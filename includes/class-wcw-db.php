<?php
if (!class_exists('WCW_DB')):
class WCW_DB {
  public static function table_events(){ global $wpdb; return $wpdb->prefix.'wcw_events'; }

  public static function create_tables(){
    global $wpdb; $charset = $wpdb->get_charset_collate();
    $t_events = self::table_events();
    require_once ABSPATH.'wp-admin/includes/upgrade.php';

    $sql_events = "CREATE TABLE $t_events (
      id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
      name VARCHAR(120) NOT NULL,
      weekday TINYINT UNSIGNED NOT NULL,
      time TIME NOT NULL,
      category_id BIGINT UNSIGNED NULL, -- ID post CPT 'attivita'
      created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (id),
      KEY idx_day_time (weekday, time),
      KEY idx_cat (category_id)
    ) $charset;";

    dbDelta($sql_events);
  }

  // ---------- CATEGORIE (dal CPT attivita) ----------
  // Ritorna oggetti: id, name, slug, color
  public static function get_categories(){
    global $wpdb;
    $p = $wpdb->posts;
    $pm = $wpdb->postmeta;
    // colore ACF salvato come postmeta 'colore'
    $sql = $wpdb->prepare(
      "SELECT p.ID AS id, p.post_title AS name, p.post_name AS slug, pm.meta_value AS color
         FROM $p p
         LEFT JOIN $pm pm ON (pm.post_id=p.ID AND pm.meta_key=%s)
        WHERE p.post_type=%s AND p.post_status='publish'
        ORDER BY p.post_title ASC",
      'colore', 'attivita'
    );
    return $wpdb->get_results($sql);
  }

  // ---------- EVENTI ----------
  // Se $category_slug è non vuoto, filtra per post_name
  public static function get_events($category_slug = ''){
    global $wpdb;
    $t_e = self::table_events();
    $p   = $wpdb->posts;
    $pm  = $wpdb->postmeta;

    if ($category_slug) {
      $sql = $wpdb->prepare(
        "SELECT e.*,
                p.post_title AS category_name,
                p.post_name  AS category_slug,
                c.meta_value AS category_color
           FROM $t_e e
      LEFT JOIN $p  p  ON (p.ID=e.category_id AND p.post_type=%s AND p.post_status='publish')
      LEFT JOIN $pm c  ON (c.post_id=p.ID AND c.meta_key=%s)
          WHERE p.post_name=%s
       ORDER BY e.time ASC, e.weekday ASC, e.name ASC",
        'attivita', 'colore', sanitize_title($category_slug)
      );
    } else {
      $sql =
        "SELECT e.*,
                p.post_title AS category_name,
                p.post_name  AS category_slug,
                c.meta_value AS category_color
           FROM $t_e e
      LEFT JOIN $p  p  ON (p.ID=e.category_id AND p.post_type='attivita' AND p.post_status='publish')
      LEFT JOIN $pm c  ON (c.post_id=p.ID AND c.meta_key='colore')
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
