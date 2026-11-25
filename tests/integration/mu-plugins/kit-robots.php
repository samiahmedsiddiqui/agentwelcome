<?php
// Gives a page a robots meta tag or an X-Robots-Tag header, by post meta, or for a whole post type by an option.
function kit_robots_value( $key ) {
	if ( ! is_singular() ) {
		return '';
	}
	$value = (string) get_post_meta( get_the_ID(), $key, true );
	if ( '' === $value && get_option( 'kit_robots_type' ) === get_post_type() && 'kit_robots' === $key ) {
		$value = 'noindex';
	}
	return $value;
}
add_filter( 'wp_robots', function ( $robots ) {
	$value = kit_robots_value( 'kit_robots' );
	if ( '' !== $value ) {
		foreach ( explode( ',', $value ) as $directive ) {
			$robots[ trim( $directive ) ] = true;
		}
	}
	return $robots;
} );
add_action( 'template_redirect', function () {
	$value = kit_robots_value( 'kit_robots_header' );
	if ( '' !== $value && ! headers_sent() ) {
		header( 'X-Robots-Tag: ' . $value );
	}
}, 20 );
