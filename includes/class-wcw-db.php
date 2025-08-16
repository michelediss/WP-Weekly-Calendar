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
