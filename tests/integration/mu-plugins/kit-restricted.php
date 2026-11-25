<?php
// Makes a page hide from logged-out visitors, the way a membership plugin does, by post meta kit_restrict: 403, 401, login (redirect to a login page) or slash (a harmless redirect).
add_action( 'template_redirect', function () {
	if ( is_user_logged_in() || ! is_singular() ) {
		return;
	}
	$mode = (string) get_post_meta( get_the_ID(), 'kit_restrict', true );
	if ( '' === $mode ) {
		return;
	}
	if ( '403' === $mode || '401' === $mode ) {
		status_header( (int) $mode );
		echo 'Members only.';
		exit;
	}
	if ( 'login' === $mode ) {
		wp_safe_redirect( home_url( '/integ-rs-login/' ), 302 );
		exit;
	}
	if ( 'slash' === $mode ) {
		$path = (string) wp_parse_url( home_url( add_query_arg( array() ) ), PHP_URL_PATH );
		if ( '/' === substr( $path, -1 ) ) {
			wp_safe_redirect( untrailingslashit( get_permalink() ), 301 );
			exit;
		}
		remove_action( 'template_redirect', 'redirect_canonical' );
	}
}, 1 );
// Includes pages on purpose: all of them, or only those with this reason code.
add_filter( 'agentwelcome_is_restricted', function ( $restricted, $post, $reason ) {
	$include = (string) get_option( 'kit_restricted_include' );
	if ( '1' === $include || $include === $reason ) {
		return false;
	}
	return $restricted;
}, 10, 3 );
