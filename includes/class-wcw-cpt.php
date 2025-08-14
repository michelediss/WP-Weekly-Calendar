<?php
// =================================================
// File: includes/class-wcw-cpt.php
// =================================================

if (!class_exists('WCW_CPT')):
class WCW_CPT {
  const POST_TYPE = 'wcw_event';
  const TAXONOMY  = 'wcw_category';
  const META_WEEKDAY = '_wcw_weekday'; // 1..4
  const META_TIME    = '_wcw_time';    // HH:MM

  public static function register(){
    register_post_type(self::POST_TYPE, [
      'label' => __('Eventi', 'wcw'),
      'public' => false,
      'show_ui' => true,
      'show_in_menu' => false, // usiamo la nostra pagina admin
      'supports' => ['title'],
      'capability_type' => 'post',
      'map_meta_cap' => true,
    ]);

    register_taxonomy(self::TAXONOMY, self::POST_TYPE, [
      'label' => __('Categorie', 'wcw'),
      'public' => false,
      'show_ui' => true,
      'hierarchical' => true,
      'show_admin_column' => true,
      'rewrite' => false,
    ]);
  }

  public static function day_label($d){
    $map = [1=>'Lunedì',2=>'Martedì',3=>'Mercoledì',4=>'Giovedì'];
    return isset($map[$d]) ? $map[$d] : '';
  }
}
endif;
