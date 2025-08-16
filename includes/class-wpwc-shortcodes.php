<?php
namespace WPWC;

if ( ! defined( 'ABSPATH' ) ) exit;

class Shortcodes {
	public static function hooks() {
		add_shortcode( 'weekly_calendar', [ __CLASS__, 'render' ] );
	}
	public static function render( $atts = [] ) {
		wp_enqueue_style( 'wpwc-calendar' );
		wp_enqueue_script( 'wpwc-calendar' );
		ob_start();
		Template_Loader::template( 'calendar.php', [
			'activities' => Activities::all(),
			'days'       => week_days(),
		] );
		return ob_get_clean();
	}
}
