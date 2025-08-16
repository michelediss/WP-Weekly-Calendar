<?php
namespace WPWC;

if ( ! defined( 'ABSPATH' ) ) exit;

function get_attivita_color( $attivita_id ): string {
	$color = get_post_meta( $attivita_id, 'colore', true );
	if ( ! is_string( $color ) || ! preg_match( '/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/', $color ) ) {
		$color = '#777777';
	}
	return $color;
}

function sanitize_time_24h( $time ): string {
	if ( ! is_string( $time ) ) return '';
	if ( preg_match( '/^([01]\d|2[0-3]):([0-5]\d)$/', $time, $m ) ) return $m[0];
	return '';
}

function week_days(): array {
	return [ '1' => __( 'Lunedì', 'wp-weekly-calendar' ),
	         '2' => __( 'Martedì', 'wp-weekly-calendar' ),
	         '3' => __( 'Mercoledì', 'wp-weekly-calendar' ),
	         '4' => __( 'Giovedì', 'wp-weekly-calendar' ),
	         '5' => __( 'Venerdì', 'wp-weekly-calendar' ),
	         '6' => __( 'Sabato', 'wp-weekly-calendar' ),
	         '7' => __( 'Domenica', 'wp-weekly-calendar' ) ];
}
