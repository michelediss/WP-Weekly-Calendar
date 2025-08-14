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
      'no_found_rows' => true,
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
