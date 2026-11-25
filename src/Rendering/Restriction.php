<?php
/**
 * Tells from a loopback response whether visitors can read a page.
 *
 * @package AgentWelcome
 */

declare(strict_types=1);

namespace AgentWelcome\Rendering;

defined( 'ABSPATH' ) || exit;

/** Decides whether a page is restricted: it answers 401 or 403, or sends a visitor to another path such as a login page. */
final class Restriction {

	/** Start of the reason of a redirect, followed by the path the visitor lands on. */
	public const REDIRECT = 'redirect';

	/**
	 * Reason for a status code.
	 *
	 * @param int  $code            HTTP status code.
	 * @param bool $basic_challenge Whether a 401 asks for HTTP Basic credentials, which a site-wide password does.
	 * @return string|null http_401 or http_403, or null when the status does not restrict the page.
	 */
	public static function from_status( int $code, bool $basic_challenge = false ): ?string {
		if ( 403 === $code || ( 401 === $code && ! $basic_challenge ) ) {
			return 'http_' . $code;
		}

		return null;
	}

	/**
	 * Reason for a redirect. Differences in the trailing slash, the scheme, the host, the query and the fragment are ignored.
	 *
	 * @param string $page   Address of the page.
	 * @param string $landed Address the request ended up at.
	 * @return string|null "redirect:" and the path landed on, or null when it is the page's own path.
	 */
	public static function from_redirect( string $page, string $landed ): ?string {
		if ( self::path( $page ) === self::path( $landed ) ) {
			return null;
		}

		$shown = (string) wp_parse_url( $landed, PHP_URL_PATH );

		return self::REDIRECT . ':' . ( '' === $shown ? '/' : $shown );
	}

	/**
	 * Short code of a reason, without the path.
	 *
	 * @param string $reason Reason from this class.
	 * @return string The code, such as http_403 or redirect.
	 */
	public static function code( string $reason ): string {
		return explode( ':', $reason, 2 )[0];
	}

	/**
	 * Path part of an address, without the trailing slash.
	 *
	 * @param string $url Address.
	 * @return string Path starting with a slash.
	 */
	private static function path( string $url ): string {
		$path = rawurldecode( (string) wp_parse_url( $url, PHP_URL_PATH ) );

		return '/' . trim( $path, '/' );
	}
}
