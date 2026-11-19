<?php
/**
 * Decides whether a Markdown request gets the formatted page or the plain Markdown.
 *
 * @package AgentWelcome
 */

declare(strict_types=1);

namespace AgentWelcome\Http;

use AgentWelcome\Settings\Options;
use WP_Post;

defined( 'ABSPATH' ) || exit;

/** Tells a person opening a Markdown link in a browser from an agent or a script. */
final class Preview {

	/** Query argument that asks for the formatted page. */
	public const PARAM = 'preview';
	/** Query argument that asks for the plain Markdown. */
	public const RAW = 'raw';
	/** Request headers the answer depends on when browsers get the formatted page by default. */
	public const VARY = 'Accept, Sec-Fetch-Dest, Sec-Fetch-Mode';

	/**
	 * @param Options $options Saved settings.
	 */
	public function __construct( private Options $options ) {}

	/**
	 * Whether browsers get the formatted page without asking for it.
	 *
	 * @return bool True when the setting is on.
	 */
	public function automatic(): bool {
		return (bool) $this->options->get( 'browser_preview', false );
	}

	/**
	 * Whether the current request should get the formatted page.
	 *
	 * @param WP_Post $post The page that is served.
	 * @return bool True for the formatted page, false for the plain Markdown.
	 */
	public function wanted( WP_Post $post ): bool {
		$preview = isset( $_GET[ self::PARAM ] ) && is_string( $_GET[ self::PARAM ] ) ? sanitize_text_field( wp_unslash( $_GET[ self::PARAM ] ) ) : null; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only choice of view.
		$raw     = isset( $_GET[ self::RAW ] ) && is_string( $_GET[ self::RAW ] ) ? sanitize_text_field( wp_unslash( $_GET[ self::RAW ] ) ) : null; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only choice of view.

		$wanted = self::decide(
			$preview,
			$raw,
			array(
				'accept' => $this->header( 'HTTP_ACCEPT' ),
				'dest'   => $this->header( 'HTTP_SEC_FETCH_DEST' ),
				'mode'   => $this->header( 'HTTP_SEC_FETCH_MODE' ),
			),
			$this->automatic()
		);

		return (bool) apply_filters( 'agentwelcome_markdown_preview', $wanted, $post );
	}

	/**
	 * Applies the rules to the values of a request.
	 *
	 * @param string|null           $preview   Value of the preview argument, or null when it is absent.
	 * @param string|null           $raw       Value of the raw argument, or null when it is absent.
	 * @param array<string, string> $headers   The accept, dest (Sec-Fetch-Dest) and mode (Sec-Fetch-Mode) request headers.
	 * @param bool                  $automatic Whether browsers get the formatted page by default.
	 * @return bool True for the formatted page.
	 */
	public static function decide( ?string $preview, ?string $raw, array $headers, bool $automatic ): bool {
		if ( self::is_on( $raw ) ) {
			return false;
		}

		if ( self::is_on( $preview ) ) {
			return true;
		}

		return $automatic
			&& 'document' === strtolower( $headers['dest'] ?? '' )
			&& 'navigate' === strtolower( $headers['mode'] ?? '' )
			&& false !== stripos( $headers['accept'] ?? '', 'text/html' );
	}

	/**
	 * Whether a query argument is present and not switched off.
	 *
	 * @param string|null $value Value of the argument, or null when it is absent.
	 * @return bool True when it asks for something.
	 */
	private static function is_on( ?string $value ): bool {
		return null !== $value && ! in_array( strtolower( $value ), array( '0', 'false', 'off', 'no' ), true );
	}

	/**
	 * Reads a request header.
	 *
	 * @param string $key Name in $_SERVER.
	 * @return string The value, or an empty string.
	 */
	private function header( string $key ): string {
		return isset( $_SERVER[ $key ] ) ? sanitize_text_field( wp_unslash( $_SERVER[ $key ] ) ) : '';
	}
}
