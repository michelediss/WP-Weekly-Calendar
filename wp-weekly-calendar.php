<?php
/**
 * Plugin Name: WP Weekly Calendar (CPT + Tassonomia)
 * Description: Calendario settimanale lunedì–giovedì con eventi filtrabili via tassonomia personalizzata e shortcode.
 * Version: 0.2.0
 * Author: Tu
 * Text Domain: wcw
 */

if (!defined('ABSPATH')) exit;

// -------------------------------------------------
// Autoload minimale
// -------------------------------------------------
require_once __DIR__ . '/includes/class-wcw-cpt.php';
require_once __DIR__ . '/includes/class-wcw-closures.php';
require_once __DIR__ . '/includes/class-wcw-shortcode.php';
require_once __DIR__ . '/includes/class-wcw-admin-page.php';
require_once __DIR__ . '/includes/class-wcw-migration.php';

// -------------------------------------------------
// Bootstrap
// -------------------------------------------------
add_action('init', function(){
  WCW_CPT::register();
});

add_action('plugins_loaded', function(){
  // Shortcode e assets pubblici
  WCW_Shortcode::init();
  // Admin
  if (is_admin()) {
    WCW_Admin_Page::init();
    WCW_Migration::maybe_admin_notice();
  }
});

register_activation_hook(__FILE__, function(){
  WCW_CPT::register();
  flush_rewrite_rules();
});

register_deactivation_hook(__FILE__, function(){
  flush_rewrite_rules();
});

// -------------------------------------------------
// Assets
// -------------------------------------------------
add_action('wp_enqueue_scripts', function(){
  wp_enqueue_style('wcw-public', plugins_url('assets/public.css', __FILE__), [], '0.2.0');
});

add_action('admin_enqueue_scripts', function($hook){
  if ($hook === 'toplevel_page_wcw-calendar') {
    wp_enqueue_style('wcw-admin', plugins_url('assets/admin.css', __FILE__), [], '0.2.0');
  }
});
