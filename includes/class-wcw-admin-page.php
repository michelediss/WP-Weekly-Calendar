<?php
if (!class_exists('WCW_Admin_Page')):
class WCW_Admin_Page {

  public static function init(){
    add_action('admin_menu', [__CLASS__, 'menu']);
    add_action('wp_ajax_wcw_save_event',   [__CLASS__, 'ajax_save_event']);
    add_action('wp_ajax_wcw_delete_event', [__CLASS__, 'ajax_delete_event']);
    add_action('admin_post_wcw_save_closure', [__CLASS__, 'save_closure']);
  }

  public static function menu(){
    add_menu_page(
      __('Calendario settimanale','wcw'),
      __('Calendario','wcw'),
      'manage_options',
      'wcw-calendar',
      [__CLASS__,'render_page'],
      'dashicons-calendar-alt',
      56
    );
  }

  /**
   * Controlli sicurezza con risposta JSON descrittiva.
   */
  private static function check_caps_and_nonce(){
    if (!current_user_can('manage_options')) {
      wp_send_json_error(['message'=>'Permessi insufficienti','code'=>'capabilities']);
    }
    $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';
    if (!$nonce || !wp_verify_nonce($nonce, 'wcw_admin')) {
      wp_send_json_error(['message'=>'Nonce non valido o scaduto. Ricarica la pagina.','code'=>'bad_nonce']);
    }
  }

  /**
   * Orario: '' se vuoto/zero, altrimenti HH:MM.
   */
  private static function clean_time($t){
    $t = is_string($t) ? trim($t) : '';
    if ($t === '' || $t === '00:00' || $t === '00:00:00' || $t === '0:00') return '';
    return substr($t, 0, 5);
  }

  /**
   * Normalizza una stringa in "chiave slug" eliminando i connettivi (e/and/&/amp/et).
   * Esempio: "teatro-musica-e-cultura" => "teatro-musica-cultura"
   */
  private static function normalize_key($s){
    $s = is_string($s) ? strtolower(sanitize_title($s)) : '';
    // rimuovi token singoli e/and/amp/et quando appaiono come parola tra i trattini
    $s = preg_replace('/(^|-)(' . 'e|and|amp|et' . ')(?=-|$)/', '$1', $s);
    $s = preg_replace('/-+/', '-', $s);
    return trim($s, '-');
  }

  /**
   * Trova l'ID categoria a partire dall'ID del CPT attività,
   * provando: slug esatto → slug normalizzato (senza connettivi) → nome normalizzato.
   */
  private static function resolve_category_id_from_activity($activity_id){
    if (!$activity_id) return 0;

    $slug  = get_post_field('post_name', $activity_id);
    $title = get_the_title($activity_id);

    $key_slug  = self::normalize_key($slug);
    $key_title = self::normalize_key($title);

    $rows = WCW_DB::get_filter_categories(); // ciascuno: ->id, ->slug, ->name
    foreach ($rows as $row) {
      $row_slug  = isset($row->slug) ? (string)$row->slug : '';
      $row_name  = isset($row->name) ? (string)$row->name : '';
      $row_key_s = self::normalize_key($row_slug);
      $row_key_n = self::normalize_key($row_name);

      if ($row_slug === $slug || $row_key_s === $key_slug || $row_key_s === $key_title || $row_key_n === $key_title) {
        return (int)$row->id;
      }
    }
    return 0;
  }

  public static function ajax_save_event(){
    self::check_caps_and_nonce();

    $id          = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    $activity_id = isset($_POST['activity_id']) ? (int) $_POST['activity_id'] : 0;

    // Nome indipendente dal CPT
    $name = isset($_POST['name']) ? sanitize_text_field(wp_unslash($_POST['name'])) : '';

    $sub   = isset($_POST['subtitle']) ? sanitize_text_field(wp_unslash($_POST['subtitle'])) : '';
    $day   = isset($_POST['weekday'])  ? (int) $_POST['weekday'] : 1;
    $time  = isset($_POST['time'])     ? sanitize_text_field(wp_unslash($_POST['time'])) : '';

    // Fine evento facoltativa
    $time_e_raw = isset($_POST['time_end']) ? sanitize_text_field(wp_unslash($_POST['time_end'])) : '';
    $time_e_raw = is_string($time_e_raw) ? trim($time_e_raw) : '';
    $time_e = ($time_e_raw === '' || $time_e_raw === '00:00' || $time_e_raw === '00:00:00' || $time_e_raw === '0:00')
      ? '' : substr($time_e_raw, 0, 5);

    // Categoria: hidden se presente; altrimenti deduci in modo robusto dall'attività
    $cat = isset($_POST['category_id']) ? (int) $_POST['category_id'] : 0;
    if (!$cat && $activity_id) {
      $cat = self::resolve_category_id_from_activity($activity_id);
    }
    // evita null per driver che usano %d
    $cat = (int)$cat;

    if (!$name || !$time) {
      wp_send_json_error(['message'=>'Nome e ora inizio sono obbligatori','code'=>'validation','debug'=>[
        'name_empty' => empty($name),
        'time_empty' => empty($time),
      ]]);
    }

    // Tratta 0 righe aggiornate come successo
    if ($id){
      $result   = WCW_DB::update_event($id, $name, $day, $time, $cat ?: null, $sub, $time_e);
      $success  = ($result !== false); // false = errore; 0 o >=1 = ok
      $affected = is_numeric($result) ? (int)$result : null;
    } else {
      $result   = WCW_DB::insert_event($name, $day, $time, $cat ?: null, $sub, $time_e);
      $success  = ($result !== false);
      $affected = is_numeric($result) ? (int)$result : 1;
    }

    if ($success) {
      wp_send_json_success(['affected'=>$affected]);
    } else {
      global $wpdb;
      // info extra per capire i mismatch
      $act_slug   = $activity_id ? get_post_field('post_name', $activity_id) : '';
      $act_title  = $activity_id ? get_the_title($activity_id) : '';
      $act_key_s  = self::normalize_key($act_slug);
      $act_key_t  = self::normalize_key($act_title);

      // includi anche la lista delle categorie "key" disponibili (utile per debug)
      $rows = WCW_DB::get_filter_categories();
      $cat_keys = [];
      foreach ($rows as $r){
        $cat_keys[$r->slug] = self::normalize_key($r->slug);
      }

      wp_send_json_error([
        'message' => 'Errore di salvataggio',
        'code'    => 'db_write_failed',
        'debug'   => [
          'wpdb_last_error'    => $wpdb->last_error,
          'id'                 => (int)$id,
          'day'                => (int)$day,
          'time'               => (string)$time,
          'time_end'           => (string)$time_e,
          'category_id'        => $cat ?: null,
          'name_len'           => strlen($name),
          'activity_id'        => (int)$activity_id,
          'activity_slug'      => $act_slug,
          'activity_title'     => $act_title,
          'activity_key_slug'  => $act_key_s,
          'activity_key_title' => $act_key_t,
          'category_keys'      => $cat_keys,
        ],
      ]);
    }
  }

  public static function ajax_delete_event(){
    self::check_caps_and_nonce();
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    $result  = $id ? WCW_DB::delete_event($id) : false;
    $success = ($result !== false);

    if ($success) {
      wp_send_json_success(['affected' => is_numeric($result) ? (int)$result : null]);
    } else {
      global $wpdb;
      wp_send_json_error([
        'message'=>'Errore eliminazione',
        'code'   =>'db_delete_failed',
        'debug'  => [
          'wpdb_last_error' => $wpdb->last_error,
          'id'              => (int)$id,
        ],
      ]);
    }
  }

  public static function save_closure(){
    if (!current_user_can('manage_options')) wp_die('Permessi insufficienti');
    check_admin_referer('wcw_admin_closure');

    update_option('wcw_closure_enabled', isset($_POST['wcw_closure_enabled']) ? 1 : 0);
    $start = isset($_POST['wcw_closure_start']) ? sanitize_text_field($_POST['wcw_closure_start']) : '';
    $end   = isset($_POST['wcw_closure_end'])   ? sanitize_text_field($_POST['wcw_closure_end'])   : '';
    $msg   = isset($_POST['wcw_closure_message']) ? wp_kses_post($_POST['wcw_closure_message']) : '';

    update_option('wcw_closure_start', $start);
    update_option('wcw_closure_end',   $end);
    update_option('wcw_closure_message', $msg);

    wp_safe_redirect( admin_url('admin.php?page=wcw-calendar&updated=1') );
    exit;
  }

  public static function render_page(){
    if (!current_user_can('manage_options')) return;

    $events = WCW_DB::get_events();
    $nonce  = wp_create_nonce('wcw_admin');

    // Attività (CPT)
    $activities = get_posts([
      'post_type'        => 'attivita',
      'post_status'      => 'publish',
      'posts_per_page'   => -1,
      'orderby'          => 'title',
      'order'            => 'ASC',
      'suppress_filters' => false,
    ]);

    // Categorie: mappa diretta slug→id e mappa "key" (senza connettivi) → id
    $cat_rows = WCW_DB::get_filter_categories();  // ->id, ->slug
    $cat_map  = [];       // slug => id
    $cat_key_map = [];    // normalized_key => id
    foreach ($cat_rows as $cr) {
      if (!empty($cr->slug)) {
        $cat_map[$cr->slug] = (int) $cr->id;
        $cat_key_map[self::normalize_key($cr->slug)] = (int) $cr->id;
      }
      if (!empty($cr->name)) {
        $cat_key_map[self::normalize_key($cr->name)] = (int) $cr->id;
      }
    }
    ?>
    <div class="wrap">
      <h1><?php esc_html_e('Calendario settimanale','wcw'); ?></h1>

      <h2 class="title"><?php esc_html_e('Nuovo/Modifica evento','wcw'); ?></h2>
      <form id="wcw-event-form" onsubmit="return false;">
        <input type="hidden" name="nonce" value="<?php echo esc_attr($nonce); ?>">
        <input type="hidden" name="action" value="wcw_save_event">
        <input type="hidden" name="id" value="">
        <!-- Categoria assegnata automaticamente -->
        <input type="hidden" name="category_id" id="wcw-category-id" value="">

        <table class="form-table" role="presentation">
          <tbody>
            <tr>
              <th scope="row"><label for="wcw-activity-id">Attività (CPT)</label></th>
              <td>
                <select id="wcw-activity-id" name="activity_id" class="regular-text">
                  <option value="0"><?php esc_html_e('— Seleziona un’attività —','wcw'); ?></option>
                  <?php foreach ($activities as $act):
                    $slug = get_post_field('post_name', $act->ID); ?>
                    <option value="<?php echo (int) $act->ID; ?>" data-slug="<?php echo esc_attr($slug); ?>">
                      <?php echo esc_html(get_the_title($act->ID)); ?>
                    </option>
                  <?php endforeach; ?>
                </select>
                <p class="description">Selezionando un’attività viene assegnata automaticamente la categoria; il nome evento resta indipendente.</p>
              </td>
            </tr>

            <tr>
              <th scope="row"><label>Nome</label></th>
              <td><input type="text" name="name" class="regular-text" required></td>
            </tr>
            <tr>
              <th scope="row"><label>Sottotitolo</label></th>
              <td><input type="text" name="subtitle" class="regular-text" placeholder="Facoltativo"></td>
            </tr>
            <tr>
              <th scope="row"><label>Giorno</label></th>
              <td>
                <select name="weekday">
                  <option value="1">Lunedì</option>
                  <option value="2">Martedì</option>
                  <option value="3">Mercoledì</option>
                  <option value="4">Giovedì</option>
                  <option value="5">Venerdì</option>
                  <option value="6">Sabato</option>
                  <option value="7">Domenica</option>
                </select>
              </td>
            </tr>
            <tr>
              <th scope="row"><label>Ora inizio</label></th>
              <td><input type="time" name="time" required></td>
            </tr>
            <tr>
              <th scope="row"><label>Ora fine</label></th>
              <td><input type="time" name="time_end" placeholder="Facoltativo"></td>
            </tr>
          </tbody>
        </table>
        <p><button class="button button-primary" id="wcw-save-btn">Salva evento</button></p>
      </form>

      <hr/>

      <h2><?php esc_html_e('Eventi','wcw'); ?></h2>
      <table class="widefat">
        <thead><tr><th>ID</th><th>Giorno</th><th>Ora</th><th>Titolo</th><th>Sottotitolo</th><th>Azioni</th></tr></thead>
        <tbody>
          <?php foreach ($events as $ev):
            $start = self::clean_time($ev->time);
            $end   = self::clean_time($ev->time_end);
            $when  = ($end !== '') ? ($start.' – '.$end) : $start;

            $ev_cat_slug = isset($ev->category_slug) ? (string) $ev->category_slug : '';
            $ev_cat_id   = isset($ev->category_id)   ? (int) $ev->category_id   : (isset($cat_map[$ev_cat_slug]) ? (int) $cat_map[$ev_cat_slug] : 0);
            ?>
            <tr data-id="<?php echo (int)$ev->id; ?>"
                data-name="<?php echo esc_attr($ev->name); ?>"
                data-subtitle="<?php echo esc_attr($ev->subtitle); ?>"
                data-weekday="<?php echo (int)$ev->weekday; ?>"
                data-time="<?php echo esc_attr($start); ?>"
                data-time_end="<?php echo esc_attr($end); ?>"
                data-category_id="<?php echo (int) $ev_cat_id; ?>"
                data-category_slug="<?php echo esc_attr($ev_cat_slug); ?>">
              <td><?php echo (int)$ev->id; ?></td>
              <td><?php echo (int)$ev->weekday; ?></td>
              <td><?php echo esc_html($when); ?></td>
              <td><?php echo esc_html($ev->name); ?></td>
              <td><?php echo esc_html($ev->subtitle); ?></td>
              <td>
                <button class="button wcw-edit">Modifica</button>
                <button class="button wcw-del">Elimina</button>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>

      <hr/>

      <h2><?php esc_html_e('Chiusura straordinaria','wcw'); ?></h2>
      <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
        <?php wp_nonce_field('wcw_admin_closure'); ?>
        <input type="hidden" name="action" value="wcw_save_closure">
        <table class="form-table" role="presentation">
          <tbody>
            <tr>
              <th scope="row"><label>Abilita chiusura</label></th>
              <td>
                <label><input type="checkbox" name="wcw_closure_enabled" value="1" <?php checked(1, get_option('wcw_closure_enabled', 0)); ?>> Attiva</label>
                <p class="description">Se attivo senza date, il calendario viene nascosto subito.</p>
              </td>
            </tr>
            <tr>
              <th scope="row"><label>Dal</label></th>
              <td><input type="date" name="wcw_closure_start" value="<?php echo esc_attr(get_option('wcw_closure_start','')); ?>"></td>
            </tr>
            <tr>
              <th scope="row"><label>Al</label></th>
              <td><input type="date" name="wcw_closure_end" value="<?php echo esc_attr(get_option('wcw_closure_end','')); ?>"></td>
            </tr>
            <tr>
              <th scope="row"><label>Messaggio</label></th>
              <td><input type="text" class="regular-text" name="wcw_closure_message" value="<?php echo esc_attr(get_option('wcw_closure_message','Le attività riprenderanno il giorno {date}')); ?>"></td>
            </tr>
          </tbody>
        </table>
        <p><button class="button button-primary">Salva chiusura</button></p>
      </form>
    </div>

    <script>
    (function(){
      const form = document.getElementById('wcw-event-form');
      const btn  = document.getElementById('wcw-save-btn');
      const sel  = document.getElementById('wcw-activity-id');
      const catHidden = document.getElementById('wcw-category-id');

      // mappe fornite da PHP
      const catBySlug = <?php echo wp_json_encode($cat_map, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE); ?>;
      const catByKey  = <?php echo wp_json_encode($cat_key_map, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE); ?>;

      function showAlert(msg){ alert(msg); }

      // Normalizza come in PHP: toLower + rimozione connector tokens (e/and/amp/et)
      function normalizeKey(s){
        s = (s || '').toString().toLowerCase();
        try { s = s.normalize('NFD').replace(/[\u0300-\u036f]/g, ''); } catch(e){}
        s = s.replace(/&/g, ' e ');
        s = s.replace(/[^a-z0-9\- ]+/g, ' ');
        s = s.trim().replace(/\s+/g, '-').replace(/-+/g, '-');
        s = s.replace(/(^|-)((e|and|amp|et))(?=-|$)/g, '$1');
        s = s.replace(/-+/g, '-').replace(/^-|-$/g, '');
        return s;
      }

      async function sendAjax(fd){
        try {
          const res = await fetch(ajaxurl, { method:'POST', body: fd, credentials: 'same-origin' });
          const ct  = res.headers.get('content-type') || '';

          if (!res.ok) {
            const txt = await res.text().catch(()=> '');
            showAlert(`Errore HTTP ${res.status} ${res.statusText}\n\n${(txt||'').slice(0,800)}`);
            return null;
          }

          if (ct.includes('application/json')) {
            const json = await res.json();
            if (json && json.success) return json;

            const msg  = json?.data?.message || 'Errore';
            const code = json?.data?.code ? ` [${json.data.code}]` : '';
            const dbg  = json?.data?.debug ? `\n\nDEBUG:\n${JSON.stringify(json.data.debug, null, 2)}` : '';
            showAlert(`${msg}${code}${dbg}`);
            return null;
          }

          const body = await res.text();
          if (body.trim() === '-1') {
            showAlert('Sicurezza: nonce non valido o scaduto. Ricarica la pagina e riprova.');
          } else {
            showAlert(`Risposta non JSON dal server:\n\n${body.slice(0,800)}`);
          }
          return null;

        } catch (err) {
          showAlert(`Errore di rete/JS: ${err?.message || err}`);
          return null;
        }
      }

      // Alla selezione dell'attività: assegna SOLO la categoria in hidden, usando slug o chiave normalizzata
      if (sel) {
        sel.addEventListener('change', ()=>{
          const opt = sel.options[sel.selectedIndex];
          if (!opt) return;

          if (sel.value !== '0') {
            const slug = opt.getAttribute('data-slug') || '';
            const keyFromSlug  = normalizeKey(slug);
            const keyFromText  = normalizeKey(opt.text);

            // priorità: slug esatto → key slug → key titolo
            let cid = catBySlug[slug] || catByKey[keyFromSlug] || catByKey[keyFromText] || '';
            catHidden.value = cid;
          } else {
            catHidden.value = '';
          }
        });
      }

      // Edit
      document.querySelectorAll('.wcw-edit').forEach(b=>b.addEventListener('click', (e)=>{
        const tr = e.target.closest('tr');
        form.id.value        = tr.dataset.id;
        form.name.value      = tr.dataset.name;
        form.subtitle.value  = tr.dataset.subtitle || '';
        form.weekday.value   = tr.dataset.weekday;
        form.time.value      = tr.dataset.time;
        form.time_end.value  = tr.dataset.time_end || '';

        // categoria hidden (se nota), altrimenti prova a derivarla dal dataset slug normalizzato
        if (tr.dataset.category_id && parseInt(tr.dataset.category_id,10) > 0) {
          catHidden.value = tr.dataset.category_id;
        } else if (tr.dataset.category_slug) {
          const slug = tr.dataset.category_slug;
          const key  = normalizeKey(slug);
          catHidden.value = catBySlug[slug] || catByKey[key] || '';
        } else {
          catHidden.value = '';
        }

        window.scrollTo({top: form.offsetTop - 20, behavior:'smooth'});
      }));

      // Delete con debug
      document.querySelectorAll('.wcw-del').forEach(b=>b.addEventListener('click', async (e)=>{
        if(!confirm('Eliminare definitivamente questo evento?')) return;
        const tr = e.target.closest('tr');
        const fd = new FormData();
        fd.append('action','wcw_delete_event');
        fd.append('nonce','<?php echo esc_js($nonce); ?>');
        fd.append('id', tr.dataset.id);
        const json = await sendAjax(fd);
        if (json) tr.remove();
      }));

      // Save con debug
      btn.addEventListener('click', async ()=>{
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
