<?php
namespace WPWC;

if ( ! defined( 'ABSPATH' ) ) exit;

class Activities {

	public static function all(): array {
		$ids = get_posts( [
			'post_type'   => 'attivita',
			'post_status' => 'publish',
			'numberposts' => -1,
			'orderby'     => 'title',
			'order'       => 'ASC',
			'fields'      => 'ids',
		] );

		$out = [];
		foreach ( $ids as $id ) {
			$out[] = self::format( $id );
		}
		return $out;
	}

	public static function format( int $id ): array {
		return [
			'id'    => $id,
			'title' => get_the_title( $id ),
			'slug'  => basename( get_permalink( $id ) ),
			'url'   => get_permalink( $id ),
			'color' => get_attivita_color( $id ),
		];
	}
}
