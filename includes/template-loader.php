<?php
namespace WPWC;

if ( ! defined( 'ABSPATH' ) ) exit;

class Template_Loader {
	public static function hooks() { /* placeholder per override futuri */ }
	public static function path( string $template ): string {
		$default = WPWC_DIR . 'templates/' . ltrim( $template, '/' );
		return apply_filters( 'wpwc_template_' . basename( $template ), $default );
	}
	public static function template( string $template, array $vars = [] ) {
		$path = self::path( $template );
		extract( $vars, EXTR_SKIP );
		include $path;
	}
}
