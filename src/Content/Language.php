<?php
/**
 * Runs code in the language of a page, on sites with a translation plugin.
 *
 * @package AgentWelcome
 */

declare(strict_types=1);

namespace AgentWelcome\Content;

use WP_Post;

defined( 'ABSPATH' ) || exit;

/** The points where a translation plugin takes part. Without one, every method behaves as if there were a single language. */
final class Language {

	/**
	 * Language code of a page.
	 *
	 * @param WP_Post $post The page.
	 * @return string The code, or an empty string on a site with one language.
	 */
	public static function of( WP_Post $post ): string {
		return (string) apply_filters( 'agentwelcome_post_language', '', $post );
	}

	/**
	 * Runs a callback with a language active, and goes back to the previous one.
	 *
	 * @template T
	 *
	 * @param string        $language Language code, or an empty string to change nothing.
	 * @param callable(): T $run      What to run.
	 * @return T What the callback returns.
	 */
	public static function run( string $language, callable $run ): mixed {
		if ( '' === $language ) {
			return $run();
		}

		do_action( 'agentwelcome_switch_language', $language );

		try {
			return $run();
		} finally {
			do_action( 'agentwelcome_restore_language' );
		}
	}

	/**
	 * Home address of the active language.
	 *
	 * @param string $path Path below the home address.
	 * @return string The address.
	 */
	public static function home_url( string $path = '' ): string {
		return (string) apply_filters( 'agentwelcome_home_url', home_url( $path ), $path );
	}
}
