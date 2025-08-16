<?php
if (!class_exists('WCW_DB')):
class WCW_DB {
  public static function table_events(){ global $wpdb; return $wpdb->prefix.'wcw_events'; }

  public static function create_tables(){
    global $wpdb; $charset = $wpdb->get_charset_collate();
    $t = self::table_events();
    require_once ABSPATH.'wp-admin/includes/upgrade.php';
    $sql = "CREATE TABLE $t (
      id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
      name VARCHAR(120) NOT NULL,
      weekday TINYINT UNSIGNED NOT NULL,
      time TIME NOT NULL,
      category_id BIGINT UNSIGNED NULL, -- ID del post CPT 'attivita'
      created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (id),
      KEY idx_day_time (weekday, time),
      KEY idx_cat (category_id)
    ) $charset;";
    dbDelta($sql);
  }

  // Categorie dal CPT 'attivita' + ACF 'colore' (per admin select)
  public static function get_categories_all(){
    global $wpdb; $p=$wpdb->posts; $pm=$wpdb->postmeta;
    $sql = $wpdb->prepare(
      "SELECT p.ID id, p.post_title name, p.post_name slug, m.meta_value color
         FROM $p p
    LEFT JOIN $pm m ON (m.post_id=p.ID AND m.meta_key=%s)
        WHERE p.post_type=%s AND p.post_status='publish'
     ORDER BY p.post_title ASC",
      'colore','attivita'
    );
    return $wpdb->get_results($sql);
  }

  // Solo attività con almeno un evento (per chip frontend)
  public static function get_filter_categories(){
    global $wpdb; $p=$wpdb->posts; $pm=$wpdb->postmeta; $e=self::table_events();
    $sql = $wpdb->prepare(
      "SELECT p.ID id, p.post_title name, p.post_name slug, m.meta_value color, COUNT(ev.id) total_events
         FROM $p p
    LEFT JOIN $pm m  ON (m.post_id=p.ID AND m.meta_key=%s)
    LEFT JOIN $e  ev ON (ev.category_id=p.ID)
        WHERE p.post_type=%s AND p.post_status='publish'
     GROUP BY p.ID, p.post_title, p.post_name, m.meta_value
       HAVING total_events > 0
     ORDER BY p.post_title ASC",
      'colore','attivita'
    );
    return $wpdb->get_results($sql);
  }

  // Alias per eventuali vecchi riferimenti
  public static function get_categories(){ return self::get_filter_categories(); }

  // Eventi con join su CPT 'attivita' + colore ACF
  public static function get_events($category_slug = ''){
    global $wpdb; $t=self::table_events(); $p=$wpdb->posts; $pm=$wpdb->postmeta;
    if ($category_slug) {
      $sql = $wpdb->prepare(
        "SELECT e.*, p.post_title category_name, p.post_name category_slug, m.meta_value category_color
           FROM $t e
      LEFT JOIN $p  p  ON (p.ID=e.category_id AND p.post_type=%s AND p.post_status='publish')
      LEFT JOIN $pm m  ON (m.post_id=p.ID AND m.meta_key=%s)
          WHERE p.post_name=%s
       ORDER BY e.weekday ASC, e.time ASC, e.name ASC",
        'attivita','colore',sanitize_title($category_slug)
      );
    } else {
      $sql =
        "SELECT e.*, p.post_title category_name, p.post_name category_slug, m.meta_value category_color
           FROM $t e
      LEFT JOIN $p  p  ON (p.ID=e.category_id AND p.post_type='attivita' AND p.post_status='publish')
      LEFT JOIN $pm m  ON (m.post_id=p.ID AND m.meta_key='colore')
       ORDER BY e.weekday ASC, e.time ASC, e.name ASC";
    }
    return $wpdb->get_results($sql);
  }

  // CRUD
  public static function insert_event($name,$weekday,$time,$category_id){
    global $wpdb;
    return (bool)$wpdb->insert(self::table_events(), [
      'name'        => sanitize_text_field($name),
      'weekday'     => max(1,min(7,(int)$weekday)),
      'time'        => preg_replace('/[^0-9:]/','',$time),
      'category_id' => $category_id ? (int)$category_id : null,
    ]);
  }
  public static function update_event($id,$name,$weekday,$time,$category_id){
    global $wpdb; $id=(int)$id; if(!$id) return false;
    return (bool)$wpdb->update(self::table_events(), [
      'name'        => sanitize_text_field($name),
      'weekday'     => max(1,min(7,(int)$weekday)),
      'time'        => preg_replace('/[^0-9:]/','',$time),
      'category_id' => $category_id ? (int)$category_id : null,
    ], ['id'=>$id]);
  }
  public static function delete_event($id){
    global $wpdb; return (bool)$wpdb->delete(self::table_events(), ['id'=>(int)$id]);
  }
}
endif;
