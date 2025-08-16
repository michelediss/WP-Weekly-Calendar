<?php
/**
 * Plugin Name:       WP Weekly Calendar (Attività = Categorie)
 * Description:       Calendario settimanale i cui "filtri categoria" derivano 1:1 dal CPT 'attivita'. Ogni attività è una categoria con colore ACF e link /attivita/slug.
 * Version:           2.0.0
 * Author:            WP Weekly Calendar Team
 * Text Domain:       wp-weekly-calendar
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'WPWC_FILE', __FILE__ );
define( 'WPWC_DIR', plugin_dir_path( __FILE__ ) );
define( 'WPWC_URL', plugin_dir_url( __FILE__ ) );
define( 'WPWC_VER', '2.0.0' );

require_once WPWC_DIR . 'includes/helpers.php';
require_once WPWC_DIR . 'includes/class-wpwc-plugin.php';

register_activation_hook( __FILE__, function() {
	\WPWC\Plugin::instance()->activate();
});

register_deactivation_hook( __FILE__, function() {
	\WPWC\Plugin::instance()->deactivate();
});

add_action( 'plugins_loaded', function() {
	load_plugin_textdomain( 'wp-weekly-calendar', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
	\WPWC\Plugin::instance()->boot();
} );
