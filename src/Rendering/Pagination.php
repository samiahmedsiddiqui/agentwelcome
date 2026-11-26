<?php
/**
 * Reads the number of paginated views from the links of a page.
 *
 * @package AgentWelcome
 */

declare(strict_types=1);

namespace AgentWelcome\Rendering;

defined( 'ABSPATH' ) || exit;

/** Finds the links of a list page to its own /page/N/ views. */
final class Pagination {

	/**
	 * Gets the highest view number that the page links to. Links marked ugc, such as those in comments, are not read.
	 *
	 * @param string $html HTML of the page or of one of its views.
	 * @param string $url  Address of the page itself, which is view 1.
	 * @return int Number of views, 1 when the page links to none.
	 */
	public static function count( string $html, string $url ): int {
		$page = wp_parse_url( $url );

		if ( ! is_array( $page ) || empty( $page['host'] ) ) {
			return 1;
		}

		$html = (string) preg_replace( '/<!--.*?-->|<(script|style|noscript|template)\b.*?<\/\1\s*>/is', '', $html );

		if ( ! preg_match_all( '/<(?:a|link)\b((?:"[^"]*"|\'[^\']*\'|[^>"\'])*)>/i', $html, $tags ) ) {
			return 1;
		}

		$pattern = '#^' . preg_quote( rtrim( rawurldecode( $page['path'] ?? '' ), '/' ), '#' ) . '/page/([1-9][0-9]{0,3})/?$#i';
		$count   = 1;

		foreach ( $tags[1] as $tag ) {
			if ( 1 === preg_match( '/\srel\s*=\s*["\'][^"\']*\bugc\b/i', $tag ) || 1 !== preg_match( '/\shref\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|([^\s"\'>]+))/i', $tag, $href ) ) {
				continue;
			}

			$address = html_entity_decode( $href[1] . ( $href[2] ?? '' ) . ( $href[3] ?? '' ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
			$link    = wp_parse_url( UrlResolver::resolve( $url, trim( $address ) ) );

			if ( ! is_array( $link ) || 0 !== strcasecmp( (string) ( $link['host'] ?? '' ), $page['host'] ) ) {
				continue;
			}

			if ( 1 === preg_match( $pattern, rawurldecode( $link['path'] ?? '' ), $found ) ) {
				$count = max( $count, (int) $found[1] );
			}
		}

		return $count;
	}
}
