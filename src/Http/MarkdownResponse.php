<?php
/**
 * Sends Markdown with the correct headers (type, canonical, noindex, caching).
 *
 * @package AgentWelcome
 */

declare(strict_types=1);

namespace AgentWelcome\Http;

defined( 'ABSPATH' ) || exit;

/** Sends Markdown and text files with the right headers, and answers conditional requests. */
final class MarkdownResponse {

	/** Content type of a Markdown page. */
	public const MARKDOWN = 'text/markdown; charset=utf-8';
	/** Content type of the llms files and plain messages. */
	public const TEXT = 'text/plain; charset=utf-8';

	/** @var array<string, string> Headers that say what was detected, for debugging. */
	private array $debug = array();

	/**
	 * Adds headers named X-AgentWelcome-Debug-* to whatever is sent next. Does nothing unless AGENTWELCOME_DEBUG is true.
	 *
	 * @param array<string, string> $values Value by name.
	 * @return void
	 */
	public function debug( array $values ): void {
		if ( ! self::debugging() ) {
			return;
		}

		foreach ( $values as $name => $value ) {
			// Headers are ASCII only.
			$this->debug[ 'X-AgentWelcome-Debug-' . $name ] = (string) preg_replace_callback( '/[^\x20-\x7E]+/', static fn( array $found ): string => rawurlencode( $found[0] ), (string) $value );
		}
	}

	/**
	 * @return bool True when AGENTWELCOME_DEBUG is true.
	 */
	public static function debugging(): bool {
		return defined( 'AGENTWELCOME_DEBUG' ) && true === constant( 'AGENTWELCOME_DEBUG' );
	}

	/**
	 * Sends the debug headers on their own, for answers such as redirects.
	 *
	 * @return void
	 */
	public function send_debug(): void {
		$this->send_headers( array() );
	}

	/**
	 * Sends a body with headers for caching and for search engines. Answers 304 when the client already has the current version.
	 *
	 * @param string                $body         Text to send.
	 * @param string                $content_type Content type header.
	 * @param string                $canonical    Address of the HTML page, sent as a canonical link header.
	 * @param int                   $modified     Unix time the content last changed, or 0 when unknown.
	 * @param array<string, string> $extra        Additional headers, which replace the ones above of the same name.
	 * @return void
	 */
	public function send( string $body, string $content_type, string $canonical = '', int $modified = 0, array $extra = array() ): void {
		$etag    = '"' . md5( $body ) . '"';
		$headers = array(
			'Content-Type'           => $content_type,
			'X-Content-Type-Options' => 'nosniff',
			'X-Robots-Tag'           => 'noindex',
			'Cache-Control'          => 'public, max-age=300',
			'ETag'                   => $etag,
		);
		$headers = array_merge( $headers, $extra );

		if ( '' !== $canonical ) {
			$headers['Link'] = '<' . esc_url_raw( $canonical ) . '>; rel="canonical"';
		}

		if ( $modified > 0 ) {
			$headers['Last-Modified'] = gmdate( 'D, d M Y H:i:s', $modified ) . ' GMT';
		}

		$headers = (array) apply_filters( 'agentwelcome_response_headers', $headers );

		if ( $this->is_not_modified( $etag, $modified ) ) {
			status_header( 304 );
			$this->send_headers( $headers );
			return;
		}

		status_header( 200 );
		$this->send_headers( $headers );

		if ( 'HEAD' !== $this->method() ) {
			echo $body; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Served as text, never HTML.
		}
	}

	/**
	 * Sends a 503 asking the client to try again shortly.
	 *
	 * @return void
	 */
	public function unavailable(): void {
		status_header( 503 );
		$this->send_headers(
			array(
				'Content-Type'  => self::TEXT,
				'Retry-After'   => '10',
				'Cache-Control' => 'no-store',
				'X-Robots-Tag'  => 'noindex',
			)
		);

		if ( 'HEAD' !== $this->method() ) {
			echo esc_html__( 'The Markdown for this page is being generated. Try again in a few seconds.', 'agentwelcome' ) . "\n";
		}
	}

	/**
	 * Sends a plain text 404 for a Markdown address that is not served.
	 *
	 * @return void
	 */
	public function not_found(): void {
		status_header( 404 );
		$this->send_headers(
			array(
				'Content-Type'           => self::TEXT,
				'X-Content-Type-Options' => 'nosniff',
				'Cache-Control'          => 'no-store',
				'X-Robots-Tag'           => 'noindex',
			)
		);

		if ( 'HEAD' !== $this->method() ) {
			echo esc_html__( 'Not found. There is no Markdown version of this address.', 'agentwelcome' ) . "\n";
		}
	}

	/**
	 * Sends the headers, with line breaks removed from the values.
	 *
	 * @param array<string, string> $headers Header values by name.
	 * @return void
	 */
	private function send_headers( array $headers ): void {
		foreach ( array_merge( $headers, $this->debug ) as $name => $value ) {
			header( $name . ': ' . str_replace( array( "\r", "\n" ), '', (string) $value ) );
		}
	}

	/**
	 * Method of the current request.
	 *
	 * @return string Upper case method, GET when unknown.
	 */
	private function method(): string {
		return isset( $_SERVER['REQUEST_METHOD'] ) ? strtoupper( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) ) : 'GET';
	}

	/**
	 * Whether the request shows the client already has the current version.
	 *
	 * @param string $etag     ETag of the response.
	 * @param int    $modified Unix time the content last changed.
	 * @return bool True when a 304 can be sent.
	 */
	private function is_not_modified( string $etag, int $modified ): bool {
		if ( isset( $_SERVER['HTTP_IF_NONE_MATCH'] ) ) {
			$sent = trim( sanitize_text_field( wp_unslash( $_SERVER['HTTP_IF_NONE_MATCH'] ) ) );

			return $sent === $etag || 'W/' . $etag === $sent;
		}

		if ( $modified > 0 && isset( $_SERVER['HTTP_IF_MODIFIED_SINCE'] ) ) {
			$since = strtotime( sanitize_text_field( wp_unslash( $_SERVER['HTTP_IF_MODIFIED_SINCE'] ) ) );

			return false !== $since && $since >= $modified;
		}

		return false;
	}
}
