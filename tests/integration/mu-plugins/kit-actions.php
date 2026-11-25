<?php
// Records the plugin's update actions, and pretends WooCommerce is active when the kit asks for it.
foreach ( array( 'agentwelcome_markdown_updated', 'agentwelcome_cache_cleared' ) as $hook ) {
	add_action( $hook, function () use ( $hook ) {
		file_put_contents( WP_CONTENT_DIR . '/kit-actions.log', wp_json_encode( array( $hook, func_get_args() ) ) . "\n", FILE_APPEND );
	}, 10, 2 );
}
if ( get_option( 'kit_woocommerce' ) && ! class_exists( 'WooCommerce' ) ) {
	class WooCommerce {}
}
