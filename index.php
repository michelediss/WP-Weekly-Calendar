<?php
/**
 * Plugin Name: WP Weekly Calendar (CPT + Tassonomia)
 * Description: Calendario settimanale lunedì–giovedì con eventi filtrabili via tassonomia personalizzata e shortcode.
 * Version: 0.2.0
 * Author: Tu
 * Text Domain: wcw
 */

if (!defined('ABSPATH')) exit;

// -------------------------------------------------
// Autoload minimale
// -------------------------------------------------
require_once __DIR__ . '/includes/class-wcw-cpt.php';
require_once __DIR__ . '/includes/class-wcw-closures.php';
require_once __DIR__ + '/includes/class-wcw-shortcode.php';
require_once __DIR__ . '/includes/class-wcw-admin-page.php';
require_once __DIR__ . '/includes/class-wcw-migration.php';

// -------------------------------------------------
// Bootstrap
// -------------------------------------------------
add_action('init', function(){
  WCW_CPT::register();
});

add_action('plugins_loaded', function(){
  // Shortcode e assets pubblici
  WCW_Shortcode::init();
  // Admin
  if (is_admin()) {
    WCW_Admin_Page::init();
    WCW_Migration::maybe_admin_notice();
  }
});

register_activation_hook(__FILE__, function(){
  WCW_CPT::register();
  flush_rewrite_rules();
});

register_deactivation_hook(__FILE__, function(){
  flush_rewrite_rules();
});

// -------------------------------------------------
// Assets
// -------------------------------------------------
add_action('wp_enqueue_scripts', function(){
  wp_enqueue_style('wcw-public', plugins_url('assets/public.css', __FILE__), [], '0.2.0');
});

add_action('admin_enqueue_scripts', function($hook){
  if ($hook === 'toplevel_page_wcw-calendar') {
    wp_enqueue_style('wcw-admin', plugins_url('assets/admin.css', __FILE__), [], '0.2.0');
  }
});

// =================================================
// File: includes/class-wcw-cpt.php
// =================================================

if (!class_exists('WCW_CPT')):
class WCW_CPT {
  const POST_TYPE = 'wcw_event';
  const TAXONOMY  = 'wcw_category';
  const META_WEEKDAY = '_wcw_weekday'; // 1..4
  const META_TIME    = '_wcw_time';    // HH:MM

  public static function register(){
    register_post_type(self::POST_TYPE, [
      'label' => __('Eventi', 'wcw'),
      'public' => false,
      'show_ui' => true,
      'show_in_menu' => false, // usiamo la nostra pagina admin
      'supports' => ['title'],
      'capability_type' => 'post',
      'map_meta_cap' => true,
    ]);

    register_taxonomy(self::TAXONOMY, self::POST_TYPE, [
      'label' => __('Categorie', 'wcw'),
      'public' => false,
      'show_ui' => true,
      'hierarchical' => true,
      'show_admin_column' => true,
      'rewrite' => false,
    ]);
  }

  public static function day_label($d){
    $map = [1=>'Lunedì',2=>'Martedì',3=>'Mercoledì',4=>'Giovedì'];
    return isset($map[$d]) ? $map[$d] : '';
  }
}
endif;

// =================================================
// File: includes/class-wcw-closures.php
// =================================================

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

// =================================================
// File: includes/class-wcw-shortcode.php
// =================================================

if (!class_exists('WCW_Shortcode')):
class WCW_Shortcode {
  public static function init(){
    add_shortcode('wcw_schedule', [__CLASS__, 'render_shortcode']);
  }

  public static function render_shortcode($atts){
    $atts = shortcode_atts(['category' => ''], $atts, 'wcw_schedule');

    if (WCW_Closures::is_closed_now()) {
      return WCW_Closures::message_html();
    }

    return self::render_table_html($atts['category']);
  }

  public static function render_table_html($category_slug = ''){
    $args = [
      'post_type' => WCW_CPT::POST_TYPE,
      'post_status' => 'publish',
      'posts_per_page' => -1,
      'meta_key' => WCW_CPT::META_TIME,
      'orderby' => 'meta_value',
      'order' => 'ASC',
      'meta_query' => [
        [
          'key' => WCW_CPT::META_WEEKDAY,
          'compare' => 'EXISTS',
        ],
        [
          'key' => WCW_CPT::META_TIME,
          'compare' => 'EXISTS',
        ],
      ],
    ];

    if ($category_slug) {
      $args['tax_query'] = [[
        'taxonomy' => WCW_CPT::TAXONOMY,
        'field' => 'slug',
        'terms' => sanitize_title($category_slug),
      ]];
    }

    $q = new WP_Query($args);

    // Raccogli orari unici e bucket per giorno
    $times = [];
    $by = [1=>[],2=>[],3=>[],4=>[]];

    foreach ($q->posts as $p) {
      $day  = intval(get_post_meta($p->ID, WCW_CPT::META_WEEKDAY, true));
      $time = get_post_meta($p->ID, WCW_CPT::META_TIME, true);
      if ($day < 1 || $day > 4 || empty($time)) continue;
      $times[$time] = true;
      $by[$day][$time][] = $p;
    }

    $times = array_keys($times);
    sort($times, SORT_STRING);

    ob_start();
    ?>
    <table class="wcw-table">
      <thead>
        <tr>
          <th>Orario</th>
          <th>Lunedì</th><th>Martedì</th><th>Mercoledì</th><th>Giovedì</th>
        </tr>
      </thead>
      <tbody>
      <?php if (empty($times)): ?>
        <tr><td colspan="5">Nessun evento</td></tr>
      <?php else: ?>
        <?php foreach ($times as $t): ?>
          <tr>
            <td><?php echo esc_html(substr($t,0,5)); ?></td>
            <?php for ($d=1; $d<=4; $d++): ?>
              <td>
                <?php if (!empty($by[$d][$t])): ?>
                  <?php foreach ($by[$d][$t] as $post): ?>
                    <div class="wcw-event">
                      <span class="wcw-name"><?php echo esc_html(get_the_title($post)); ?></span>
                      <?php $terms = get_the_terms($post, WCW_CPT::TAXONOMY); ?>
                      <?php if ($terms && !is_wp_error($terms)): ?>
                        <small class="wcw-cat"><?php echo esc_html($terms[0]->name); ?></small>
                      <?php endif; ?>
                    </div>
                  <?php endforeach; ?>
                <?php endif; ?>
              </td>
            <?php endfor; ?>
          </tr>
        <?php endforeach; ?>
      <?php endif; ?>
      </tbody>
    </table>
    <?php
    return ob_get_clean();
  }
}
endif;

// =================================================
// File: includes/class-wcw-admin-page.php
// =================================================

if (!class_exists('WCW_Admin_Page')):
class WCW_Admin_Page {
  public static function init(){
    add_action('admin_menu', [__CLASS__, 'menu']);
  }

  public static function menu(){
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

  public static function handle_post(){
    if (!isset($_POST['wcw_action'])) return;
    if (!current_user_can('manage_options')) return;
    if (!check_admin_referer('wcw_nonce_action', 'wcw_nonce')) return;

    $action = sanitize_text_field($_POST['wcw_action']);

    if ($action === 'save_event') {
      $post_id = intval($_POST['id'] ?? 0);
      $title = sanitize_text_field($_POST['name'] ?? '');
      $weekday = max(1, min(4, intval($_POST['weekday'] ?? 1)));
      $time = preg_replace('/[^0-9:]/', '', $_POST['time'] ?? '00:00');
      $term_id = intval($_POST['category_term'] ?? 0);

      $postarr = [
        'post_type' => WCW_CPT::POST_TYPE,
        'post_status' => 'publish',
        'post_title' => $title,
      ];

      if ($post_id) {
        $postarr['ID'] = $post_id;
        wp_update_post($postarr);
      } else {
        $post_id = wp_insert_post($postarr);
      }

      if ($post_id && !is_wp_error($post_id)) {
        update_post_meta($post_id, WCW_CPT::META_WEEKDAY, $weekday);
        update_post_meta($post_id, WCW_CPT::META_TIME, $time);
        if ($term_id) {
          wp_set_object_terms($post_id, [$term_id], WCW_CPT::TAXONOMY, false);
        } else {
          wp_set_object_terms($post_id, [], WCW_CPT::TAXONOMY, false);
        }
      }
    }

    if ($action === 'delete_event' && !empty($_POST['id'])) {
      wp_delete_post(intval($_POST['id']), true);
    }

    if ($action === 'save_closure') {
      update_option('wcw_closure_enabled', isset($_POST['closure_enabled']) ? 1 : 0);
      update_option('wcw_closure_start', sanitize_text_field($_POST['closure_start'] ?? ''));
      update_option('wcw_closure_end', sanitize_text_field($_POST['closure_end'] ?? ''));
      update_option('wcw_closure_message', sanitize_text_field($_POST['closure_message'] ?? 'Le attività riprenderanno il giorno {date}'));
    }

    if ($action === 'add_term') {
      $new_term = sanitize_text_field($_POST['new_term'] ?? '');
      if ($new_term) {
        wp_insert_term($new_term, WCW_CPT::TAXONOMY);
      }
    }
  }

  public static function render_page(){
    if (!current_user_can('manage_options')) return;
    self::handle_post();

    $events = get_posts([
      'post_type' => WCW_CPT::POST_TYPE,
      'post_status' => 'publish',
      'numberposts' => -1,
      'orderby' => 'meta_value',
      'meta_key' => WCW_CPT::META_TIME,
      'order' => 'ASC',
    ]);

    $terms = get_terms([
      'taxonomy' => WCW_CPT::TAXONOMY,
      'hide_empty' => false,
    ]);

    ?>
    <div class="wrap">
      <h1>Calendario settimanale</h1>

      <h2 class="title">Nuovo evento</h2>
      <form method="post" class="wcw-form">
        <?php wp_nonce_field('wcw_nonce_action', 'wcw_nonce'); ?>
        <input type="hidden" name="wcw_action" value="save_event">
        <p><label>Nome <input type="text" name="name" required></label></p>
        <p><label>Giorno
          <select name="weekday">
            <option value="1">Lunedì</option>
            <option value="2">Martedì</option>
            <option value="3">Mercoledì</option>
            <option value="4">Giovedì</option>
          </select>
        </label></p>
        <p><label>Orario <input type="time" name="time" required></label></p>
        <p><label>Categoria
          <select name="category_term">
            <option value="0">— nessuna —</option>
            <?php foreach ($terms as $t): ?>
              <option value="<?php echo intval($t->term_id); ?>"><?php echo esc_html($t->name); ?></option>
            <?php endforeach; ?>
          </select>
        </label></p>
        <p><button class="button button-primary" type="submit">Salva</button></p>
      </form>

      <form method="post" class="wcw-inline">
        <?php wp_nonce_field('wcw_nonce_action', 'wcw_nonce'); ?>
        <input type="hidden" name="wcw_action" value="add_term">
        <label>Nuova categoria <input type="text" name="new_term" placeholder="Nome categoria"></label>
        <button class="button" type="submit">Aggiungi</button>
        <a class="button-link" href="<?php echo admin_url('edit-tags.php?taxonomy='.WCW_CPT::TAXONOMY.'&post_type='.WCW_CPT::POST_TYPE); ?>">Gestisci categorie</a>
      </form>

      <h2>Periodo di chiusura</h2>
      <form method="post" class="wcw-form">
        <?php wp_nonce_field('wcw_nonce_action', 'wcw_nonce'); ?>
        <input type="hidden" name="wcw_action" value="save_closure">
        <?php $enabled = (bool) get_option('wcw_closure_enabled', 0);
              $start = get_option('wcw_closure_start', '');
              $end   = get_option('wcw_closure_end', '');
              $msg   = get_option('wcw_closure_message', 'Le attività riprenderanno il giorno {date}');
        ?>
        <p><label><input type="checkbox" name="closure_enabled" <?php checked($enabled); ?>> Abilita chiusura</label></p>
        <p><label>Dal <input type="date" name="closure_start" value="<?php echo esc_attr($start); ?>"></label></p>
        <p><label>Al <input type="date" name="closure_end" value="<?php echo esc_attr($end); ?>"></label></p>
        <p><label>Messaggio <input type="text" name="closure_message" size="60" value="<?php echo esc_attr($msg); ?>"></label></p>
        <p><small>Usa {date} per inserire la data di riapertura.</small></p>
        <p><button class="button" type="submit">Salva impostazioni</button></p>
      </form>

      <hr>
      <h2>Eventi</h2>
      <table class="widefat">
        <thead><tr>
          <th>Nome</th><th>Giorno</th><th>Orario</th><th>Categoria</th><th>Azione</th>
        </tr></thead>
        <tbody>
        <?php if (empty($events)): ?>
          <tr><td colspan="5">Nessun evento</td></tr>
        <?php else: foreach ($events as $e): ?>
          <?php $weekday = intval(get_post_meta($e->ID, WCW_CPT::META_WEEKDAY, true));
                $time = get_post_meta($e->ID, WCW_CPT::META_TIME, true);
                $terms_e = get_the_terms($e, WCW_CPT::TAXONOMY);
                $term_name = ($terms_e && !is_wp_error($terms_e)) ? $terms_e[0]->name : '';
          ?>
          <tr>
            <td><?php echo esc_html($e->post_title); ?></td>
            <td><?php echo esc_html(WCW_CPT::day_label($weekday)); ?></td>
            <td><?php echo esc_html(substr($time,0,5)); ?></td>
            <td><?php echo esc_html($term_name); ?></td>
            <td>
              <form method="post" style="display:inline">
                <?php wp_nonce_field('wcw_nonce_action', 'wcw_nonce'); ?>
                <input type="hidden" name="wcw_action" value="delete_event">
                <input type="hidden" name="id" value="<?php echo intval($e->ID); ?>">
                <button class="button-link-delete" type="submit">Elimina</button>
              </form>
              <a href="<?php echo get_edit_post_link($e->ID); ?>">Modifica</a>
            </td>
          </tr>
        <?php endforeach; endif; ?>
        </tbody>
      </table>

      <h2>Anteprima</h2>
      <div class="wcw-preview">
        <?php echo WCW_Shortcode::render_table_html(''); ?>
      </div>
    </div>
    <?php
  }
}
endif;

// =================================================
// File: includes/class-wcw-migration.php
// =================================================

if (!class_exists('WCW_Migration')):
class WCW_Migration {
  private static function old_table(){
    global $wpdb; return $wpdb->prefix . 'wcw_events';
  }

  public static function old_table_exists(){
    global $wpdb; $table = self::old_table();
    return $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table)) === $table;
  }

  public static function maybe_admin_notice(){
    if (!current_user_can('manage_options')) return;
    if (!self::old_table_exists()) return;
    add_action('admin_notices', function(){
      echo '<div class="notice notice-warning"><p>È stata rilevata la vecchia tabella eventi. <a href="'.esc_url(admin_url('admin.php?page=wcw-calendar&migrate=1')).'">Migra ora al nuovo CPT</a>.</p></div>';
    });

    if (isset($_GET['page'], $_GET['migrate']) && $_GET['page']==='wcw-calendar' && $_GET['migrate']=='1') {
      self::run_migration();
    }
  }

  public static function run_migration(){
    if (!self::old_table_exists()) return;
    global $wpdb; $table = self::old_table();
    $rows = $wpdb->get_results("SELECT * FROM $table ORDER BY weekday, time, name");
    foreach ($rows as $r) {
      $post_id = wp_insert_post([
        'post_type' => WCW_CPT::POST_TYPE,
        'post_status' => 'publish',
        'post_title' => $r->name,
      ]);
      if (is_wp_error($post_id)) continue;
      update_post_meta($post_id, WCW_CPT::META_WEEKDAY, intval($r->weekday));
      update_post_meta($post_id, WCW_CPT::META_TIME, substr($r->time,0,5));
      if (!empty($r->category)) {
        $term = term_exists($r->category, WCW_CPT::TAXONOMY);
        if (!$term) $term = wp_insert_term($r->category, WCW_CPT::TAXONOMY);
        if (!is_wp_error($term)) {
          $term_id = is_array($term) ? $term['term_id'] : $term; 
          wp_set_object_terms($post_id, [$term_id], WCW_CPT::TAXONOMY, false);
        }
      }
    }
    add_action('admin_notices', function(){
      echo '<div class="notice notice-success"><p>Migrazione completata.</p></div>';
    });
  }
}
endif;

// =================================================
// File: assets/public.css
// =================================================
/*
.wcw-table { width:100%; border-collapse:collapse }
.wcw-table th, .wcw-table td { border:1px solid #e5e7eb; padding:8px; vertical-align:top }
.wcw-event { margin-bottom:6px }
.wcw-name { display:block; font-weight:600 }
.wcw-cat { display:block; opacity:.75 }
.wcw-closure-message { padding:12px; background:#fff8e1; border:1px solid #ffe082 }
*/

// =================================================
// File: assets/admin.css
// =================================================
/*
.wcw-form label { display:inline-block; min-width:140px }
.wcw-inline { margin:8px 0 }
.wcw-preview { margin-top:12px }
*/
