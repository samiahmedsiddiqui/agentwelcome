<?php
/**
 * Captures the final front-end HTML of a post.
 *
 * Fetches the public page as a logged-out visitor so theme templates, ACF output,
 * page builders and shortcodes are all included. Falls back to the_content.
 *
 * @package AgentWelcome
 */

declare(strict_types=1);

namespace AgentWelcome\Rendering;

use AgentWelcome\Content\Language;
use AgentWelcome\Http\Urls;
use AgentWelcome\Settings\Options;
use WP_Post;

defined( 'ABSPATH' ) || exit;

/** Reads the HTML of a page the way a visitor gets it, with a request to the site itself. When that fails, the post content is used instead. */
final class Renderer {

	/** Redirects followed before giving up. */
	private const MAX_REDIRECTS = 3;

	/**
	 * @param Options $options Saved settings.
	 * @param Urls    $urls    Builds the page addresses.
	 */
	public function __construct(
		private Options $options,
		private Urls $urls
	) {}

	/**
	 * Reads the HTML of a page.
	 *
	 * @param WP_Post $post  The page.
	 * @param int     $paged Number of the paginated view, 1 for the page itself.
	 * @return RenderResult The HTML and how it was obtained.
	 */
	public function render( WP_Post $post, int $paged = 1 ): RenderResult {
		return Language::run( Language::of( $post ), fn(): RenderResult => $this->read( $post, $paged ) );
	}

	/**
	 * Reads the HTML with the language of the page active.
	 *
	 * @param WP_Post $post  The page.
	 * @param int     $paged Number of the paginated view, 1 for the page itself.
	 * @return RenderResult The HTML and how it was obtained.
	 */
	private function read( WP_Post $post, int $paged ): RenderResult {
		$url = $this->urls->view_url( $post, $paged );

		if ( null === $url ) {
			return $this->fallback( $post, 'no_permalink' );
		}

		if ( ! apply_filters( 'agentwelcome_use_loopback', true, $post ) ) {
			return $this->fallback( $post, '' );
		}

		$result = $this->loopback( $post, $url );

		return $result instanceof RenderResult ? $result : $this->fallback( $post, $result );
	}

	/**
	 * Requests the page address, following redirects on the same site. Saved credentials are not sent over http when the first address was https.
	 *
	 * @param WP_Post $post The page.
	 * @param string  $url  Address of the page.
	 * @return RenderResult|string The result, or an error code.
	 */
	private function loopback( WP_Post $post, string $url ): RenderResult|string {
		$auth   = $this->options->http_auth();
		$secure = 'https' === wp_parse_url( $url, PHP_URL_SCHEME );
		$page   = $url;

		for ( $hop = 0; $hop <= self::MAX_REDIRECTS; $hop++ ) {
			if ( ! $this->is_same_site( $url ) ) {
				return $this->restricted( $post, Restriction::from_redirect( $page, $url ) ) ?? 'redirect_off_site';
			}

			if ( null !== $auth && $secure && 'https' !== wp_parse_url( $url, PHP_URL_SCHEME ) ) {
				return 'redirect_insecure';
			}

			$response = wp_remote_get( add_query_arg( 'agentwelcome_render', (string) wp_rand(), $url ), $this->request_args( $auth ) );

			if ( is_wp_error( $response ) ) {
				return 'request_failed:' . $response->get_error_message();
			}

			$code = (int) wp_remote_retrieve_response_code( $response );

			if ( in_array( $code, array( 301, 302, 303, 307, 308 ), true ) ) {
				$location = (string) wp_remote_retrieve_header( $response, 'location' );

				if ( '' === $location ) {
					return 'redirect_invalid';
				}

				$url = UrlResolver::resolve( $url, $location );
				continue;
			}

			// A 401 that asks for HTTP Basic credentials is a protected site, not a restricted page.
			$basic      = null !== $auth || 0 === stripos( (string) wp_remote_retrieve_header( $response, 'www-authenticate' ), 'basic' );
			$restricted = $this->restricted( $post, Restriction::from_redirect( $page, $url ) ?? Restriction::from_status( $code, $basic ) );

			if ( null !== $restricted ) {
				return $restricted;
			}

			if ( 401 === $code ) {
				return null === $auth ? 'http_auth_required' : 'http_auth_failed';
			}

			$body = (string) wp_remote_retrieve_body( $response );

			if ( 200 !== $code || '' === $body ) {
				return 'http_' . $code;
			}

			$robots = wp_remote_retrieve_header( $response, 'x-robots-tag' );

			return new RenderResult( self::without_marker( $body ), RenderResult::LOOPBACK, '', implode( "\n", (array) $robots ) );
		}

		return 'too_many_redirects';
	}

	/**
	 * Removes the argument of the render request from the links that copied it, such as pagination links.
	 *
	 * @param string $html HTML of the page.
	 * @return string The HTML without the argument.
	 */
	public static function without_marker( string $html ): string {
		return (string) preg_replace(
			array( '/\?agentwelcome_render=\d+&(?:#0?38;|amp;)?/', '/(?:\?|&(?:#0?38;|amp;)?)agentwelcome_render=\d+/' ),
			array( '?', '' ),
			$html
		);
	}

	/**
	 * Builds the result of a page that visitors cannot read, unless the setting is off or a filter includes the page.
	 *
	 * @param WP_Post     $post   The page.
	 * @param string|null $reason Why visitors cannot read it, or null when they can.
	 * @return RenderResult|null The result, or null when the page is treated as readable.
	 */
	private function restricted( WP_Post $post, ?string $reason ): ?RenderResult {
		if ( null === $reason || ! $this->options->get( 'exclude_restricted', true ) ) {
			return null;
		}

		/**
		 * Filters whether a page that visitors cannot read is left out. Return false to include the page.
		 *
		 * @param bool    $restricted Whether the page is left out.
		 * @param WP_Post $post       The page.
		 * @param string  $reason     Short code: http_401, http_403 or redirect.
		 */
		if ( ! apply_filters( 'agentwelcome_is_restricted', true, $post, Restriction::code( $reason ) ) ) {
			return null;
		}

		return new RenderResult( '', RenderResult::RESTRICTED, '', '', $reason );
	}

	/**
	 * Builds the arguments of the request.
	 *
	 * @param array{user: string, password: string}|null $auth HTTP Basic credentials.
	 * @return array<string, mixed> Arguments for wp_remote_get().
	 */
	private function request_args( ?array $auth ): array {
		$headers = array(
			'Accept'                => 'text/html,application/xhtml+xml',
			'Cache-Control'         => 'no-cache',
			'X-AgentWelcome-Render' => '1',
		);

		if ( null !== $auth ) {
			$headers['Authorization'] = 'Basic ' . base64_encode( $auth['user'] . ':' . $auth['password'] ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Required by the HTTP Basic scheme.
		}

		return array(
			'timeout'             => (int) apply_filters( 'agentwelcome_loopback_timeout', 15 ),
			'redirection'         => 0,
			'sslverify'           => (bool) apply_filters( 'https_local_ssl_verify', false ), // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Core hook.
			'limit_response_size' => 5 * MB_IN_BYTES,
			'cookies'             => array(),
			'user-agent'          => 'AgentWelcome/' . AGENTWELCOME_VERSION . '; ' . home_url( '/' ),
			'headers'             => $headers,
		);
	}

	/**
	 * Whether an address is on this site, with the same host and port.
	 *
	 * @param string $url Address.
	 * @return bool True when it is.
	 */
	private function is_same_site( string $url ): bool {
		$target = wp_parse_url( $url );
		$home   = wp_parse_url( Language::home_url() );

		if ( ! is_array( $target ) || ! is_array( $home ) || ! in_array( $target['scheme'] ?? '', array( 'http', 'https' ), true ) ) {
			return false;
		}

		return strtolower( (string) ( $target['host'] ?? '' ) ) === strtolower( (string) ( $home['host'] ?? '' ) )
			&& ( $target['port'] ?? null ) === ( $home['port'] ?? null );
	}

	/**
	 * Builds the HTML from the post content, with the shortcodes and blocks processed.
	 *
	 * @param WP_Post $item The page.
	 * @param string  $code Error code of the failed request, or an empty string when the request was not tried.
	 * @return RenderResult The content, with a notice.
	 */
	private function fallback( WP_Post $item, string $code ): RenderResult {
		global $post;

		$original = $post;
		$post     = $item; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Shortcodes read the global post.

		setup_postdata( $item );
		$content = apply_filters( 'the_content', $item->post_content ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Core hook.
		wp_reset_postdata();

		$post = $original; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Restores the global.

		return new RenderResult( '<main>' . $content . '</main>', RenderResult::FALLBACK, $this->notice( $code ) );
	}

	/**
	 * Turns an error code into a sentence for the admin.
	 *
	 * @param string $code Error code.
	 * @return string The sentence, or an empty string for no error.
	 */
	private function notice( string $code ): string {
		if ( '' === $code ) {
			return '';
		}

		if ( 0 === strpos( $code, 'request_failed:' ) ) {
			/* translators: %s: error message. */
			return sprintf( __( 'The page could not be fetched (%s). Using the post content instead.', 'agentwelcome' ), substr( $code, 15 ) );
		}

		switch ( $code ) {
			case 'http_auth_required':
				return __( 'The site asked for HTTP Basic authentication. Enable it in the AgentWelcome settings. Using the post content instead.', 'agentwelcome' );
			case 'http_auth_failed':
				return __( 'The HTTP Basic authentication credentials were rejected. Using the post content instead.', 'agentwelcome' );
			case 'redirect_insecure':
				return __( 'The page redirected from https to http, so the saved credentials were not sent. Using the post content instead.', 'agentwelcome' );
			case 'redirect_off_site':
				return __( 'The page redirected to a different site. Using the post content instead.', 'agentwelcome' );
			case 'no_permalink':
				return __( 'The post has no pretty permalink. Using the post content instead.', 'agentwelcome' );
			default:
				/* translators: %s: error code. */
				return sprintf( __( 'The page could not be fetched (%s). Using the post content instead.', 'agentwelcome' ), $code );
		}
	}
}
