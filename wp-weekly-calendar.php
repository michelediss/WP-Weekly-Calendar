<?php
/**
 * Plugin Name: WP Weekly Calendar 
 * Description: Calendario settimanale per le attività dell'ex-OPG "Je so' pazzo".
 * Version: 1.1.0
 * Author: Michele Paolino
 * Author URI: https://michelepaolino.com
 * Text Domain: wcw
 */
if (!defined('ABSPATH')) exit;

define('WCW_VERSION', '1.1.0');
define('WCW_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('WCW_PLUGIN_URL', plugin_dir_url(__FILE__));

require_once WCW_PLUGIN_DIR . 'includes/class-wcw-db.php';
require_once WCW_PLUGIN_DIR . 'includes/class-wcw-closures.php';
require_once WCW_PLUGIN_DIR . 'includes/class-wcw-shortcode.php';
require_once WCW_PLUGIN_DIR . 'includes/class-wcw-admin-page.php';

// install / upgrade — con buffer per intercettare eventuale output
register_activation_hook(__FILE__, function () {
  ob_start();

  // Se il file delle tabelle ha "maybe_upgrade_schema", chiamalo dopo create_tables
  if (class_exists('WCW_DB') && method_exists('WCW_DB', 'create_tables')) {
    WCW_DB::create_tables();
  }
  if (class_exists('WCW_DB') && method_exists('WCW_DB', 'maybe_upgrade_schema')) {
    WCW_DB::maybe_upgrade_schema();
  }

  // Se qualcosa ha stampato, lo registriamo nel log e lo scartiamo
  $out = ob_get_clean();
  if (!empty($out)) {
    error_log('WP Weekly Calendar: output inatteso in attivazione ('.strlen($out).' chars): '.wp_strip_all_tags($out));
  }
});

add_action('plugins_loaded', function(){
  // garantisce che le nuove colonne esistano anche su installazioni già attive
  if (method_exists('WCW_DB','maybe_upgrade_schema')) {
    WCW_DB::maybe_upgrade_schema();
  }
  WCW_Shortcode::init();
  if (is_admin()) WCW_Admin_Page::init();
});

add_action('wp_enqueue_scripts', function(){
  wp_enqueue_style('wcw-public', WCW_PLUGIN_URL.'assets/public.css', [], WCW_VERSION);
});

add_action('admin_enqueue_scripts', function($hook){
  if ($hook === 'toplevel_page_wcw-calendar') {
    wp_enqueue_style('wcw-admin', WCW_PLUGIN_URL.'assets/admin.css', [], WCW_VERSION);
  }
});
