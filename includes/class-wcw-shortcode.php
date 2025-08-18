<?php
if ( ! class_exists( 'WCW_Shortcode' ) ) :
class WCW_Shortcode {

  public static function init() {
    add_shortcode( 'wcw_schedule', [ __CLASS__, 'render' ] );
    add_shortcode( 'weekly_calendar', [ __CLASS__, 'render' ] ); // alias retrocompatibile
  }

  /**
   * Shortcode [wcw_schedule]
   *
   * Attributi:
   * - category            => slug della categoria (CPT: attivita) da filtrare (default: '')
   * - filters             => '1' per mostrare i chip filtri, '0' per nasconderli (default: '1')
   * - show_when_closed    => '1' per mostrare calendario ANCHE se chiuso (con banner),
   *                          '0' per mostrare SOLO il messaggio di chiusura (default: '0')
   */
  public static function render( $atts ) {
    $atts = shortcode_atts( [
      'category'         => '',
      'filters'          => '1',
      'show_when_closed' => '0',
    ], $atts, 'wcw_schedule' );

    $category_slug   = sanitize_title( $atts['category'] );
    $show_filters    = $atts['filters'] === '1';

    // Stato chiusura
    $has_closures = class_exists( 'WCW_Closures' );
    $is_closed    = $has_closures ? WCW_Closures::is_closed_now() : false;
    $closure_html = ( $has_closures && $is_closed ) ? WCW_Closures::message_html() : '';

    // Se chiuso e NON si vuole mostrare il calendario, ritorno solo il messaggio
    if ( $is_closed && $atts['show_when_closed'] !== '1' ) {
      return $closure_html;
    }

    // Dati
    $rows = WCW_DB::get_events( $category_slug );
    $cats = $show_filters ? WCW_DB::get_filter_categories() : [];

    // Raggruppo per giorno (1=Lun ... 7=Dom)
    $by_day = [ 1 => [], 2 => [], 3 => [], 4 => [], 5 => [], 6 => [], 7 => [] ];
    foreach ( $rows as $r ) {
      $d = (int) $r->weekday;
      if ( $d < 1 || $d > 7 ) continue;
      $by_day[ $d ][] = $r;
    }
    foreach ( $by_day as $d => &$items ) {
      usort( $items, function ( $a, $b ) {
        $c = strcmp( $a->time, $b->time );
        if ( $c !== 0 ) return $c;
        return strcmp( $a->name, $b->name );
      } );
    }
    unset( $items );

    // Render
    ob_start();
    ?>
    <div class="wpwc-schedule<?php echo $is_closed ? ' is-closed' : ''; ?>">

      <?php if ( $closure_html ) : ?>
        <div class="wpwc-closure">
          <?php echo $closure_html; // già sanificato nella classe WCW_Closures ?>
        </div>
      <?php endif; ?>

      <?php if ( $show_filters ) : ?>
        <div class="wpwc-filters">
          <a href="#"
             class="wpwc-chip <?php echo $category_slug === '' ? 'is-active' : ''; ?>"
             data-wpwc-cat="">
            <?php esc_html_e( 'Tutte', 'wcw' ); ?>
          </a>
          <?php foreach ( $cats as $c ) :
            $col = $c->color ? sanitize_hex_color( $c->color ) : '';
            ?>
            <a href="#"
               class="wpwc-chip <?php echo $category_slug === $c->slug ? 'is-active' : ''; ?>"
               data-wpwc-cat="<?php echo esc_attr( $c->slug ); ?>"
               <?php echo $col ? 'style="--chip:' . esc_attr( $col ) . ';"' : ''; ?>>
              <?php echo esc_html( $c->name ); ?>
            </a>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

      <div class="wpwc-grid">
        <?php
        $days = [ 1 => 'Lunedì', 2 => 'Martedì', 3 => 'Mercoledì', 4 => 'Giovedì', 5 => 'Venerdì', 6 => 'Sabato', 7 => 'Domenica' ];
        foreach ( $days as $idx => $label ) :
          $items = $by_day[ $idx ];
          ?>
          <section class="wpwc-day">
            <h3 class="wpwc-day-title"><?php echo esc_html( $label ); ?></h3>

            <?php if ( empty( $items ) ) : ?>
              <div class="wpwc-empty"><?php esc_html_e( 'Nessuna attività', 'wcw' ); ?></div>
            <?php else : ?>
              <ul class="wpwc-events">
                <?php foreach ( $items as $ev ) :
                  $col   = $ev->category_color ? sanitize_hex_color( $ev->category_color ) : '';
                  $start = $ev->time ? substr( $ev->time, 0, 5 ) : '';
                  $end   = $ev->time_end ? substr( $ev->time_end, 0, 5 ) : '';
                  ?>
                  <li class="wpwc-event" <?php echo $col ? 'style="--cat:' . esc_attr( $col ) . ';"' : ''; ?>>
                    <div class="wpwc-event-time">
                      <?php echo esc_html( $start . ( $end ? ' – ' . $end : '' ) ); ?>
                    </div>
                    <div class="wpwc-event-main">
                      <div class="wpwc-event-title"><?php echo esc_html( $ev->name ); ?></div>
                      <?php if ( ! empty( $ev->subtitle ) ) : ?>
                        <div class="wpwc-event-subtitle"><?php echo esc_html( $ev->subtitle ); ?></div>
                      <?php endif; ?>
                      <?php if ( ! empty( $ev->category_name ) ) : ?>
                        <div class="wpwc-event-chip" aria-hidden="true"><?php echo esc_html( $ev->category_name ); ?></div>
                      <?php endif; ?>
                    </div>
                  </li>
                <?php endforeach; ?>
              </ul>
            <?php endif; ?>
          </section>
        <?php endforeach; ?>
      </div>
    </div>

    <?php if ( $show_filters ) : ?>
    <script>
      (function(){
        const setActive = (el) => {
          document.querySelectorAll('.wpwc-chip').forEach(x => x.classList.remove('is-active'));
          if (el) el.classList.add('is-active');
        };
        const applyFilter = (slug) => {
          const base = window.location.href
            .replace(/([?&])category=[^&]*(&|$)/, '$1')
            .replace(/[?&]$/, '');
          const url = slug ? (base + (base.includes('?') ? '&' : '?') + 'category=' + encodeURIComponent(slug)) : base;
          window.location.href = url;
        };
        document.querySelectorAll('.wpwc-chip').forEach(chip => chip.addEventListener('click', function(e){
          e.preventDefault();
          const slug = this.getAttribute('data-wpwc-cat') || '';
          setActive(this);
          applyFilter(slug);
        }));
      })();
    </script>
    <?php endif;

    return ob_get_clean();
  }
}
endif;
