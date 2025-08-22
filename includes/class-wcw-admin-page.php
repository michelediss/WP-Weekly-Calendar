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

  private static function check_caps_and_nonce(){
    if (!current_user_can('manage_options')) wp_send_json_error(['message'=>'Permessi insufficienti']);
    check_ajax_referer('wcw_admin', 'nonce');
  }

  public static function ajax_save_event(){
    self::check_caps_and_nonce();

    $id     = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    $name   = $_POST['name']   ?? '';
    $sub    = $_POST['subtitle'] ?? '';
    $day    = $_POST['weekday']?? 1;
    $time   = $_POST['time']   ?? '';
    $time_e = $_POST['time_end'] ?? '';
    $cat    = isset($_POST['category_id']) ? (int)$_POST['category_id'] : null;

    if (!$name || !$time) wp_send_json_error(['message'=>'Nome e ora inizio sono obbligatori']);

    if ($id){
      $ok = WCW_DB::update_event($id, $name, $day, $time, $cat, $sub, $time_e);
    } else {
      $ok = WCW_DB::insert_event($name, $day, $time, $cat, $sub, $time_e);
    }
    $ok ? wp_send_json_success() : wp_send_json_error(['message'=>'Errore di salvataggio']);
  }

  public static function ajax_delete_event(){
    self::check_caps_and_nonce();
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    $ok = $id ? WCW_DB::delete_event($id) : false;
    $ok ? wp_send_json_success() : wp_send_json_error(['message'=>'Errore eliminazione']);
  }

  public static function save_closure(){
    if (!current_user_can('manage_options')) wp_die('Permessi insufficienti');
    check_admin_referer('wcw_admin_closure');

    // Importante: salva la checkbox come 0/1
    update_option('wcw_closure_enabled', isset($_POST['wcw_closure_enabled']) ? 1 : 0);

    // Date (accetta sia Y-m-d che d/m/Y; non le valida qui)
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

    // Minimal UI: elenco + form rapido
    $events = WCW_DB::get_events();
    $nonce = wp_create_nonce('wcw_admin');
    ?>
    <div class="wrap">
      <h1><?php esc_html_e('Calendario settimanale','wcw'); ?></h1>

      <h2 class="title"><?php esc_html_e('Nuovo/Modifica evento','wcw'); ?></h2>
      <form id="wcw-event-form" onsubmit="return false;">
        <input type="hidden" name="nonce" value="<?php echo esc_attr($nonce); ?>">
        <input type="hidden" name="action" value="wcw_save_event">
        <input type="hidden" name="id" value="">

        <table class="form-table" role="presentation">
          <tbody>
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
            $start = $ev->time ? substr($ev->time,0,5) : '';
            $end   = !empty($ev->time_end) ? substr($ev->time_end,0,5) : '';
            $when  = $end ? ($start.' – '.$end) : $start; ?>
            <tr data-id="<?php echo (int)$ev->id; ?>"
                data-name="<?php echo esc_attr($ev->name); ?>"
                data-subtitle="<?php echo esc_attr($ev->subtitle); ?>"
                data-weekday="<?php echo (int)$ev->weekday; ?>"
                data-time="<?php echo esc_attr($start); ?>"
                data-time_end="<?php echo esc_attr($end); ?>">
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

      // Edit
      document.querySelectorAll('.wcw-edit').forEach(b=>b.addEventListener('click', (e)=>{
        const tr = e.target.closest('tr');
        form.id.value        = tr.dataset.id;
        form.name.value      = tr.dataset.name;
        form.subtitle.value  = tr.dataset.subtitle || '';
        form.weekday.value   = tr.dataset.weekday;
        form.time.value      = tr.dataset.time;
        form.time_end.value  = tr.dataset.time_end || '';
        window.scrollTo({top: form.offsetTop - 20, behavior:'smooth'});
      }));

      // Delete
      document.querySelectorAll('.wcw-del').forEach(b=>b.addEventListener('click', async (e)=>{
        if(!confirm('Eliminare definitivamente questo evento?')) return;
        const tr = e.target.closest('tr');
        const fd = new FormData();
        fd.append('action','wcw_delete_event');
        fd.append('nonce','<?php echo esc_js($nonce); ?>');
        fd.append('id', tr.dataset.id);
        const res = await fetch(ajaxurl, { method:'POST', body: fd });
        const json = await res.json();
        if (json.success) tr.remove(); else alert(json.data?.message || 'Errore');
      }));

      // Save
      btn.addEventListener('click', async ()=>{
        const fd = new FormData(form);
        const res = await fetch(ajaxurl, { method: 'POST', body: fd });
        const json = await res.json();
        if (json.success) location.reload(); else alert(json.data?.message || 'Errore');
      });
    })();
    </script>
    <?php
  }
}
endif;
