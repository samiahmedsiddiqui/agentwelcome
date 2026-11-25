<?php
/**
 * Finds the AI crawlers that robots.txt keeps away from llms.txt and the Markdown pages.
 *
 * @package AgentWelcome
 */

declare(strict_types=1);

namespace AgentWelcome\Robots;

use AgentWelcome\Http\Urls;
use AgentWelcome\Rendering\UrlResolver;
use AgentWelcome\Settings\Options;

defined( 'ABSPATH' ) || exit;

/** Reads the robots.txt of the site and lists the known AI crawlers it blocks. It never changes robots.txt. */
final class CrawlerCheck {

	/** Transient that keeps the robots.txt content. */
	private const TRANSIENT = 'agentwelcome_robots_txt';
	/** Seconds the content is kept. */
	private const TTL = 300;
	/** Redirects followed before giving up. */
	private const MAX_REDIRECTS = 3;
	/** Known AI crawlers. */
	public const CRAWLERS = array( 'GPTBot', 'ChatGPT-User', 'OAI-SearchBot', 'ClaudeBot', 'Claude-Web', 'anthropic-ai', 'PerplexityBot', 'Google-Extended', 'Applebot-Extended', 'CCBot', 'Bytespider', 'cohere-ai', 'Meta-ExternalAgent' );

	/**
	 * @param Options $options Saved settings.
	 * @param Urls    $urls    Builds the page addresses.
	 */
	public function __construct(
		private Options $options,
		private Urls $urls
	) {}

	/**
	 * Crawlers that are not allowed to read llms.txt or the Markdown pages.
	 *
	 * @return list<string> Crawler names, empty when none is blocked or robots.txt could not be read.
	 */
	public function blocked(): array {
		$body = $this->body();

		if ( null === $body || '' === trim( $body ) ) {
			return array();
		}

		$rules   = new RobotsRules( $body );
		$paths   = $this->paths();
		$blocked = array();

		/**
		 * Filters the user agents of the AI crawlers that are checked against robots.txt.
		 *
		 * @param string[] $crawlers User agent names.
		 */
		$crawlers = (array) apply_filters( 'agentwelcome_ai_crawlers', self::CRAWLERS );

		foreach ( $crawlers as $crawler ) {
			if ( ! is_string( $crawler ) || '' === $crawler ) {
				continue;
			}

			foreach ( $paths as $path ) {
				if ( ! $rules->allows( $crawler, $path ) ) {
					$blocked[] = $crawler;
					break;
				}
			}
		}

		return $blocked;
	}

	/**
	 * Paths of llms.txt, the Markdown homepage and one Markdown page.
	 *
	 * @return list<string> Paths.
	 */
	private function paths(): array {
		$urls  = array( home_url( '/llms.txt' ), home_url( '/' . $this->urls->home_file() ) );
		$pages = get_posts(
			array(
				'post_type'      => 'page',
				'post_status'    => 'publish',
				'posts_per_page' => 1,
				'orderby'        => 'ID',
				'order'          => 'ASC',
				'no_found_rows'  => true,
			)
		);

		if ( ! empty( $pages ) ) {
			$urls[] = $this->urls->markdown_url( $pages[0] );
		}

		$paths = array();

		foreach ( $urls as $url ) {
			$path = null === $url ? '' : (string) wp_parse_url( $url, PHP_URL_PATH );

			if ( '' !== $path ) {
				$paths[] = $path;
			}
		}

		return $paths;
	}

	/**
	 * Content of robots.txt, kept for a few minutes.
	 *
	 * @return string|null The content, an empty string when there is no file, or null when it could not be read.
	 */
	private function body(): ?string {
		$saved = get_transient( self::TRANSIENT );

		if ( is_string( $saved ) ) {
			return $saved;
		}

		$body = $this->fetch( home_url( '/robots.txt' ) );

		if ( null !== $body ) {
			set_transient( self::TRANSIENT, $body, self::TTL );
		}

		return $body;
	}

	/**
	 * Requests robots.txt from the site itself, following redirects on the same site.
	 *
	 * @param string $url Address of robots.txt.
	 * @return string|null The content, an empty string for a missing file, or null when the request failed.
	 */
	private function fetch( string $url ): ?string {
		$auth   = $this->options->http_auth();
		$secure = 'https' === wp_parse_url( $url, PHP_URL_SCHEME );
		$args   = array(
			'timeout'             => (int) apply_filters( 'agentwelcome_loopback_timeout', 15 ),
			'redirection'         => 0,
			'sslverify'           => (bool) apply_filters( 'https_local_ssl_verify', false ), // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Core hook.
			'limit_response_size' => MB_IN_BYTES,
			'cookies'             => array(),
			'user-agent'          => 'AgentWelcome/' . AGENTWELCOME_VERSION . '; ' . home_url( '/' ),
			'headers'             => array( 'Cache-Control' => 'no-cache' ),
		);

		if ( null !== $auth ) {
			$args['headers']['Authorization'] = 'Basic ' . base64_encode( $auth['user'] . ':' . $auth['password'] ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Required by the HTTP Basic scheme.
		}

		for ( $hop = 0; $hop <= self::MAX_REDIRECTS; $hop++ ) {
			if ( ! $this->is_same_site( $url ) || ( null !== $auth && $secure && 'https' !== wp_parse_url( $url, PHP_URL_SCHEME ) ) ) {
				return null;
			}

			$response = wp_remote_get( $url, $args );

			if ( is_wp_error( $response ) ) {
				return null;
			}

			$code = (int) wp_remote_retrieve_response_code( $response );

			if ( in_array( $code, array( 301, 302, 303, 307, 308 ), true ) ) {
				$location = (string) wp_remote_retrieve_header( $response, 'location' );

				if ( '' === $location ) {
					return null;
				}

				$url = UrlResolver::resolve( $url, $location );
				continue;
			}

			if ( 200 === $code ) {
				return (string) wp_remote_retrieve_body( $response );
			}

			// A missing file (4xx other than a login request) puts no limits on crawlers.
			return $code >= 400 && $code < 500 && 401 !== $code && 403 !== $code ? '' : null;
		}

		return null;
	}

	/**
	 * Whether an address is on this site, with the same host and port.
	 *
	 * @param string $url Address.
	 * @return bool True when it is.
	 */
	private function is_same_site( string $url ): bool {
		$target = wp_parse_url( $url );
		$home   = wp_parse_url( home_url() );

		if ( ! is_array( $target ) || ! is_array( $home ) || ! in_array( $target['scheme'] ?? '', array( 'http', 'https' ), true ) ) {
			return false;
		}

		return strtolower( (string) ( $target['host'] ?? '' ) ) === strtolower( (string) ( $home['host'] ?? '' ) )
			&& ( $target['port'] ?? null ) === ( $home['port'] ?? null );
	}
}
