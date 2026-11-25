<?php
/**
 * Reads the rules of a robots.txt file and tells whether a crawler may fetch a path.
 *
 * @package AgentWelcome
 */

declare(strict_types=1);

namespace AgentWelcome\Robots;

defined( 'ABSPATH' ) || exit;

/** Parsed robots.txt: user-agent groups with Allow and Disallow rules. */
final class RobotsRules {

	/** @var array<string, list<array{bool, string}>> Rules by lowercase user agent, as allow flag and pattern. */
	private array $groups = array();

	/**
	 * @param string $body Content of robots.txt.
	 */
	public function __construct( string $body ) {
		$agents = array();
		$in_run = false;

		$lines = preg_split( '/\r\n|\r|\n/', preg_replace( '/^\xEF\xBB\xBF/', '', $body ) ?? '' );

		foreach ( false === $lines ? array() : $lines as $line ) {
			$line = trim( explode( '#', $line, 2 )[0] );

			if ( ! str_contains( $line, ':' ) ) {
				continue;
			}

			list( $field, $value ) = array_map( 'trim', explode( ':', $line, 2 ) );
			$field                 = strtolower( $field );

			if ( 'user-agent' === $field ) {
				if ( ! $in_run ) {
					$agents = array();
					$in_run = true;
				}

				$agents[]                               = strtolower( $value );
				$this->groups[ strtolower( $value ) ] ??= array();
				continue;
			}

			if ( 'allow' !== $field && 'disallow' !== $field ) {
				continue;
			}

			$in_run = false;

			// An empty Disallow allows everything, and an empty Allow adds nothing.
			if ( '' === $value ) {
				continue;
			}

			foreach ( $agents as $agent ) {
				$this->groups[ $agent ][] = array( 'allow' === $field, $value );
			}
		}
	}

	/**
	 * Whether a crawler may fetch a path.
	 *
	 * @param string $agent User agent token, such as GPTBot.
	 * @param string $path  Path with the query string, starting with a slash.
	 * @return bool False only when a rule disallows it.
	 */
	public function allows( string $agent, string $path ): bool {
		$agent = strtolower( $agent );
		$rules = $this->groups[ $agent ] ?? $this->groups['*'] ?? array();
		$best  = -1;
		$allow = true;

		foreach ( $rules as list( $is_allow, $pattern ) ) {
			if ( ! self::matches( $pattern, $path ) ) {
				continue;
			}

			$length = strlen( $pattern );

			if ( $length > $best || ( $length === $best && $is_allow ) ) {
				$best  = $length;
				$allow = $is_allow;
			}
		}

		return $allow;
	}

	/**
	 * Whether a rule pattern matches the start of a path. * matches any characters, and a final $ matches the end.
	 *
	 * @param string $pattern Rule pattern.
	 * @param string $path    Path.
	 * @return bool True when it matches.
	 */
	private static function matches( string $pattern, string $path ): bool {
		$anchored = str_ends_with( $pattern, '$' );
		$regex    = '/^' . str_replace( '\*', '.*', preg_quote( $anchored ? substr( $pattern, 0, -1 ) : $pattern, '/' ) ) . ( $anchored ? '$' : '' ) . '/s';

		return 1 === preg_match( $regex, $path );
	}
}
