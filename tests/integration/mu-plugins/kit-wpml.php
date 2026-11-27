<?php
// Stands in for WPML in the language directory format: English at the root, Arabic under /ar/ and Chinese under /zh-hans/.
// The option kit_wpml switches it on: "filter" leaves the request address alone, "rewrite" takes the directory out of it early.
// A post's or term's language is the meta kit_lang, and translations share the meta kit_trid (the ID of the English one).
$kit_wpml = (string) get_option( 'kit_wpml' );
if ( '' === $kit_wpml ) {
	return;
}
define( 'ICL_SITEPRESS_VERSION', '0-kit' );

$GLOBALS['kit_wpml_current'] = 'en';
if ( preg_match( '#^/(ar|zh-hans)(?=/|\?|$)#', $_SERVER['REQUEST_URI'] ?? '', $kit_found ) ) {
	$GLOBALS['kit_wpml_current'] = $kit_found[1];
	if ( 'rewrite' === $kit_wpml ) {
		$_SERVER['REQUEST_URI'] = '/' . ltrim( substr( $_SERVER['REQUEST_URI'], strlen( $kit_found[0] ) ), '/' );
	}
}
$GLOBALS['kit_wpml_request'] = $GLOBALS['kit_wpml_current'];

function kit_wpml_languages() {
	$all = array(
		'en'      => array( 'English', 'en_US' ),
		'ar'      => array( 'العربية', 'ar' ),
		'zh-hans' => array( '简体中文', 'zh_CN' ),
	);
	$out = array();
	foreach ( $all as $code => $data ) {
		$out[ $code ] = array( 'language_code' => $code, 'native_name' => $data[0], 'default_locale' => $data[1] );
	}
	return $out;
}

function kit_wpml_convert( $url, $lang ) {
	$home = rtrim( (string) get_option( 'home' ), '/' );
	if ( ! is_string( $url ) || 0 !== strpos( $url, $home ) ) {
		return $url;
	}
	$rest = preg_replace( '#^/(ar|zh-hans)(?=/|\?|$)#', '', substr( $url, strlen( $home ) ) );
	return $home . ( 'en' === $lang || ! $lang ? '' : '/' . $lang ) . $rest;
}

function kit_wpml_object( $id, $type, $return_original = false, $lang = null ) {
	global $wpdb;
	$lang = $lang ? $lang : $GLOBALS['kit_wpml_current'];
	$term = taxonomy_exists( $type );
	$meta = $term ? 'get_term_meta' : 'get_post_meta';
	$id   = (int) $id;
	$trid = (int) $meta( $id, 'kit_trid', true );
	$trid = $trid ? $trid : $id;
	$ids  = $term
		? $wpdb->get_col( $wpdb->prepare( "SELECT term_id FROM {$wpdb->termmeta} WHERE meta_key = 'kit_trid' AND meta_value = %s", $trid ) )
		: $wpdb->get_col( $wpdb->prepare( "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = 'kit_trid' AND meta_value = %s", $trid ) );
	foreach ( array_merge( array( $trid ), array_map( 'intval', $ids ) ) as $candidate ) {
		$own  = (string) $meta( $candidate, 'kit_lang', true );
		$item = $term ? get_term( $candidate, $type ) : get_post( $candidate );
		$kind = $item && ! is_wp_error( $item ) ? ( $term ? $item->taxonomy : $item->post_type ) : '';
		if ( $kind === $type && ( $own ? $own : 'en' ) === $lang ) {
			return $candidate;
		}
	}
	return $return_original ? $id : null;
}

add_filter( 'wpml_default_language', fn() => 'en' );
add_filter( 'wpml_current_language', fn() => $GLOBALS['kit_wpml_current'] );
add_filter( 'wpml_active_languages', fn() => kit_wpml_languages() );
add_action( 'wpml_switch_language', function ( $code ) {
	$GLOBALS['kit_wpml_current'] = $code ? $code : $GLOBALS['kit_wpml_request'];
} );
add_filter( 'wpml_object_id', 'kit_wpml_object', 10, 4 );
add_filter( 'wpml_post_language_details', function ( $value, $id ) {
	$code = (string) get_post_meta( $id, 'kit_lang', true );
	$code = $code ? $code : 'en';
	return array( 'language_code' => $code, 'locale' => kit_wpml_languages()[ $code ]['default_locale'] );
}, 10, 2 );
add_filter( 'wpml_is_translated_post_type', fn( $value, $type ) => in_array( $type, array( 'post', 'page' ), true ), 10, 2 );
add_filter( 'wpml_permalink', fn( $url, $lang = null ) => kit_wpml_convert( $url, $lang ? $lang : $GLOBALS['kit_wpml_current'] ), 10, 2 );

add_filter( 'home_url', fn( $url ) => kit_wpml_convert( $url, $GLOBALS['kit_wpml_current'] ) );
foreach ( array( 'post_link', 'page_link', 'post_type_link' ) as $kit_hook ) {
	add_filter( $kit_hook, function ( $url, $post ) {
		$id   = is_object( $post ) ? $post->ID : (int) $post;
		$code = (string) get_post_meta( $id, 'kit_lang', true );
		return kit_wpml_convert( $url, $code ? $code : 'en' );
	}, 10, 2 );
}
foreach ( array( 'page_on_front', 'page_for_posts' ) as $kit_option ) {
	add_filter( 'option_' . $kit_option, fn( $id ) => (int) $id ? (string) (int) kit_wpml_object( $id, 'page', false ) : $id );
}
add_filter( 'option_blogdescription', fn( $text ) => array( 'ar' => 'وصف الموقع', 'zh-hans' => '网站描述' )[ $GLOBALS['kit_wpml_current'] ] ?? $text );
add_filter( 'posts_where', function ( $where ) {
	global $wpdb;
	$lang  = $GLOBALS['kit_wpml_current'];
	$other = "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = 'kit_lang'";
	$match = 'en' === $lang
		? "{$wpdb->posts}.ID NOT IN ( {$other} AND meta_value <> 'en' )"
		: $wpdb->prepare( "{$wpdb->posts}.ID IN ( {$other} AND meta_value = %s )", $lang );
	return $where . " AND ( {$wpdb->posts}.post_type NOT IN ( 'post', 'page' ) OR {$match} )";
} );
add_action( 'init', fn() => remove_action( 'template_redirect', 'redirect_canonical' ) );
