<?php
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

    return self::render_columns_html($atts['category']);
  }

  public static function render_columns_html($category_slug = ''){
    $args = [
      'post_type'      => WCW_CPT::POST_TYPE,
      'post_status'    => 'publish',
      'posts_per_page' => -1,
      'meta_key'       => WCW_CPT::META_TIME,
      'orderby'        => 'meta_value',
      'order'          => 'ASC',
      'meta_query'     => [
        ['key' => WCW_CPT::META_WEEKDAY, 'compare' => 'EXISTS'],
        ['key' => WCW_CPT::META_TIME,    'compare' => 'EXISTS'],
      ],
      'no_found_rows'  => true,
    ];

    if ($category_slug) {
      $args['tax_query'] = [[
        'taxonomy' => WCW_CPT::TAXONOMY,
        'field'    => 'slug',
        'terms'    => sanitize_title($category_slug),
      ]];
    }

    $q  = new WP_Query($args);

    // Bucket: 1..4 = Lun..Gio
    $by = [1=>[],2=>[],3=>[],4=>[]];

    foreach ($q->posts as $p) {
      $day  = (int) get_post_meta($p->ID, WCW_CPT::META_WEEKDAY, true);
      $time =        get_post_meta($p->ID, WCW_CPT::META_TIME,    true);
      if ($day < 1 || $day > 4 || empty($time)) continue;
      $by[$day][] = ['post' => $p, 'time' => $time];
    }

    // Ordina ogni giorno per orario
    foreach ($by as $d => &$events) {
      usort($events, function($a,$b){ return strcmp($a['time'], $b['time']); });
    }
    unset($events);

    // Nessun evento? Messaggio semplice
    $has_any = false;
    foreach ($by as $events) { if (!empty($events)) { $has_any = true; break; } }

    ob_start();
    if (!$has_any) {
      echo '<div class="wcw-empty">Nessun evento</div>';
      return ob_get_clean();
    }
    ?>
    <div class="wcw-grid">
      <?php for ($d=1; $d<=4; $d++): ?>
        <div class="wcw-day">
          <div class="wcw-day-title"><?php echo esc_html(WCW_CPT::day_label($d)); ?></div>
          <?php foreach ($by[$d] as $item):
            /** @var WP_Post $post */
            $post = $item['post'];
            $time = $item['time'];
            $terms = get_the_terms($post, WCW_CPT::TAXONOMY);
            ?>
            <div class="wcw-card">
              <div class="wcw-card-line">
                <span class="wcw-card-time"><?php echo esc_html(substr($time,0,5)); ?></span>
                <span class="wcw-card-title"><?php echo esc_html(get_the_title($post)); ?></span>
              </div>
              <?php if ($terms && !is_wp_error($terms)): ?>
                <div class="wcw-card-cat"><?php echo esc_html($terms[0]->name); ?></div>
              <?php endif; ?>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endfor; ?>
    </div>
    <?php
    return ob_get_clean();
  }
}
endif;
