<?php
/**
 * Reads whether a page tells search engines not to index it.
 *
 * @package AgentWelcome
 */

declare(strict_types=1);

namespace AgentWelcome\Rendering;

defined( 'ABSPATH' ) || exit;

/** Looks for noindex in the robots meta tag and in the X-Robots-Tag header of a rendered page. */
final class Robots {

	/** Source of a noindex found in the page's robots meta tag. */
	public const META = 'meta';
	/** Source of a noindex found in the X-Robots-Tag header. */
	public const HEADER = 'header';

	/** Directives that take a value after a colon, so the text before it is not a crawler name. */
	private const VALUE_DIRECTIVES = array( 'max-snippet', 'max-image-preview', 'max-video-preview', 'unavailable_after' );

	/**
	 * Where a noindex was found.
	 *
	 * @param string $html   HTML of the page.
	 * @param string $header Values of the X-Robots-Tag header, one per line.
	 * @return string META, HEADER, or an empty string when the page is not noindex.
	 */
	public static function source( string $html, string $header = '' ): string {
		if ( self::meta_says_noindex( $html ) ) {
			return self::META;
		}

		return self::header_says_noindex( $header ) ? self::HEADER : '';
	}

	/**
	 * Whether the page is noindex.
	 *
	 * @param string $html   HTML of the page.
	 * @param string $header Values of the X-Robots-Tag header, one per line.
	 * @return bool True when the meta tag or the header says noindex or none.
	 */
	public static function is_noindex( string $html, string $header = '' ): bool {
		return '' !== self::source( $html, $header );
	}

	/**
	 * Whether a robots meta tag in the head says noindex.
	 *
	 * @param string $html HTML of the page.
	 * @return bool True when it does.
	 */
	private static function meta_says_noindex( string $html ): bool {
		$end  = stripos( $html, '</head>' );
		$head = false === $end ? $html : substr( $html, 0, $end );

		preg_match_all( '/<meta\b[^>]*>/i', $head, $tags );

		foreach ( $tags[0] as $tag ) {
			$attributes = self::attributes( $tag );

			if ( 'robots' === strtolower( $attributes['name'] ?? '' ) && self::has_noindex( $attributes['content'] ?? '' ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Whether the header says noindex for every crawler.
	 *
	 * @param string $header Values of the X-Robots-Tag header, one per line.
	 * @return bool True when it does.
	 */
	private static function header_says_noindex( string $header ): bool {
		foreach ( (array) preg_split( '/[\r\n]+/', $header ) as $line ) {
			$line = trim( $line );

			if ( 1 === preg_match( '/^([A-Za-z0-9_-]+)\s*:\s*(.*)$/', $line, $match ) && ! in_array( strtolower( $match[1] ), self::VALUE_DIRECTIVES, true ) ) {
				if ( 'robots' !== strtolower( $match[1] ) ) {
					continue;
				}

				$line = $match[2];
			}

			if ( self::has_noindex( $line ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Whether a list of directives holds noindex or none.
	 *
	 * @param string $directives Comma separated directives.
	 * @return bool True when it does.
	 */
	private static function has_noindex( string $directives ): bool {
		foreach ( explode( ',', strtolower( $directives ) ) as $directive ) {
			if ( in_array( trim( $directive ), array( 'noindex', 'none' ), true ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Reads the attributes of one tag.
	 *
	 * @param string $tag The tag, such as <meta name="robots" content="noindex">.
	 * @return array<string, string> Values by lower case attribute name.
	 */
	private static function attributes( string $tag ): array {
		$found = array();

		if ( preg_match_all( '/([a-zA-Z:_-]+)\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|([^\s"\'>]+))/', $tag, $matches, PREG_SET_ORDER ) ) {
			foreach ( $matches as $match ) {
				$found[ strtolower( $match[1] ) ] = html_entity_decode( ( $match[2] ?? '' ) . ( $match[3] ?? '' ) . ( $match[4] ?? '' ), ENT_QUOTES, 'UTF-8' );
			}
		}

		return $found;
	}
}
