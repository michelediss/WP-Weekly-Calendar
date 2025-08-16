<?php
/**
 * Plugin Name: WP Weekly Calendar (ACF + CPT Attività + AJAX)
 * Description: Eventi su tabella custom; categorie dal CPT "attivita" con colore ACF "colore"; frontend a griglia con filtri AJAX; unica pagina admin.
 * Version: 0.7.1
 * Author: Tu
 * Text Domain: wcw
 */
if (!defined('ABSPATH')) exit;

define('WCW_VERSION', '0.7.1');
define('WCW_PLUGIN_FILE', __FILE__);
define('WCW_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('WCW_PLUGIN_URL', plugin_dir_url(__FILE__));

require_once WCW_PLUGIN_DIR . 'includes/class-wcw-db.php';
require_once WCW_PLUGIN_DIR . 'includes/class-wcw-closures.php';
require_once WCW_PLUGIN_DIR . 'includes/class-wcw-shortcode.php';
require_once WCW_PLUGIN_DIR . 'includes/class-wcw-admin-page.php';

register_activation_hook(__FILE__, function(){ WCW_DB::create_tables(); });

add_action('plugins_loaded', function () {
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
