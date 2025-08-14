<?php
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
