<?php
/**
 * Reads the Accept header of a request.
 *
 * @package AgentWelcome
 */

declare(strict_types=1);

namespace AgentWelcome\Http;

defined( 'ABSPATH' ) || exit;

/** Tells whether a client asks for Markdown more than for HTML. */
final class AcceptHeader {

	/**
	 * Whether text/markdown is listed with a higher quality than text/html.
	 *
	 * The wildcards text/* and star/star only count for HTML, so a client that accepts anything gets the page as usual.
	 *
	 * @param string $header Value of the Accept header.
	 * @return bool True when the client prefers Markdown.
	 */
	public static function prefers_markdown( string $header ): bool {
		$ranges = self::parse( $header );

		if ( ! isset( $ranges['text/markdown'] ) || $ranges['text/markdown'] <= 0.0 ) {
			return false;
		}

		foreach ( array( 'text/html', 'text/*', '*/*' ) as $range ) {
			if ( isset( $ranges[ $range ] ) ) {
				return $ranges['text/markdown'] > $ranges[ $range ];
			}
		}

		return true;
	}

	/**
	 * Splits the header into media ranges and their quality.
	 *
	 * @param string $header Value of the Accept header.
	 * @return array<string, float> Quality (0 to 1) by lower case media range. The highest value wins for a repeated range.
	 */
	private static function parse( string $header ): array {
		$ranges = array();

		foreach ( explode( ',', $header ) as $item ) {
			$parts = array_map( 'trim', explode( ';', $item ) );
			$range = strtolower( (string) array_shift( $parts ) );

			if ( 1 !== preg_match( '#^(\*|[a-z0-9!\#$&^_.+-]+)/(\*|[a-z0-9!\#$&^_.+-]+)$#', $range ) ) {
				continue;
			}

			$quality = 1.0;

			foreach ( $parts as $param ) {
				if ( 1 === preg_match( '/^q\s*=\s*(\d(?:\.\d{0,3})?)$/i', $param, $match ) ) {
					$quality = min( 1.0, (float) $match[1] );
				}
			}

			$ranges[ $range ] = max( $ranges[ $range ] ?? 0.0, $quality );
		}

		return $ranges;
	}
}
