<?php
/**
 * PHPUnit bootstrap for the unit tests, which run without WordPress.
 *
 * @package AgentWelcome
 */

define( 'ABSPATH', __DIR__ . '/' );

require_once dirname( __DIR__ ) . '/vendor/autoload.php';

if ( ! function_exists( 'wp_parse_url' ) ) {
	function wp_parse_url( $url, $component = -1 ) {
		return parse_url( $url, $component );
	}
}

if ( ! function_exists( 'wp_json_encode' ) ) {
	function wp_json_encode( $value, $flags = 0 ) {
		return json_encode( $value, $flags );
	}
}

if ( ! function_exists( '__' ) ) {
	function __( $text, $domain = 'default' ) {
		return $text;
	}
}

$GLOBALS['agentwelcome_test_transients'] = array();

if ( ! function_exists( 'get_transient' ) ) {
	function get_transient( $name ) {
		return $GLOBALS['agentwelcome_test_transients'][ $name ] ?? false;
	}
}

if ( ! function_exists( 'set_transient' ) ) {
	function set_transient( $name, $value, $expiration = 0 ) {
		$GLOBALS['agentwelcome_test_transients'][ $name ] = $value;

		return true;
	}
}

if ( ! function_exists( 'delete_transient' ) ) {
	function delete_transient( $name ) {
		unset( $GLOBALS['agentwelcome_test_transients'][ $name ] );

		return true;
	}
}

$GLOBALS['agentwelcome_test_filters'] = array();

if ( ! function_exists( 'apply_filters' ) ) {
	function apply_filters( $hook_name, $value, ...$args ) {
		$callback = $GLOBALS['agentwelcome_test_filters'][ $hook_name ] ?? null;

		return null === $callback ? $value : $callback( $value, ...$args );
	}
}

if ( ! function_exists( 'wp_check_invalid_utf8' ) ) {
	function wp_check_invalid_utf8( $text, $strip = false ) {
		return mb_check_encoding( (string) $text, 'UTF-8' ) ? $text : ( $strip ? (string) iconv( 'UTF-8', 'UTF-8//IGNORE', (string) $text ) : '' );
	}
}

$GLOBALS['agentwelcome_test_meta'] = array();

if ( ! class_exists( 'WP_Post' ) ) {
	class WP_Post {
		public int $ID             = 0;
		public string $post_excerpt = '';
	}
}

if ( ! function_exists( 'get_post_meta' ) ) {
	function get_post_meta( $post_id, $key = '', $single = false ) {
		return $GLOBALS['agentwelcome_test_meta'][ $post_id ][ $key ] ?? '';
	}
}

if ( ! function_exists( 'wp_strip_all_tags' ) ) {
	function wp_strip_all_tags( $text ) {
		return trim( strip_tags( $text ) );
	}
}

if ( ! function_exists( 'strip_shortcodes' ) ) {
	function strip_shortcodes( $text ) {
		return (string) preg_replace( '/\[[^\]]*\]/', '', $text );
	}
}

if ( ! function_exists( 'wp_trim_words' ) ) {
	function wp_trim_words( $text, $num_words = 55, $more = null ) {
		$words = preg_split( '/\s+/', trim( $text ), -1, PREG_SPLIT_NO_EMPTY );

		return implode( ' ', array_slice( $words, 0, $num_words ) ) . ( count( $words ) > $num_words ? ( $more ?? '&hellip;' ) : '' );
	}
}
