#!/usr/bin/env bash
set -euo pipefail

PLUGIN_SLUG="wp-weekly-calendar"
VERSION="0.5.0"

if [[ -d "$PLUGIN_SLUG" ]]; then
  echo "La cartella '$PLUGIN_SLUG' esiste già. Esco per non sovrascrivere."
  exit 1
fi

echo "-> Crea struttura cartelle"
mkdir -p "${PLUGIN_SLUG}/includes" "${PLUGIN_SLUG}/assets"

echo "-> Scrive: ${PLUGIN_SLUG}/wp-weekly-calendar.php"
cat > "${PLUGIN_SLUG}/wp-weekly-calendar.php" <<'PHP'
<?php
/**
 * Plugin Name: WP Weekly Calendar (DB + Single Admin + Grid AJAX)
 * Description: Attività gestite su tabelle custom, unica pagina admin, frontend a 7 colonne con filtri AJAX.
 * Version: 0.5.0
 * Author: Tu
 * Text Domain: wcw
 */

if (!defined('ABSPATH')) exit;

define('WCW_VERSION', '0.5.0');
define('WCW_PLUGIN_FILE', __FILE__);
define('WCW_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('WCW_PLUGIN_URL', plugin_dir_url(__FILE__));

require_once WCW_PLUGIN_DIR . 'includes/class-wcw-db.php';
require_once WCW_PLUGIN_DIR . 'includes/class-wcw-closures.php';
require_once WCW_PLUGIN_DIR . 'includes/class-wcw-shortcode.php';
require_once WCW_PLUGIN_DIR . 'includes/class-wcw-admin-page.php';

register_activation_hook(__FILE__, function(){ WCW_DB::create_tables(); });
add_action('plugins_loaded', function(){ WCW_Shortcode::init(); if (is_admin()) WCW_Admin_Page::init(); });

add_action('wp_enqueue_scripts', function(){
  wp_enqueue_style('wcw-public', WCW_PLUGIN_URL . 'assets/public.css', [], WCW_VERSION);
});
add_action('admin_enqueue_scripts', function($hook){
  if ($hook === 'toplevel_page_wcw-calendar') {
    wp_enqueue_style('wcw-admin', WCW_PLUGIN_URL . 'assets/admin.css', [], WCW_VERSION);
  }
});
PHP

echo "-> Scrive: ${PLUGIN_SLUG}/includes/class-wcw-db.php"
cat > "${PLUGIN_SLUG}/includes/class-wcw-db.php" <<'PHP'
<?php
if (!class_exists('WCW_DB')):
class WCW_DB {
  public static function table_events(){ global $wpdb; return $wpdb->prefix.'wcw_events'; }
  public static function table_cats(){ global $wpdb; return $wpdb->prefix.'wcw_categories'; }

  public static function create_tables(){
    global $wpdb; $charset = $wpdb->get_charset_collate();
    $t_events = self::table_events();
    $t_cats   = self::table_cats();
    require_once ABSPATH.'wp-admin/includes/upgrade.php';

    $sql_cats = "CREATE TABLE $t_cats (
      id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
      name VARCHAR(80) NOT NULL,
      slug VARCHAR(80) NOT NULL UNIQUE,
      PRIMARY KEY (id)
    ) $charset;";

    $sql_events = "CREATE TABLE $t_events (
      id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
      name VARCHAR(120) NOT NULL,
      weekday TINYINT UNSIGNED NOT NULL,
      time TIME NOT NULL,
      category_id BIGINT UNSIGNED NULL,
      created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (id),
      KEY idx_day_time (weekday, time),
      KEY idx_cat (category_id)
    ) $charset;";

    dbDelta($sql_cats);
    dbDelta($sql_events);
  }

  // Categorie
  public static function get_categories(){
    global $wpdb; return $wpdb->get_results("SELECT * FROM ".self::table_cats()." ORDER BY name ASC");
  }
  public static function upsert_category($name){
    global $wpdb; $name = trim($name); if ($name==='') return false;
    $slug = sanitize_title($name);
    $t = self::table_cats();
    $exists = $wpdb->get_var($wpdb->prepare("SELECT id FROM $t WHERE slug=%s", $slug));
    if ($exists) return (int)$exists;
    $wpdb->insert($t, ['name'=>$name,'slug'=>$slug]);
    return (int)$wpdb->insert_id;
  }
  public static function delete_category($id){
    global $wpdb; $id = (int)$id; if(!$id) return false;
    $wpdb->update(self::table_events(), ['category_id'=>null], ['category_id'=>$id]);
    return (bool)$wpdb->delete(self::table_cats(), ['id'=>$id]);
  }

  // Eventi
  public static function get_events($category_slug = ''){
    global $wpdb; $t_e = self::table_events(); $t_c = self::table_cats();
    if ($category_slug) {
      $sql = $wpdb->prepare(
        "SELECT e.*, c.name AS category_name, c.slug AS category_slug
         FROM $t_e e LEFT JOIN $t_c c ON c.id=e.category_id
         WHERE c.slug=%s
         ORDER BY e.time ASC, e.weekday ASC, e.name ASC",
         sanitize_title($category_slug)
      );
    } else {
      $sql = "SELECT e.*, c.name AS category_name, c.slug AS category_slug
              FROM $t_e e LEFT JOIN $t_c c ON c.id=e.category_id
              ORDER BY e.time ASC, e.weekday ASC, e.name ASC";
    }
    return $wpdb->get_results($sql);
  }

  public static function insert_event($name,$weekday,$time,$category_id){
    global $wpdb; $weekday=(int)$weekday; $category_id = $category_id? (int)$category_id : null;
    return (bool)$wpdb->insert(self::table_events(), [
      'name'=>sanitize_text_field($name),
      'weekday'=>max(1,min(7,$weekday)),
      'time'=>preg_replace('/[^0-9:]/','',$time),
      'category_id'=>$category_id,
    ]);
  }

  public static function update_event($id,$name,$weekday,$time,$category_id){
    global $wpdb; $id=(int)$id; if(!$id) return false;
    return (bool)$wpdb->update(self::table_events(), [
      'name'=>sanitize_text_field($name),
      'weekday'=>max(1,min(7,(int)$weekday)),
      'time'=>preg_replace('/[^0-9:]/','',$time),
      'category_id'=>$category_id? (int)$category_id : null,
    ], ['id'=>$id]);
  }

  public static function delete_event($id){
    global $wpdb; return (bool)$wpdb->delete(self::table_events(), ['id'=>(int)$id]);
  }
}
endif;
PHP

echo "-> Scrive: ${PLUGIN_SLUG}/includes/class-wcw-closures.php"
cat > "${PLUGIN_SLUG}/includes/class-wcw-closures.php" <<'PHP'
<?php
if (!class_exists('WCW_Closures')):
class WCW_Closures {
  public static function is_closed_now(){
    if (!get_option('wcw_closure_enabled', 0)) return false;
    $start = get_option('wcw_closure_start', '');
    $end   = get_option('wcw_closure_end', '');
    if (!$start || !$end) return false;
    $tz = new DateTimeZone('Europe/Rome');
    $today = new DateTime('today', $tz);
    $s = DateTime::createFromFormat('Y-m-d', $start, $tz);
    $e = DateTime::createFromFormat('Y-m-d', $end, $tz);
    if (!$s || !$e) return false;
    return $today >= $s && $today <= $e;
  }
  public static function message_html(){
    $end = get_option('wcw_closure_end', '');
    $tpl = get_option('wcw_closure_message', 'Le attività riprenderanno il giorno {date}');
    if (!$end) return '';
    $tz = new DateTimeZone('Europe/Rome');
    $e = DateTime::createFromFormat('Y-m-d', $end, $tz);
    $months = [1=>'gennaio',2=>'febbraio',3=>'marzo',4=>'aprile',5=>'maggio',6=>'giugno',7=>'luglio',8=>'agosto',9=>'settembre',10=>'ottobre',11=>'novembre',12=>'dicembre'];
    $date_it = intval($e->format('j')) . ' ' . $months[intval($e->format('n'))] . ' ' . $e->format('Y');
    $msg = str_replace('{date}', $date_it, $tpl);
    return '<div class="wcw-closure-message">' . esc_html($msg) . '</div>';
  }
}
endif;
PHP

echo "-> Scrive: ${PLUGIN_SLUG}/includes/class-wcw-shortcode.php"
cat > "${PLUGIN_SLUG}/includes/class-wcw-shortcode.php" <<'PHP'
<?php
if (!class_exists('WCW_Shortcode')):
class WCW_Shortcode {

  public static function init(){
    add_shortcode('wcw_schedule', [__CLASS__, 'render']);
    add_action('wp_ajax_wpwcf_filter', [__CLASS__, 'ajax_filter']);
    add_action('wp_ajax_nopriv_wpwcf_filter', [__CLASS__, 'ajax_filter']);
  }

  public static function render($atts){
    $atts = shortcode_atts(['category' => ''], $atts, 'wcw_schedule');
    if (WCW_Closures::is_closed_now()) return WCW_Closures::message_html();

    $qs = isset($_GET['attivita']) ? sanitize_text_field(wp_unslash($_GET['attivita'])) : '';
    $current = $qs !== '' ? $qs : $atts['category'];

    $cats = WCW_DB::get_categories();
    ob_start(); ?>
    <div class="wpwc-wrap">

      <div class="wpwc-toolbar" role="tablist" aria-label="Filtra per attività">
        <a class="wpwc-chip<?php echo $current==='' ? ' is-active' : ''; ?>" href="#" data-wpwc-cat="">
          <span class="dot" style="background:#999"></span>
          Tutte le attività
        </a>
        <?php foreach ($cats as $c): ?>
          <a class="wpwc-chip<?php echo $current===$c->slug ? ' is-active' : ''; ?>" href="#" data-wpwc-cat="<?php echo esc_attr($c->slug); ?>">
            <span class="dot" style="background:#777777"></span>
            <?php echo esc_html($c->name); ?>
          </a>
        <?php endforeach; ?>
      </div>

      <div id="wpwc-grid">
        <?php echo self::render_grid_html($current); ?>
      </div>

    </div>

    <script>
    (function(){
      const wrap = document.currentScript.closest('.wpwc-wrap');
      const grid = wrap.querySelector('#wpwc-grid');
      const chips = wrap.querySelectorAll('.wpwc-chip');
      const ajaxUrl = "<?php echo esc_url(admin_url('admin-ajax.php')); ?>";

      function setActive(el){ chips.forEach(c => c.classList.remove('is-active')); el.classList.add('is-active'); }
      function updateURL(slug){
        const url = new URL(window.location);
        if (slug) url.searchParams.set('attivita', slug);
        else url.searchParams.delete('attivita');
        window.history.replaceState({}, '', url);
      }
      async function fetchGrid(slug){
        const fd = new FormData();
        fd.append('action','wpwcf_filter');
        fd.append('category', slug);
        const res = await fetch(ajaxUrl, { method:'POST', body: fd, credentials:'same-origin' });
        if (!res.ok) return;
        grid.innerHTML = await res.text();
      }
      chips.forEach(ch => ch.addEventListener('click', function(e){
        e.preventDefault();
        const slug = this.getAttribute('data-wpwc-cat') || '';
        setActive(this);
        updateURL(slug);
        fetchGrid(slug);
      }));
    })();
    </script>
    <?php
    return ob_get_clean();
  }

  private static function render_grid_html($category_slug = ''){
    $by = [1=>[],2=>[],3=>[],4=>[],5=>[],6=>[],7=>[]];
    $rows = WCW_DB::get_events($category_slug);

    foreach ($rows as $r) { $d = (int)$r->weekday; if ($d<1 || $d>7) continue; $by[$d][] = $r; }
    foreach ($by as $d=>&$items) { usort($items, fn($a,$b)=>strcmp($a->time,$b->time)); }
    unset($items);

    $labels = [1=>'Lunedì',2=>'Martedì',3=>'Mercoledì',4=>'Giovedì',5=>'Venerdì',6=>'Sabato',7=>'Domenica'];

    ob_start(); ?>
    <div class="wpwc-grid">
      <div class="wpwc-head">
        <?php for ($d=1; $d<=7; $d++): ?>
          <div class="wpwc-day"><?php echo esc_html($labels[$d]); ?></div>
        <?php endfor; ?>
      </div>
      <div class="wpwc-cols">
        <?php for ($d=1; $d<=7; $d++): ?>
          <div class="wpwc-cell" data-day="<?php echo (int)$d; ?>">
            <?php if (empty($by[$d])): ?>
              <!-- nessun evento -->
            <?php else: foreach ($by[$d] as $ev): ?>
              <div class="wpwc-event" data-cat="<?php echo esc_attr($ev->category_slug ?: ''); ?>">
                <div class="title"><?php echo esc_html($ev->name); ?></div>
                <div class="meta">
                  <?php echo esc_html(substr($ev->time,0,5)); ?>
                  <?php if (!empty($ev->category_name)): ?>
                    • <a href="<?php echo esc_url( home_url('/attivita/' . ($ev->category_slug ?? '')) ); ?>">
                      <?php echo esc_html($ev->category_name); ?>
                    </a>
                  <?php endif; ?>
                </div>
              </div>
            <?php endforeach; endif; ?>
          </div>
        <?php endfor; ?>
      </div>
    </div>
    <?php return ob_get_clean();
  }

  public static function ajax_filter(){
    $slug = isset($_POST['category']) ? sanitize_text_field(wp_unslash($_POST['category'])) : '';
    echo self::render_grid_html($slug);
    wp_die();
  }
}
endif;
PHP

echo "-> Scrive: ${PLUGIN_SLUG}/includes/class-wcw-admin-page.php"
cat > "${PLUGIN_SLUG}/includes/class-wcw-admin-page.php" <<'PHP'
<?php
if (!class_exists('WCW_Admin_Page')):
class WCW_Admin_Page {
  public static function init(){
    add_action('admin_menu', [__CLASS__, 'menu']);
    add_action('wp_ajax_wcw_save_event',   [__CLASS__, 'ajax_save_event']);
    add_action('wp_ajax_wcw_delete_event', [__CLASS__, 'ajax_delete_event']);
    add_action('wp_ajax_wcw_add_cat',      [__CLASS__, 'ajax_add_cat']);
    add_action('wp_ajax_wcw_delete_cat',   [__CLASS__, 'ajax_delete_cat']);
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
    $cat  = intval($_POST['category_id'] ?? 0) ?: null;
    if ($name==='' || $time==='') wp_send_json_error(['message'=>'Dati mancanti'], 400);
    $ok = $id ? WCW_DB::update_event($id,$name,$day,$time,$cat) : WCW_DB::insert_event($name,$day,$time,$cat);
    $ok ? wp_send_json_success() : wp_send_json_error(['message'=>'Errore DB'], 500);
  }
  public static function ajax_delete_event(){ self::check_caps_and_nonce(); $id=intval($_POST['id']??0); if(!$id) wp_send_json_error(); $ok=WCW_DB::delete_event($id); $ok? wp_send_json_success(): wp_send_json_error(['message'=>'Errore DB'],500);}
  public static function ajax_add_cat(){ self::check_caps_and_nonce(); $name=sanitize_text_field($_POST['name']??''); $id=WCW_DB::upsert_category($name); if($id) wp_send_json_success(['id'=>$id]); else wp_send_json_error(['message'=>'Errore categoria'],400);}
  public static function ajax_delete_cat(){ self::check_caps_and_nonce(); $id=intval($_POST['id']??0); $ok=WCW_DB::delete_category($id); $ok? wp_send_json_success(): wp_send_json_error(['message'=>'Errore DB'],500);}

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
    $cats = WCW_DB::get_categories();
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
          </form>

          <h3>Categorie</h3>
          <form id="wcw-cat-form" onsubmit="return false;">
            <input type="text" name="name" placeholder="Nome categoria">
            <button class="button" id="wcw-add-cat">Aggiungi</button>
          </form>
          <ul id="wcw-cat-list">
            <?php foreach ($cats as $c): ?>
              <li data-id="<?php echo intval($c->id); ?>"><?php echo esc_html($c->name); ?> <a href="#" class="wcw-del-cat">Elimina</a></li>
            <?php endforeach; ?>
          </ul>
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

      $('#wcw-save').addEventListener('click', async function(){
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

      $('#wcw-add-cat').addEventListener('click', async function(){
        const inp = document.querySelector('#wcw-cat-form input[name="name"]'); const name = inp.value.trim(); if(!name) return;
        const fd = new FormData(); fd.append('action','wcw_add_cat'); fd.append('nonce',nonce); fd.append('name',name);
        const res = await fetch(ajaxurl,{method:'POST',body:fd}); const json = await res.json();
        if(json.success){ location.reload(); } else { alert(json.data?.message||'Errore'); }
      });

      $$('#wcw-cat-list .wcw-del-cat').forEach(a=>a.addEventListener('click', async function(e){
        e.preventDefault(); if(!confirm('Eliminare la categoria?')) return;
        const li = this.closest('li'); const id = li.dataset.id;
        const fd = new FormData(); fd.append('action','wcw_delete_cat'); fd.append('nonce',nonce); fd.append('id',id);
        const res = await fetch(ajaxurl,{method:'POST',body:fd}); const json = await res.json();
        if(json.success){ li.remove(); } else { alert(json.data?.message||'Errore'); }
      }));
    })();
    </script>
    <?php
  }

  private static function day_label($d){ $map = [1=>'Lunedì',2=>'Martedì',3=>'Mercoledì',4=>'Giovedì',5=>'Venerdì',6=>'Sabato',7=>'Domenica']; return $map[$d] ?? ''; }
}
endif;
PHP

echo "-> Scrive: ${PLUGIN_SLUG}/uninstall.php"
cat > "${PLUGIN_SLUG}/uninstall.php" <<'PHP'
<?php
// Mantiene i dati in tabella. Pulisce solo le opzioni.
if (defined('WP_UNINSTALL_PLUGIN')) {
  delete_option('wcw_closure_enabled');
  delete_option('wcw_closure_start');
  delete_option('wcw_closure_end');
  delete_option('wcw_closure_message');
}
PHP

echo "-> Scrive: ${PLUGIN_SLUG}/assets/public.css"
cat > "${PLUGIN_SLUG}/assets/public.css" <<'CSS'
/* Griglia: 7 colonne (Lun..Dom). Nessuna colonna orari. */
.wpwc-toolbar{display:flex;flex-wrap:wrap;gap:8px;margin-bottom:12px}
.wpwc-chip{display:inline-flex;align-items:center;gap:6px;padding:6px 10px;border:1px solid #e5e7eb;border-radius:999px;background:#fff;text-decoration:none}
.wpwc-chip.is-active{border-color:#cbd5e1;background:#f8fafc}
.wpwc-chip .dot{width:10px;height:10px;border-radius:50%;display:inline-block}

.wpwc-grid{display:grid;gap:12px}
.wpwc-head{display:grid;grid-template-columns:repeat(7,minmax(0,1fr));gap:12px}
.wpwc-day{font-weight:600;padding:6px 0}
.wpwc-cols{display:grid;grid-template-columns:repeat(7,minmax(0,1fr));gap:12px}
.wpwc-cell{min-height:20px}

.wpwc-event{border:1px solid #e5e7eb;background:#fff;border-radius:8px;padding:10px;margin-bottom:8px}
.wpwc-event .title{font-weight:600}
.wpwc-event .meta{font-size:.9em;opacity:.85;margin-top:2px}

/* Responsive */
@media (max-width:1024px){.wpwc-head,.wpwc-cols{grid-template-columns:repeat(4,minmax(0,1fr))}}
@media (max-width:640px){.wpwc-head,.wpwc-cols{grid-template-columns:repeat(2,minmax(0,1fr))}}
CSS

echo "-> Scrive: ${PLUGIN_SLUG}/assets/admin.css"
cat > "${PLUGIN_SLUG}/assets/admin.css" <<'CSS'
.wcw-grid-admin{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:24px}
@media (max-width:1100px){.wcw-grid-admin{grid-template-columns:1fr}}
.wcw-form label{display:inline-block;min-width:140px}
ul#wcw-cat-list{margin:6px 0 0 1em;list-style:disc}
CSS

echo "-> Completato."
echo "Copia '${PLUGIN_SLUG}' in wp-content/plugins, poi attiva il plugin da WP."

