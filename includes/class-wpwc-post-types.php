<?php
namespace WPWC;

if ( ! defined( 'ABSPATH' ) ) exit;

class Post_Types {
	public static function register() {
		$labels = [
			'name'               => __( 'Eventi settimanali', 'wp-weekly-calendar' ),
			'singular_name'      => __( 'Evento settimanale', 'wp-weekly-calendar' ),
			'add_new'            => __( 'Aggiungi nuovo', 'wp-weekly-calendar' ),
			'add_new_item'       => __( 'Aggiungi nuovo evento', 'wp-weekly-calendar' ),
			'edit_item'          => __( 'Modifica evento', 'wp-weekly-calendar' ),
			'new_item'           => __( 'Nuovo evento', 'wp-weekly-calendar' ),
			'view_item'          => __( 'Vedi evento', 'wp-weekly-calendar' ),
			'search_items'       => __( 'Cerca eventi', 'wp-weekly-calendar' ),
			'not_found'          => __( 'Nessun evento trovato', 'wp-weekly-calendar' ),
			'not_found_in_trash' => __( 'Nessun evento nel cestino', 'wp-weekly-calendar' ),
			'menu_name'          => __( 'Calendario', 'wp-weekly-calendar' ),
		];

		register_post_type( 'wpwc_event', [
			'labels' => $labels,
			'public' => true,
			'show_ui' => true,
			'show_in_menu' => true,
			'menu_icon' => 'dashicons-calendar-alt',
			'supports' => [ 'title', 'editor' ],
			'show_in_rest' => true,
			'has_archive' => false,
			'rewrite' => false,
		] );
	}
}
