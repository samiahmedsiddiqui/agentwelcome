<?php
// Behaves like a site where another plugin redirects every 404 to the homepage, and lets the kit switch off the plugin's own 404.
add_action( 'template_redirect', function () {
	if ( get_option( 'kit_redirect_404' ) && is_404() ) {
		wp_safe_redirect( home_url( '/' ), 301 );
		exit;
	}
}, 5 );
add_filter( 'agentwelcome_answer_unserved_md', function ( $answer ) {
	return get_option( 'kit_answer_off' ) ? false : $answer;
} );
