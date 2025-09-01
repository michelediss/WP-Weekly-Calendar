<?php
if ( ! class_exists( 'WCW_DB' ) ):
class WCW_DB {

  /* ===========================
   *  Low-level helpers
   * =========================== */
  private static function table(){
    global $wpdb;
    return $wpdb->prefix . 'wcw_events';
  }

  private static function clean_time($t){
    $t = is_string($t) ? trim($t) : '';
    if ($t === '' || $t === '00:00' || $t === '00:00:00' || $t === '0:00') return '';
    return substr($t, 0, 5);
  }

  /**
   * Ritorna il colore per un post 'attivita' leggendo il campo ACF 'colore'.
   * Fallback su get_post_meta('colore') se ACF non è disponibile.
   * Default: #777777
   */
  private static function get_activity_color($post_id){
    $color = '';
    if (function_exists('get_field')) {
      $color = get_field('colore', $post_id);
    } else {
      $color = get_post_meta($post_id, 'colore', true);
    }
    $color = is_string($color) ? trim($color) : '';
    $color = sanitize_hex_color($color);
    return $color ?: '#777777';
  }

  private static function enrich_event(&$r){
    // category_id è l'ID del CPT 'attivita'
    $cat_id = isset($r->category_id) ? (int)$r->category_id : 0;

    if ($cat_id > 0){
      $slug  = get_post_field('post_name', $cat_id) ?: '';
      $name  = get_the_title($cat_id) ?: '';
      $color = self::get_activity_color($cat_id);

      $r->category_slug  = $slug;
      $r->category_name  = $name;
      $r->category_color = $color;
    } else {
      $r->category_slug  = '';
      $r->category_name  = '';
      $r->category_color = '#777777';
    }

    // normalizza orari per uso frontend/admin
    $r->time     = self::clean_time($r->time);
    $r->time_end = self::clean_time($r->time_end);
  }

  /* ===========================
   *  Public API
   * =========================== */

  /** Ritorna un singolo evento (arricchito con dati categoria). */
  public static function get_event($id){
    global $wpdb;
    $sql = $wpdb->prepare("SELECT * FROM " . self::table() . " WHERE id = %d", (int)$id);
    $row = $wpdb->get_row($sql);
    if ($row) self::enrich_event($row);
    return $row;
  }

  /**
   * Ritorna eventi; se $category_slug è fornito, filtra post-enrichment.
   * $category_slug è lo slug del CPT 'attivita'.
   */
  public static function get_events($category_slug = ''){
    global $wpdb;
    $rows = $wpdb->get_results("SELECT * FROM " . self::table() . " ORDER BY weekday ASC, time ASC, id ASC");

    foreach ($rows as &$r) self::enrich_event($r);
    unset($r);

    if (is_string($category_slug) && $category_slug !== ''){
      $category_slug = sanitize_title($category_slug);
      $rows = array_values(array_filter($rows, function($r) use ($category_slug){
        return isset($r->category_slug) && $r->category_slug === $category_slug;
      }));
    }
    return $rows;
  }

  /** Inserisce un evento. Ritorna insert_id o false su errore. */
  public static function insert_event($name, $day, $time, $cat, $sub, $time_e){
    global $wpdb;
    $table = self::table();

    $data = [
      'name'        => (string)$name,
      'weekday'     => (int)$day,
      'time'        => (string)$time,
      'subtitle'    => (string)$sub,
      'time_end'    => ($time_e === '' ? null : (string)$time_e),
      'category_id' => (is_int($cat) && $cat > 0) ? (int)$cat : null,
    ];
    $format = ['%s','%d','%s','%s','%s','%d'];

    $res = $wpdb->insert($table, $data, $format);
    return ($res === false) ? false : (int)$wpdb->insert_id;
  }

  /**
   * Aggiorna un evento.
   * Ritorna false su errore, altrimenti il numero di righe (0 = nessun cambiamento).
   */
  public static function update_event($id, $name, $day, $time, $cat, $sub, $time_e){
    global $wpdb;
    $table = self::table();

    $data = [
      'name'     => (string)$name,
      'weekday'  => (int)$day,
      'time'     => (string)$time,
      'subtitle' => (string)$sub,
      'time_end' => ($time_e === '' ? null : (string)$time_e),
    ];
    $format = ['%s','%d','%s','%s','%s'];

    // Se $cat è un int >0, aggiorna; se null, non toccare; (mai passare 0)
    if (is_int($cat) && $cat > 0){
      $data['category_id'] = (int)$cat;
      $format[] = '%d';
    }

    $res = $wpdb->update($table, $data, ['id'=>(int)$id], $format, ['%d']);
    return ($res === false) ? false : (int)$res;
  }

  /** Elimina un evento. Ritorna numero righe eliminate o false. */
  public static function delete_event($id){
    global $wpdb;
    $table = self::table();
    $res = $wpdb->delete($table, ['id'=>(int)$id], ['%d']);
    return ($res === false) ? false : (int)$res;
  }

  /**
   * Ritorna l’elenco “categorie” per i filtri UI.
   * Piano A: usa direttamente i post pubblicati del CPT 'attivita'.
   * Oggetto: (id, slug, name, color) — color da ACF 'colore'
   */
  public static function get_filter_categories(){
    $posts = get_posts([
      'post_type'        => 'attivita',
      'post_status'      => 'publish',
      'posts_per_page'   => -1,
      'orderby'          => 'title',
      'order'            => 'ASC',
      'suppress_filters' => false,
    ]);

    $out = [];
    foreach ($posts as $p){
      $out[] = (object)[
        'id'    => (int)$p->ID,
        'slug'  => (string)$p->post_name,
        'name'  => (string)$p->post_title,
        'color' => (string) self::get_activity_color($p->ID),
      ];
    }
    return $out;
  }
}
endif;
