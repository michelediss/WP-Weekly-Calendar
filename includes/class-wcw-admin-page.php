<?php
if (!class_exists('WCW_Admin_Page')):
  class WCW_Admin_Page
  {

    public static function init()
    {
      add_action('admin_menu', [__CLASS__, 'menu']);
      add_action('wp_ajax_wcw_save_event', [__CLASS__, 'ajax_save_event']);
      add_action('wp_ajax_wcw_delete_event', [__CLASS__, 'ajax_delete_event']);
      add_action('admin_post_wcw_save_closure', [__CLASS__, 'save_closure']);
    }

    public static function menu()
    {
      add_menu_page(
        __('Calendario settimanale', 'wcw'),
        __('Calendario', 'wcw'),
        'manage_options',
        'wcw-calendar',
        [__CLASS__, 'render_page'],
        'dashicons-calendar-alt',
        56
      );
    }

    /* ============ Utilities ============ */

    private static function json_error($message, $code, $debug = [])
    {
      wp_send_json_error(['message' => $message, 'code' => $code, 'debug' => $debug]);
    }

    private static function check_caps_and_nonce()
    {
      if (!current_user_can('manage_options')) {
        self::json_error('Permessi insufficienti', 'capabilities');
      }
      $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';
      if (!$nonce || !wp_verify_nonce($nonce, 'wcw_admin')) {
        self::json_error('Nonce non valido o scaduto. Ricarica la pagina.', 'bad_nonce');
      }
    }

    private static function clean_time($t)
    {
      $t = is_string($t) ? trim($t) : '';
      if ($t === '' || $t === '00:00' || $t === '00:00:00' || $t === '0:00')
        return '';
      return substr($t, 0, 5);
    }

    /* ============ AJAX ============ */

    public static function ajax_save_event()
    {
      self::check_caps_and_nonce();

      $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
      $name = isset($_POST['name']) ? sanitize_text_field(wp_unslash($_POST['name'])) : '';
      $sub = isset($_POST['subtitle']) ? sanitize_text_field(wp_unslash($_POST['subtitle'])) : '';
      $day = isset($_POST['weekday']) ? (int) $_POST['weekday'] : 1;
      $time = isset($_POST['time']) ? sanitize_text_field(wp_unslash($_POST['time'])) : '';

      $time_e_raw = isset($_POST['time_end']) ? sanitize_text_field(wp_unslash($_POST['time_end'])) : '';
      $time_e_raw = is_string($time_e_raw) ? trim($time_e_raw) : '';
      $time_e = ($time_e_raw === '' || $time_e_raw === '00:00' || $time_e_raw === '00:00:00' || $time_e_raw === '0:00')
        ? '' : substr($time_e_raw, 0, 5);

      // Piano A: category_id ≡ activity_id. Prendi dall'hidden o dal select.
      $cat = isset($_POST['category_id']) ? (int) $_POST['category_id'] : 0;
      if (!$cat) {
        $activity_id = isset($_POST['activity_id']) ? (int) $_POST['activity_id'] : 0;
        if ($activity_id > 0)
          $cat = $activity_id;
      }
      // In update, se ancora 0, mantieni la categoria precedente
      if ($id && !$cat && method_exists('WCW_DB', 'get_event')) {
        $prev = WCW_DB::get_event($id);
        if ($prev && !empty($prev->category_id))
          $cat = (int) $prev->category_id;
      }
      $cat = $cat ?: null; // evita 0

      if (!$name || !$time) {
        self::json_error('Nome e ora inizio sono obbligatori', 'validation', [
          'name_empty' => empty($name),
          'time_empty' => empty($time),
        ]);
      }

      if ($id) {
        $result = WCW_DB::update_event($id, $name, $day, $time, $cat, $sub, $time_e);
        $success = ($result !== false);           // false = errore, 0/1+ = ok
        $affected = is_numeric($result) ? (int) $result : null;
      } else {
        $result = WCW_DB::insert_event($name, $day, $time, $cat, $sub, $time_e);
        $success = ($result !== false);
        $affected = is_numeric($result) ? (int) $result : 1;
      }

      if ($success) {
        wp_send_json_success(['affected' => $affected]);
      } else {
        global $wpdb;
        self::json_error('Errore di salvataggio', 'db_write_failed', [
          'wpdb_last_error' => $wpdb->last_error,
          'id' => (int) $id,
          'day' => (int) $day,
          'time' => (string) $time,
          'time_end' => (string) $time_e,
          'category_id' => $cat,
          'name_len' => strlen($name),
        ]);
      }
    }

    public static function ajax_delete_event()
    {
      self::check_caps_and_nonce();
      $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;

      $result = $id ? WCW_DB::delete_event($id) : false;
      $success = ($result !== false);

      if ($success) {
        wp_send_json_success(['affected' => is_numeric($result) ? (int) $result : null]);
      } else {
        global $wpdb;
        self::json_error('Errore eliminazione', 'db_delete_failed', [
          'wpdb_last_error' => $wpdb->last_error,
          'id' => (int) $id,
        ]);
      }
    }

    /* ============ Chiusure ============ */

    public static function save_closure()
    {
      if (!current_user_can('manage_options'))
        wp_die('Permessi insufficienti');
      check_admin_referer('wcw_admin_closure');

      update_option('wcw_closure_enabled', isset($_POST['wcw_closure_enabled']) ? 1 : 0);

      $start = isset($_POST['wcw_closure_start']) ? sanitize_text_field($_POST['wcw_closure_start']) : '';
      $end = isset($_POST['wcw_closure_end']) ? sanitize_text_field($_POST['wcw_closure_end']) : '';
      $msg = isset($_POST['wcw_closure_message']) ? wp_kses_post($_POST['wcw_closure_message']) : '';

      update_option('wcw_closure_start', $start);
      update_option('wcw_closure_end', $end);
      update_option('wcw_closure_message', $msg);

      wp_safe_redirect(admin_url('admin.php?page=wcw-calendar&updated=1'));
      exit;
    }

    /* ============ UI ============ */

    public static function render_page()
    {
      if (!current_user_can('manage_options'))
        return;

      $events = WCW_DB::get_events();
      $nonce = wp_create_nonce('wcw_admin');
      $activities = WCW_DB::get_filter_categories();

      // mappa id->nome per mostrare la categoria in tabella
      $cat_map = [];
      foreach ($activities as $act) {
        $cat_map[(int) $act->id] = $act->name;
      }

      // nomi giorni
      $weekday_names = [1 => 'Lunedì', 2 => 'Martedì', 3 => 'Mercoledì', 4 => 'Giovedì', 5 => 'Venerdì', 6 => 'Sabato', 7 => 'Domenica'];
      ?>
      <div class="wrap wcw-admin">
        <h1><?php esc_html_e('Calendario settimanale', 'wcw'); ?></h1>

        <style>
          .wcw-admin .wcw-section {
            margin-top: 24px
          }

          .wcw-admin .wcw-card {
            background: #fff;
            border: 1px solid #c3c4c7;
            border-radius: 8px;
            padding: 16px
          }

          .wcw-admin .wcw-card h2 {
            margin-top: 0
          }

          .wcw-admin .wcw-help {
            margin: 0 0 12px;
            color: #646970
          }

          /* Griglia form */
          .wcw-form-grid {
            display: grid;
            gap: 16px
          }

          .wcw-row-1 {
            grid-template-columns: 1fr 1fr 1fr
          }

          .wcw-row-2 {
            grid-template-columns: 2fr 1fr 1fr
          }

          @media (max-width:1024px) {

            .wcw-row-1,
            .wcw-row-2 {
              grid-template-columns: 1fr
            }
          }

          .wcw-field {
            display: flex;
            flex-direction: column;
            gap: 6px
          }

          .wcw-field small.description {
            color: #646970
          }

          .wcw-actions {
            margin-top: 12px
          }

          /* Tabella anteprima */
          .wcw-admin table.widefat th,
          .wcw-admin table.widefat td {
            vertical-align: middle
          }

          .wcw-admin table.widefat thead th {
            white-space: nowrap
          }
        </style>

        <!-- Form evento -->
        <div class="wcw-section wcw-card" aria-labelledby="wcw-form-title">
          <h2 id="wcw-form-title"><?php esc_html_e('Aggiungi o modifica un’attività in calendario', 'wcw'); ?></h2>
          <p class="wcw-help">Compila i dettagli e premi <strong>Salva</strong>. La lista completa è in fondo alla pagina.</p>

          <form id="wcw-event-form" onsubmit="return false;" novalidate>
            <input type="hidden" name="nonce" value="<?php echo esc_attr($nonce); ?>">
            <input type="hidden" name="action" value="wcw_save_event">
            <input type="hidden" name="id" value="">
            <input type="hidden" name="category_id" id="wcw-category-id" value="">

            <!-- Riga 1: titolo, dettaglio, attività (33/33/33) -->
            <div class="wcw-form-grid wcw-row-1" role="group" aria-label="<?php esc_attr_e('Dettagli contenuto', 'wcw'); ?>">
              <div class="wcw-field">
                <label for="wcw-name"><strong><?php esc_html_e('Nome attività', 'wcw'); ?></strong></label>
                <input id="wcw-name" type="text" name="name" class="regular-text" placeholder="Es. Corso di Yoga"
                  required>
              </div>

              <div class="wcw-field">
                <label for="wcw-subtitle"><strong><?php esc_html_e('Didascalia (facoltativa)', 'wcw'); ?></strong></label>
                <input id="wcw-subtitle" type="text" name="subtitle" class="regular-text"
                  placeholder="Es. Ogni primo martedì del mese">
              </div>

              <div class="wcw-field">
                <label for="wcw-activity-id"><strong><?php esc_html_e('Collega ad un’Attività', 'wcw'); ?></strong></label>
                <select id="wcw-activity-id" name="activity_id" class="regular-text">
                  <option value="0"><?php esc_html_e('— Nessun collegamento —', 'wcw'); ?></option>
                  <?php foreach ($activities as $act): ?>
                    <option value="<?php echo (int) $act->id; ?>" data-slug="<?php echo esc_attr($act->slug); ?>">
                      <?php echo esc_html($act->name); ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>
            </div>

            <!-- Riga 2: giorno (50), ora inizio (25), ora fine (25) -->
            <div class="wcw-form-grid wcw-row-2" role="group" aria-label="<?php esc_attr_e('Pianificazione', 'wcw'); ?>"
              style="margin-top:24px;">
              <div class="wcw-field">
                <label for="wcw-weekday"><strong><?php esc_html_e('Giorno della settimana', 'wcw'); ?></strong></label>
                <select id="wcw-weekday" name="weekday">
                  <option value="1">Lunedì</option>
                  <option value="2">Martedì</option>
                  <option value="3">Mercoledì</option>
                  <option value="4">Giovedì</option>
                  <option value="5">Venerdì</option>
                  <option value="6">Sabato</option>
                  <option value="7">Domenica</option>
                </select>
              </div>

              <div class="wcw-field">
                <label for="wcw-time"><strong><?php esc_html_e('Ora di inizio', 'wcw'); ?></strong></label>
                <input id="wcw-time" type="time" name="time" required>
              </div>

              <div class="wcw-field">
                <label for="wcw-time-end"><strong><?php esc_html_e('Ora di fine (facoltativa)', 'wcw'); ?></strong></label>
                <input id="wcw-time-end" type="time" name="time_end" placeholder="Facoltativo">
                <small
                  class="description"><?php esc_html_e('Se non indicata, verrà mostrata solo l’ora di inizio.', 'wcw'); ?></small>
              </div>
            </div>

            <div class="wcw-actions">
              <button class="button button-primary" id="wcw-save-btn"><?php esc_html_e('Salva', 'wcw'); ?></button>
            </div>
          </form>
        </div>

        <!-- Chiusura straordinaria -->
        <div class="wcw-section wcw-card" aria-labelledby="wcw-closure-title">
          <h2 id="wcw-closure-title"><?php esc_html_e('Chiusura straordinaria', 'wcw'); ?></h2>
          <p class="wcw-help">Nasconde temporaneamente il calendario sul sito e mostra un messaggio agli utenti.</p>

          <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <?php wp_nonce_field('wcw_admin_closure'); ?>
            <input type="hidden" name="action" value="wcw_save_closure">

            <div class="wcw-form-grid wcw-row-2" role="group"
              aria-label="<?php esc_attr_e('Impostazioni chiusura', 'wcw'); ?>">
              <div class="wcw-field">
                <label><strong><?php esc_html_e('Attiva chiusura', 'wcw'); ?></strong></label>
                <label><input type="checkbox" name="wcw_closure_enabled" value="1" <?php checked(1, get_option('wcw_closure_enabled', 0)); ?>> <?php esc_html_e('Abilita', 'wcw'); ?></label>
                <small
                  class="description"><?php esc_html_e('Se attivo senza date, il calendario viene nascosto subito.', 'wcw'); ?></small>
              </div>

              <div class="wcw-field">
                <label for="wcw-closure-start"><strong><?php esc_html_e('Dal', 'wcw'); ?></strong></label>
                <input id="wcw-closure-start" type="date" name="wcw_closure_start"
                  value="<?php echo esc_attr(get_option('wcw_closure_start', '')); ?>">
              </div>

              <div class="wcw-field">
                <label for="wcw-closure-end"><strong><?php esc_html_e('Al', 'wcw'); ?></strong></label>
                <input id="wcw-closure-end" type="date" name="wcw_closure_end"
                  value="<?php echo esc_attr(get_option('wcw_closure_end', '')); ?>">
              </div>
            </div>

            <div class="wcw-field" style="margin-top:12px;">
              <label for="wcw-closure-message"><strong><?php esc_html_e('Messaggio agli utenti', 'wcw'); ?></strong></label>
              <textarea id="wcw-closure-message" name="wcw_closure_message" rows="5" class="large-text"
                style="min-height:120px;"><?php echo esc_textarea(get_option('wcw_closure_message', 'Le attività riprenderanno il giorno {date}')); ?></textarea>
              <small
                class="description"><?php esc_html_e('Puoi usare {date} per inserire automaticamente la data di riapertura.', 'wcw'); ?></small>
            </div>

            <div class="wcw-actions">
              <button class="button button-primary"><?php esc_html_e('Salva chiusura', 'wcw'); ?></button>
            </div>
          </form>
        </div>

        <!-- Anteprima / Lista eventi -->
        <div class="wcw-section wcw-card" aria-labelledby="wcw-list-title">
          <h2 id="wcw-list-title"><?php esc_html_e('Anteprima calendario', 'wcw'); ?></h2>
          <p class="wcw-help">Usa <em>Modifica</em> per caricare i dati nel form in alto oppure <em>Elimina</em> per
            rimuovere.</p>

          <table class="widefat">
            <thead>
              <tr>
                <th><?php esc_html_e('Giorno', 'wcw'); ?></th>
                <th><?php esc_html_e('Orario', 'wcw'); ?></th>
                <th><?php esc_html_e('Titolo', 'wcw'); ?></th>
                <th><?php esc_html_e('Dettaglio', 'wcw'); ?></th>
                <th><?php esc_html_e('Categoria', 'wcw'); ?></th>
                <th><?php esc_html_e('Azioni', 'wcw'); ?></th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($events as $ev):
                $start = self::clean_time($ev->time);
                $end = self::clean_time($ev->time_end);
                $when = ($end !== '') ? ($start . ' – ' . $end) : $start;
                $wd = isset($weekday_names[(int) $ev->weekday]) ? $weekday_names[(int) $ev->weekday] : (int) $ev->weekday;
                $cat_label = '';
                if (!empty($ev->category_id) && isset($cat_map[(int) $ev->category_id])) {
                  $cat_label = $cat_map[(int) $ev->category_id];
                } elseif (!empty($ev->category_slug)) {
                  $cat_label = $ev->category_slug;
                } else {
                  $cat_label = '—';
                }
                ?>
                <tr data-id="<?php echo (int) $ev->id; ?>" data-name="<?php echo esc_attr($ev->name); ?>"
                  data-subtitle="<?php echo esc_attr($ev->subtitle); ?>" data-weekday="<?php echo (int) $ev->weekday; ?>"
                  data-time="<?php echo esc_attr($start); ?>" data-time_end="<?php echo esc_attr($end); ?>"
                  data-category_id="<?php echo (int) ($ev->category_id ?? 0); ?>"
                  data-category_slug="<?php echo esc_attr($ev->category_slug ?? ''); ?>">
                  <td><?php echo esc_html($wd); ?></td>
                  <td><?php echo esc_html($when); ?></td>
                  <td><?php echo esc_html($ev->name); ?></td>
                  <td><?php echo esc_html($ev->subtitle); ?></td>
                  <td><?php echo esc_html($cat_label); ?></td>
                  <td>
                    <button class="button wcw-edit"><?php esc_html_e('Modifica', 'wcw'); ?></button>
                    <button class="button wcw-del"><?php esc_html_e('Elimina', 'wcw'); ?></button>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>

      <script>
        (function () {
          const form = document.getElementById('wcw-event-form');
          const btn = document.getElementById('wcw-save-btn');
          const sel = document.getElementById('wcw-activity-id');
          const catHidden = document.getElementById('wcw-category-id');

          function showAlert(msg) { alert(msg); }

          async function sendAjax(fd) {
            try {
              const res = await fetch(ajaxurl, { method: 'POST', body: fd, credentials: 'same-origin' });
              const ct = res.headers.get('content-type') || '';
              if (!res.ok) {
                const txt = await res.text().catch(() => '');
                showAlert(`Errore HTTP ${res.status} ${res.statusText}\n\n${(txt || '').slice(0, 800)}`);
                return null;
              }
              if (ct.includes('application/json')) {
                const json = await res.json();
                if (json && json.success) return json;
                const msg = json?.data?.message || 'Errore';
                const code = json?.data?.code ? ` [${json.data.code}]` : '';
                const dbg = json?.data?.debug ? `\n\nDEBUG:\n${JSON.stringify(json.data.debug, null, 2)}` : '';
                showAlert(`${msg}${code}${dbg}`);
                return null;
              }
              const body = await res.text();
              if (body.trim() === '-1') showAlert('Sicurezza: nonce non valido o scaduto. Ricarica la pagina e riprova.');
              else showAlert(`Risposta non JSON dal server:\n\n${body.slice(0, 800)}`);
              return null;
            } catch (err) {
              showAlert(`Errore di rete/JS: ${err?.message || err}`);
              return null;
            }
          }

          if (sel) {
            sel.addEventListener('change', () => {
              if (sel.value !== '0') catHidden.value = sel.value;
            });
          }

          // Edit
          document.querySelectorAll('.wcw-edit').forEach(b => b.addEventListener('click', (e) => {
            const tr = e.target.closest('tr');
            form.id.value = tr.dataset.id;
            form.name.value = tr.dataset.name;
            form.subtitle.value = tr.dataset.subtitle || '';
            form.weekday.value = tr.dataset.weekday;
            form.time.value = tr.dataset.time;
            form.time_end.value = tr.dataset.time_end || '';
            catHidden.value = tr.dataset.category_id && parseInt(tr.dataset.category_id, 10) > 0 ? tr.dataset.category_id : '';
            window.scrollTo({ top: form.closest('.wcw-card').offsetTop - 20, behavior: 'smooth' });
          }));

          // Delete
          document.querySelectorAll('.wcw-del').forEach(b => b.addEventListener('click', async (e) => {
            if (!confirm('Eliminare definitivamente questo evento?')) return;
            const tr = e.target.closest('tr');
            const fd = new FormData();
            fd.append('action', 'wcw_delete_event');
            fd.append('nonce', '<?php echo esc_js($nonce); ?>');
            fd.append('id', tr.dataset.id);
            const json = await sendAjax(fd);
            if (json) tr.remove();
          }));

          // Save
          btn.addEventListener('click', async () => {
            const fd = new FormData(form);
            const json = await sendAjax(fd);
            if (json) {
              if (typeof json.data?.affected !== 'undefined' && json.data.affected === 0) {
                alert('Salvato (nessuna modifica rilevata).');
              }
              location.reload();
            }
          });
        })();
      </script>
      <?php
    }

  }
endif;
