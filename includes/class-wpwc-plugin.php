<?php
namespace WPWC;

if ( ! defined( 'ABSPATH' ) ) exit;

require_once WPWC_DIR . 'includes/class-wpwc-post-types.php';
require_once WPWC_DIR . 'includes/class-wpwc-metaboxes.php';
require_once WPWC_DIR . 'includes/class-wpwc-activities.php';
require_once WPWC_DIR . 'includes/class-wpwc-rest.php';
require_once WPWC_DIR . 'includes/class-wpwc-shortcodes.php';
require_once WPWC_DIR . 'includes/template-loader.php';

final class Plugin {

	private static $instance = null;

	public static function instance(): self {
		if ( self::$instance === null ) self::$instance = new self();
		return self::$instance;
	}

	public function boot() {
		add_action( 'init', [ Post_Types::class, 'register' ] );
		add_action( 'init', [ $this, 'register_assets' ] );
		Metaboxes::hooks();
		REST::hooks();
		Shortcodes::hooks();
		Template_Loader::hooks();
	}

	public function register_assets() {
		wp_register_style( 'wpwc-calendar', WPWC_URL . 'assets/css/calendar.css', [], WPWC_VER );
		wp_register_script( 'wpwc-calendar', WPWC_URL . 'assets/js/calendar.js', [ 'wp-element' ], WPWC_VER, true );
		wp_localize_script( 'wpwc-calendar', 'WPWC', [
			'rest'   => [ 'root' => esc_url_raw( rest_url( 'wpwc/v1' ) ), 'nonce' => wp_create_nonce( 'wp_rest' ) ],
			'i18n'   => [ 'all' => __( 'Tutte le attività', 'wp-weekly-calendar' ) ],
		] );
	}

	public function activate() {
		Post_Types::register();
		flush_rewrite_rules();
	}

	public function deactivate() {
		flush_rewrite_rules();
	}
}
