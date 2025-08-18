<?php
if ( ! class_exists( 'WCW_Closures' ) ) :
/**
 * Gestione periodi di chiusura e messaggi informativi.
 *
 * Opzioni:
 * - wcw_closure_enabled  (0|1)
 * - wcw_closure_start    (YYYY-MM-DD) opzionale
 * - wcw_closure_end      (YYYY-MM-DD) opzionale
 * - wcw_closure_message  (string) messaggio HTML/markup semplice
 */
class WCW_Closures {

  /** true se la chiusura è abilitata nelle opzioni */
  public static function is_enabled() {
    return (bool) get_option( 'wcw_closure_enabled', 0 );
  }

  /** [start, end] come stringhe YYYY-MM-DD (possono essere vuote) */
  public static function get_range() {
    $start = get_option( 'wcw_closure_start', '' );
    $end   = get_option( 'wcw_closure_end',   '' );
    return [ trim( $start ), trim( $end ) ];
  }

  /**
   * Ritorna true se "ora" ricade nel periodo di chiusura.
   * Regole:
   *  - se disabled => false
   *  - se start & end => start <= today <= end
   *  - se solo start => start <= today
   *  - se solo end   => today <= end
   *  - se nessuna data ma enabled => true (chiusura aperta a tempo indeterminato)
   */
  public static function is_closed_now() {
    if ( ! self::is_enabled() ) return false;

    $today = current_time( 'Y-m-d' );
    list( $start, $end ) = self::get_range();

    if ( $start && $end ) return ( $today >= $start && $today <= $end );
    if ( $start )         return ( $today >= $start );
    if ( $end )           return ( $today <= $end );
    return true;
  }

  /** Messaggio HTML da mostrare quando il calendario è chiuso */
  public static function message_html() {
    $default = __( "Le attività sono temporaneamente sospese.", "wcw" );
    $msg     = get_option( 'wcw_closure_message', '' );
    $msg     = $msg !== '' ? $msg : $default;

    list( $start, $end ) = self::get_range();
    $date_line = '';
    if ( $start || $end ) {
      $i18n = function( $d ) { return $d ? esc_html( date_i18n( 'j F Y', strtotime( $d . ' 00:00:00' ) ) ) : ''; };
      if ( $start && $end )   $date_line = sprintf( __( 'Dal %s al %s', 'wcw' ), $i18n( $start ), $i18n( $end ) );
      elseif ( $start )       $date_line = sprintf( __( 'Da %s', 'wcw' ), $i18n( $start ) );
      elseif ( $end )         $date_line = sprintf( __( 'Fino al %s', 'wcw' ), $i18n( $end ) );
    }

    ob_start(); ?>
    <div class="wpwc-closure-box">
      <div class="wpwc-closure-title"><?php esc_html_e( 'Comunicazione', 'wcw' ); ?></div>
      <?php if ( $date_line ) : ?>
        <div class="wpwc-closure-dates"><?php echo $date_line; ?></div>
      <?php endif; ?>
      <div class="wpwc-closure-message">
        <?php echo wpautop( wp_kses_post( $msg ) ); ?>
      </div>
    </div>
    <?php
    return ob_get_clean();
  }
}
endif;
