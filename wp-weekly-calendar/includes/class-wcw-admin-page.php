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
    add_menu_page(__('Calendario settimanale','wcw'), __('Calendario','wcw'), 'manage_options', 'wcw-calendar', [__CLASS__,'render_page'], 'dashicons-calendar-alt', 56);
  }

  private static function check_caps_and_nonce(){ if (!current_user_can('manage_options')) wp_send_json_error(['message'=>'forbidden'], 403); check_ajax_referer('wcw_nonce','nonce'); }

  public static function ajax_save_event(){
    self::check_caps_and_nonce();
    $id   = intval($_POST['id'] ?? 0);
    $name = sanitize_text_field($_POST['name'] ?? '');
    $day  = max(1,min(7,intval($_POST['weekday'] ?? 1)));
    $time = preg_replace('/[^0-9:]/','', $_POST['time'] ?? '');
    $cat  = intval($_POST['category_id'] ?? 0) ?: null; // ID post CPT 'attivita'
    if ($name==='' || $time==='') wp_send_json_error(['message'=>'Dati mancanti'], 400);
    $ok = $id ? WCW_DB::update_event($id,$name,$day,$time,$cat) : WCW_DB::insert_event($name,$day,$time,$cat);
    $ok ? wp_send_json_success() : wp_send_json_error(['message'=>'Errore DB'], 500);
  }

  public static function ajax_delete_event(){
    self::check_caps_and_nonce(); $id=intval($_POST['id']??0);
    if(!$id) wp_send_json_error();
    $ok=WCW_DB::delete_event($id);
    $ok? wp_send_json_success(): wp_send_json_error(['message'=>'Errore DB'],500);
  }

  public static function save_closure(){
    if (!current_user_can('manage_options')) wp_die('forbidden');
    check_admin_referer('wcw_closure_form');
    update_option('wcw_closure_enabled', isset($_POST['closure_enabled']) ? 1 : 0);
    update_option('wcw_closure_start', sanitize_text_field($_POST['closure_start'] ?? ''));
    update_option('wcw_closure_end', sanitize_text_field($_POST['closure_end'] ?? ''));
    update_option('wcw_closure_message', sanitize_text_field($_POST['closure_message'] ?? 'Le attività riprenderanno il giorno {date}'));
    wp_redirect(admin_url('admin.php?page=wcw-calendar&saved=1'));
    exit;
  }

  public static function render_page(){
    if (!current_user_can('manage_options')) return;
    $cats = WCW_DB::get_categories(); // CPT attivita
    $events = WCW_DB::get_events('');
    $enabled = (bool) get_option('wcw_closure_enabled', 0);
    $start = get_option('wcw_closure_start', '');
    $end   = get_option('wcw_closure_end', '');
    $msg   = get_option('wcw_closure_message', 'Le attività riprenderanno il giorno {date}');
    $nonce = wp_create_nonce('wcw_nonce'); ?>
    <div class="wrap">
      <h1>Calendario settimanale</h1>

      <div class="wcw-grid-admin">
        <div>
          <h2>Nuova/modifica attività</h2>
          <form id="wcw-event-form" onsubmit="return false;">
            <input type="hidden" name="id" value="">
            <p><label>Nome <input type="text" name="name" required></label></p>
            <p><label>Giorno
              <select name="weekday">
                <option value="1">Lunedì</option>
                <option value="2">Martedì</option>
                <option value="3">Mercoledì</option>
                <option value="4">Giovedì</option>
                <option value="5">Venerdì</option>
                <option value="6">Sabato</option>
                <option value="7">Domenica</option>
              </select>
            </label></p>
            <p><label>Orario <input type="time" name="time" required></label></p>
            <p><label>Categoria
              <select name="category_id">
                <option value="">— nessuna —</option>
                <?php foreach ($cats as $c): ?>
                  <option value="<?php echo intval($c->id); ?>"><?php echo esc_html($c->name); ?></option>
                <?php endforeach; ?>
              </select>
            </label></p>
            <p>
              <button class="button button-primary" id="wcw-save">Salva</button>
              <button class="button" id="wcw-reset" type="reset">Reset</button>
            </p>
            <p class="description">Le categorie sono gestite dal CPT <code>attivita</code>. Colore letto dal campo ACF <code>colore</code>.</p>
          </form>
        </div>

        <div>
          <h2>Periodo di chiusura</h2>
          <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <?php wp_nonce_field('wcw_closure_form'); ?>
            <input type="hidden" name="action" value="wcw_save_closure">
            <p><label><input type="checkbox" name="closure_enabled" <?php checked($enabled); ?>> Abilita chiusura</label></p>
            <p><label>Dal <input type="date" name="closure_start" value="<?php echo esc_attr($start); ?>"></label></p>
            <p><label>Al <input type="date" name="closure_end" value="<?php echo esc_attr($end); ?>"></label></p>
            <p><label>Messaggio <input type="text" name="closure_message" size="50" value="<?php echo esc_attr($msg); ?>"></label></p>
            <p><button class="button" type="submit">Salva impostazioni</button></p>
          </form>
        </div>
      </div>

      <hr>
      <h2>Attività</h2>
      <table class="widefat" id="wcw-table">
        <thead><tr>
          <th>Nome</th><th>Giorno</th><th>Orario</th><th>Categoria</th><th>Azione</th>
        </tr></thead>
        <tbody>
        <?php foreach ($events as $e): ?>
          <tr data-id="<?php echo intval($e->id); ?>" data-day="<?php echo intval($e->weekday); ?>" data-time="<?php echo esc_attr(substr($e->time,0,5)); ?>" data-cat="<?php echo intval($e->category_id); ?>">
            <td class="c-name"><?php echo esc_html($e->name); ?></td>
            <td class="c-day"><?php echo esc_html(self::day_label((int)$e->weekday)); ?></td>
            <td class="c-time"><?php echo esc_html(substr($e->time,0,5)); ?></td>
            <td class="c-cat"><?php echo esc_html($e->category_name ?: ''); ?></td>
            <td>
              <a href="#" class="wcw-edit">Modifica</a> |
              <a href="#" class="wcw-delete">Elimina</a>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <script>
    (function(){
      const $ = document.querySelector.bind(document);
      const $$ = s => Array.from(document.querySelectorAll(s));
      const nonce = '<?php echo esc_js($nonce); ?>';

      function dayLabel(d){return {1:'Lunedì',2:'Martedì',3:'Mercoledì',4:'Giovedì',5:'Venerdì',6:'Sabato',7:'Domenica'}[d]||''}
      function fillFormFromRow(tr){ const f = $('#wcw-event-form'); f.id.value = tr.dataset.id; f.name.value = tr.querySelector('.c-name').textContent.trim(); f.weekday.value = tr.dataset.day; f.time.value = tr.dataset.time; f.category_id.value = tr.dataset.cat || ''; }

      const saveBtn = document.getElementById('wcw-save');
      if (saveBtn) saveBtn.addEventListener('click', async function(){
        const f = $('#wcw-event-form'); const fd = new FormData(f);
        fd.append('action','wcw_save_event'); fd.append('nonce',nonce);
        const res = await fetch(ajaxurl,{method:'POST',body:fd}); const json = await res.json();
        if(!json.success){ alert(json.data?.message||'Errore'); return; } location.reload();
      });

      $$('#wcw-table .wcw-edit').forEach(a=>a.addEventListener('click', function(e){ e.preventDefault(); fillFormFromRow(this.closest('tr')); window.scrollTo({top:0,behavior:'smooth'}); }));

      $$('#wcw-table .wcw-delete').forEach(a=>a.addEventListener('click', async function(e){
        e.preventDefault(); if(!confirm('Eliminare questa attività?')) return;
        const tr = this.closest('tr'); const fd = new FormData();
        fd.append('action','wcw_delete_event'); fd.append('nonce',nonce); fd.append('id', tr.dataset.id);
        const res = await fetch(ajaxurl,{method:'POST',body:fd}); const json = await res.json();
        if(json.success){ tr.remove(); } else { alert(json.data?.message||'Errore'); }
      }));
    })();
    </script>
    <?php
  }

  private static function day_label($d){ $map = [1=>'Lunedì',2=>'Martedì',3=>'Mercoledì',4=>'Giovedì',5=>'Venerdì',6=>'Sabato',7=>'Domenica']; return $map[$d] ?? ''; }
}
endif;
