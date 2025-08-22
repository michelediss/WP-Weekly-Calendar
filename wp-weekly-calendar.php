<?php
/**
 * Plugin Name: WP Weekly Calendar 
 * Description: Calendario settimanale per le attività dell'ex-OPG "Je so' pazzo".
 * Version: 1.0.0
 * Author: Michele Paolino
 * Author URI: https://michelepaolino.com
 * Text Domain: wcw
 */
if (!defined('ABSPATH')) exit;

define('WCW_VERSION', '1.0.0');
define('WCW_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('WCW_PLUGIN_URL', plugin_dir_url(__FILE__));

require_once WCW_PLUGIN_DIR . 'includes/class-wcw-db.php';
require_once WCW_PLUGIN_DIR . 'includes/class-wcw-closures.php';
require_once WCW_PLUGIN_DIR . 'includes/class-wcw-shortcode.php';
require_once WCW_PLUGIN_DIR . 'includes/class-wcw-admin-page.php';

register_activation_hook(__FILE__, function(){ WCW_DB::create_tables(); });

add_action('plugins_loaded', function(){
  WCW_Shortcode::init();
  if (is_admin()) WCW_Admin_Page::init();
});

/**
 * Restituisce una versione basata sul filemtime se il file esiste,
 * altrimenti cade su WCW_VERSION (comodo per cache-busting in produzione).
 */
function wcw_asset_version($relative_path){
  $file = WCW_PLUGIN_DIR . ltrim($relative_path, '/');
  return file_exists($file) ? (string) filemtime($file) : WCW_VERSION;
}

add_action('wp_enqueue_scripts', function(){
  // CSS pubblico
  wp_enqueue_style(
    'wcw-public',
    WCW_PLUGIN_URL . 'assets/public.css',
    [],
    wcw_asset_version('assets/public.css')
  );

  // JS pubblico (caricato nel footer)
  wp_enqueue_script(
    'wcw-public-js',
    WCW_PLUGIN_URL . 'assets/public.js',
    [],                                 // aggiungi dipendenze se servono (es. ['jquery'])
    wcw_asset_version('assets/public.js'),
    true                                // in footer
  );

  // Opzionale: marcare come 'defer' su WP 5.7+ (mantiene retrocompatibilità)
  if (function_exists('wp_script_add_data')) {
    wp_script_add_data('wcw-public-js', 'defer', true);
  }
});

add_action('admin_enqueue_scripts', function($hook){
  if ($hook === 'toplevel_page_wcw-calendar') {
    wp_enqueue_style(
      'wcw-admin',
      WCW_PLUGIN_URL . 'assets/admin.css',
      [],
      wcw_asset_version('assets/admin.css')
    );
  }
});
