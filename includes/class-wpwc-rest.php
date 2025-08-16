<?php
namespace WPWC;

if ( ! defined( 'ABSPATH' ) ) exit;

class REST {

	public static function hooks() {
		add_action( 'rest_api_init', [ __CLASS__, 'routes' ] );
	}

	public static function routes() {
		register_rest_route( 'wpwc/v1', '/attivita', [
			'methods'  => 'GET',
			'callback' => function() {
				return rest_ensure_response( Activities::all() );
			},
			'permission_callback' => '__return_true',
		] );

		register_rest_route( 'wpwc/v1', '/events', [
			'methods'  => 'GET',
			'args'     => [
				'day'       => ['validate_callback' => function($v){ return in_array( (string)$v, array_keys( week_days() ), true ); }],
				'attivita'  => ['validate_callback' => function($v){ return empty($v) || is_numeric($v); }],
			],
			'callback' => [ __CLASS__, 'get_events' ],
			'permission_callback' => '__return_true',
		] );
	}

	public static function get_events( \WP_REST_Request $req ) {
		$day  = $req->get_param( 'day' );
		$att  = (int) $req->get_param( 'attivita' );

		$meta_query = [];
		if ( $day ) {
			$meta_query[] = [ 'key' => '_wpwc_day', 'value' => (string) $day ];
		}
		if ( $att ) {
			$meta_query[] = [ 'key' => '_wpwc_attivita', 'value' => $att, 'compare' => '=' ];
		}

		$q = new \WP_Query( [
			'post_type'      => 'wpwc_event',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'orderby'        => [ 'meta_value' => 'ASC', 'title' => 'ASC' ],
			'meta_key'       => '_wpwc_start',
			'meta_query'     => $meta_query,
			'no_found_rows'  => true,
		] );

		$events = [];
		while ( $q->have_posts() ) {
			$q->the_post();
			$id   = get_the_ID();
			$attv = (int) get_post_meta( $id, '_wpwc_attivita', true );
			$events[] = [
				'id'       => $id,
				'title'    => get_the_title(),
				'content'  => wp_strip_all_tags( get_the_content() ),
				'day'      => get_post_meta( $id, '_wpwc_day', true ),
				'start'    => get_post_meta( $id, '_wpwc_start', true ),
				'end'      => get_post_meta( $id, '_wpwc_end', true ),
				'attivita' => $attv ? Activities::format( $attv ) : null,
				'permalink'=> get_permalink( $id ),
			];
		}
		wp_reset_postdata();

		return rest_ensure_response( $events );
	}
}
