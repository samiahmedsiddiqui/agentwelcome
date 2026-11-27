<?php
/**
 * Serves the Markdown pages and llms.txt in each language of a WPML site.
 *
 * @package AgentWelcome
 */

declare(strict_types=1);

namespace AgentWelcome\Integrations;

use AgentWelcome\Content\Language;
use AgentWelcome\Contracts\Hookable;
use WP_Post;

defined( 'ABSPATH' ) || exit;

/** Works with the language directory and domain-per-language formats of WPML. The query argument format is not supported. */
final class Wpml implements Hookable {

	/** @var array<string, string>|null Home address by language code, the default language first. */
	private ?array $homes = null;

	/** @var array<int, mixed> Languages that were active before each switch. */
	private array $previous = array();

	/** @var string Address of the request as it arrived. */
	private string $uri = '';

	/**
	 * @return void
	 */
	public function register(): void {
		if ( ! defined( 'ICL_SITEPRESS_VERSION' ) ) {
			return;
		}

		// Kept now, because the language directory may be removed from it later in the request.
		$this->uri = isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';

		add_filter( 'agentwelcome_site_address', array( $this, 'site_address' ) );
		add_filter( 'agentwelcome_request', array( $this, 'request' ) );
		add_filter( 'agentwelcome_url_language', array( $this, 'url_language' ), 10, 2 );
		add_filter( 'agentwelcome_post_language', array( $this, 'post_language' ), 10, 2 );
		add_action( 'agentwelcome_switch_language', array( $this, 'switch_to' ) );
		add_action( 'agentwelcome_restore_language', array( $this, 'restore' ) );
		add_filter( 'agentwelcome_languages', array( $this, 'languages' ) );
		add_filter( 'agentwelcome_home_url', array( $this, 'home_url' ), 10, 2 );
		add_filter( 'agentwelcome_permalink', array( $this, 'permalink' ), 10, 2 );
		add_filter( 'agentwelcome_front_page_id', array( $this, 'translate_page_id' ) );
		add_filter( 'agentwelcome_resolve_post', array( $this, 'resolve_post' ) );
		add_filter( 'agentwelcome_is_eligible', array( $this, 'is_eligible' ), 10, 2 );
		add_filter( 'agentwelcome_excluded_terms', array( $this, 'excluded_terms' ), 10, 2 );
		add_filter( 'agentwelcome_page_count', array( $this, 'page_count' ), 5, 2 );
		add_filter( 'agentwelcome_front_matter_language', array( $this, 'front_matter_language' ), 10, 2 );
		add_filter( 'agentwelcome_debug_headers', array( $this, 'debug_headers' ) );
		add_filter( 'allowed_redirect_hosts', array( $this, 'redirect_hosts' ) );
	}

	/**
	 * Finds the language of an address by comparing it with the home address of each language. The longest matching directory wins.
	 *
	 * @param string                $host  Host of the address, or an empty string when unknown.
	 * @param string                $path  Path of the address.
	 * @param array<string, string> $homes Home address by language code, the default language first.
	 * @return array{language: string, path: string}|null The language and the path below its home, or null when the address is under none.
	 */
	public static function match( string $host, string $path, array $homes ): ?array {
		$path  = trim( (string) preg_replace( '#^/?index\.php(/|$)#', '/', $path ), '/' );
		$host  = self::host( $host );
		$found = null;
		$best  = array( -1, -1 );

		foreach ( $homes as $language => $home ) {
			$parts  = (array) wp_parse_url( $home );
			$prefix = trim( (string) ( $parts['path'] ?? '' ), '/' );

			if ( '' !== $prefix && $path !== $prefix && ! str_starts_with( $path, $prefix . '/' ) ) {
				continue;
			}

			// A matching host counts before the length of the directory.
			$score = array( (int) ( self::host( (string) ( $parts['host'] ?? '' ) ) === $host ), strlen( $prefix ) );

			if ( $score > $best ) {
				$best  = $score;
				$found = array(
					'language' => (string) $language,
					'path'     => trim( substr( $path, strlen( $prefix ) ), '/' ),
				);
			}
		}

		return $found;
	}

	/**
	 * Writes percent escapes in lower case, as WordPress does in permalinks.
	 *
	 * @param string $path Path.
	 * @return string Path.
	 */
	public static function lower_escapes( string $path ): string {
		return (string) preg_replace_callback( '/%[0-9A-F]{2}/i', static fn( array $found ): string => strtolower( $found[0] ), $path );
	}

	/**
	 * Host without a port or "www.", in lower case.
	 *
	 * @param string $host Host.
	 * @return string Host.
	 */
	private static function host( string $host ): string {
		return (string) preg_replace( '/^www\.|:\d+$/', '', strtolower( $host ) );
	}

	/**
	 * Home address of every active language.
	 *
	 * @return array<string, string> Address by language code, the default language first.
	 */
	private function homes(): array {
		if ( null !== $this->homes ) {
			return $this->homes;
		}

		$default = (string) apply_filters( 'wpml_default_language', null ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WPML's hook.
		$codes   = array_keys( $this->active() );
		$homes   = array();

		if ( in_array( $default, $codes, true ) ) {
			$codes = array_unique( array_merge( array( $default ), $codes ) );
		}

		foreach ( $codes as $code ) {
			$home = apply_filters( 'wpml_permalink', trailingslashit( (string) get_option( 'home' ) ), $code, true ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WPML's hook.

			// An address with a query argument means the format that is not supported.
			if ( is_string( $home ) && '' !== $home && ! str_contains( $home, '?' ) ) {
				$homes[ (string) $code ] = untrailingslashit( $home );
			}
		}

		if ( array() !== $homes ) {
			$this->homes = $homes;
		}

		return $homes;
	}

	/**
	 * Active languages as WPML describes them.
	 *
	 * @return array<string, array<string, mixed>> Details by language code.
	 */
	private function active(): array {
		$active = apply_filters( 'wpml_active_languages', null, array( 'skip_missing' => 0 ) ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WPML's hook.

		return is_array( $active ) ? $active : array();
	}

	/**
	 * Code of the default language.
	 *
	 * @return string The code, or an empty string.
	 */
	private function default_language(): string {
		return (string) ( array_key_first( $this->homes() ) ?? '' );
	}

	/**
	 * Language that WPML has active.
	 *
	 * @return string The code, or an empty string.
	 */
	private function current(): string {
		return (string) apply_filters( 'wpml_current_language', null ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WPML's hook.
	}

	/**
	 * The address of the site itself, which stays the same in every language.
	 *
	 * @return string The saved home address.
	 */
	public function site_address(): string {
		return (string) get_option( 'home' );
	}

	/**
	 * Reads the language and the path of the current request.
	 *
	 * @param array<string, mixed> $request The request as WordPress read it.
	 * @return array<string, mixed> The request with the language, and the path below the home of that language.
	 */
	public function request( array $request ): array {
		$homes = $this->homes();
		$host  = isset( $_SERVER['HTTP_HOST'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ) ) : '';
		$found = self::match( $host, (string) wp_parse_url( $this->uri, PHP_URL_PATH ), $homes );

		if ( null === $found ) {
			return $request;
		}

		$default = $this->default_language();
		$current = $this->current();
		$source  = 'address';

		// The directory was already taken out of the address, so ask WPML which language it was.
		if ( $found['language'] === $default && $current !== $default && isset( $homes[ $current ] ) ) {
			$found['language'] = $current;
			$source            = 'wpml';
		}

		$other = $found['language'] !== $default;

		return array_merge(
			$request,
			array(
				'path'      => self::lower_escapes( $found['path'] ),
				'language'  => $found['language'],
				'source'    => $source,
				// The llms files exist once, for the default language.
				'index_url' => $other && 'address' === $source ? $homes[ $default ] : '',
				'shared'    => ! $other,
			)
		);
	}

	/**
	 * @param mixed $language Language found so far.
	 * @param mixed $url      Full address or path.
	 * @return mixed Language of the address.
	 */
	public function url_language( mixed $language, mixed $url = '' ): mixed {
		$parts = (array) wp_parse_url( (string) $url );
		$host  = (string) ( $parts['host'] ?? wp_parse_url( (string) get_option( 'home' ), PHP_URL_HOST ) );
		$found = self::match( $host, (string) ( $parts['path'] ?? '' ), $this->homes() );

		return null === $found ? $language : $found['language'];
	}

	/**
	 * @param mixed $language Language found so far.
	 * @param mixed $post     The page.
	 * @return mixed Language of the page. Content that WPML does not translate belongs to the default language.
	 */
	public function post_language( mixed $language, mixed $post = null ): mixed {
		if ( ! $post instanceof WP_Post ) {
			return $language;
		}

		if ( false === apply_filters( 'wpml_is_translated_post_type', null, $post->post_type ) ) { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WPML's hook.
			return $this->default_language();
		}

		$code = $this->details( $post )['language_code'] ?? '';

		return is_string( $code ) && '' !== $code ? $code : $this->default_language();
	}

	/**
	 * @param WP_Post $post The page.
	 * @return array<string, mixed> What WPML says about the language of the page.
	 */
	private function details( WP_Post $post ): array {
		$details = apply_filters( 'wpml_post_language_details', null, $post->ID ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WPML's hook.

		return is_array( $details ) ? $details : array();
	}

	/**
	 * @param string $language Language code.
	 * @return void
	 */
	public function switch_to( string $language ): void {
		$this->previous[] = apply_filters( 'wpml_current_language', null ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WPML's hook.

		do_action( 'wpml_switch_language', $language ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WPML's hook.
	}

	/**
	 * @return void
	 */
	public function restore(): void {
		do_action( 'wpml_switch_language', array_pop( $this->previous ) ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WPML's hook.
	}

	/**
	 * @return array<string, string> Name of each language, in that language, the default one first.
	 */
	public function languages(): array {
		$active = $this->active();
		$names  = array();

		foreach ( array_keys( $this->homes() ) as $code ) {
			$names[ $code ] = (string) ( $active[ $code ]['native_name'] ?? $code );
		}

		return $names;
	}

	/**
	 * @param mixed $url  Home address so far.
	 * @param mixed $path Path below the home address.
	 * @return mixed Home address of the active language.
	 */
	public function home_url( mixed $url, mixed $path = '' ): mixed {
		$home = $this->homes()[ $this->current() ] ?? null;

		if ( null === $home ) {
			return $url;
		}

		return '' === (string) $path ? $home : $home . '/' . ltrim( (string) $path, '/' );
	}

	/**
	 * @param mixed $url  Permalink so far.
	 * @param mixed $post The page.
	 * @return mixed Permalink in the language of the page.
	 */
	public function permalink( mixed $url, mixed $post = null ): mixed {
		if ( ! is_string( $url ) || ! $post instanceof WP_Post ) {
			return $url;
		}

		$language = Language::of( $post );
		$home     = $this->homes()[ $language ] ?? null;

		if ( null === $home ) {
			return $url;
		}

		if ( 'page' === get_option( 'show_on_front' ) && $this->translate_page_id( (int) get_option( 'page_on_front' ) ) === $post->ID ) {
			return trailingslashit( $home );
		}

		return apply_filters( 'wpml_permalink', $url, $language, true ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WPML's hook.
	}

	/**
	 * @param mixed $id Page ID.
	 * @return int ID of the translation in the active language, or 0 when there is none.
	 */
	public function translate_page_id( mixed $id ): int {
		$language = $this->current();

		if ( ! isset( $this->homes()[ $language ] ) || (int) $id <= 0 ) {
			return (int) $id;
		}

		return (int) apply_filters( 'wpml_object_id', (int) $id, 'page', false, $language ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WPML's hook.
	}

	/**
	 * Replaces a page of another language with its translation, so default language content is never served under a language.
	 *
	 * @param mixed $post The page found for the path.
	 * @return mixed The page in the active language, or null when it has no translation.
	 */
	public function resolve_post( mixed $post ): mixed {
		$language = $this->current();

		if ( ! $post instanceof WP_Post || ! isset( $this->homes()[ $language ] ) || Language::of( $post ) === $language ) {
			return $post;
		}

		$id = (int) apply_filters( 'wpml_object_id', $post->ID, $post->post_type, false, $language ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WPML's hook.

		if ( $id <= 0 ) {
			return null;
		}

		$translation = get_post( $id );

		return $translation instanceof WP_Post && Language::of( $translation ) === $language ? $translation : null;
	}

	/**
	 * Leaves out pages of a language that is not active or has no address of its own.
	 *
	 * @param mixed $eligible Whether the page is served so far.
	 * @param mixed $post     The page.
	 * @return mixed False for such a page, otherwise the value unchanged.
	 */
	public function is_eligible( mixed $eligible, mixed $post = null ): mixed {
		if ( ! $eligible || ! $post instanceof WP_Post || array() === $this->homes() ) {
			return $eligible;
		}

		return isset( $this->homes()[ Language::of( $post ) ] );
	}

	/**
	 * Adds the translations of the excluded terms.
	 *
	 * @param mixed $terms Term IDs.
	 * @param mixed $post  The page.
	 * @return mixed Term IDs.
	 */
	public function excluded_terms( mixed $terms, mixed $post = null ): mixed {
		if ( ! is_array( $terms ) || ! $post instanceof WP_Post ) {
			return $terms;
		}

		$language = Language::of( $post );
		$all      = $terms;

		foreach ( get_object_taxonomies( $post->post_type ) as $taxonomy ) {
			foreach ( $terms as $term ) {
				$all[] = (int) apply_filters( 'wpml_object_id', (int) $term, $taxonomy, false, $language ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WPML's hook.
			}
		}

		return array_values( array_unique( array_filter( $all ) ) );
	}

	/**
	 * Counts the views of the blog page from the posts of its language only.
	 *
	 * @param mixed $count Number of views so far.
	 * @param mixed $post  The page.
	 * @return mixed Number of views.
	 */
	public function page_count( mixed $count, mixed $post = null ): mixed {
		if ( ! $post instanceof WP_Post || $this->translate_page_id( (int) get_option( 'page_for_posts' ) ) !== $post->ID ) {
			return $count;
		}

		$posts = new \WP_Query(
			array(
				'post_type'      => 'post',
				'post_status'    => 'publish',
				'posts_per_page' => 1,
				'fields'         => 'ids',
			)
		);

		return max( 1, (int) ceil( $posts->found_posts / max( 1, (int) get_option( 'posts_per_page' ) ) ), (int) get_post_meta( $post->ID, \AgentWelcome\Content\PostMeta::VIEWS, true ) );
	}

	/**
	 * @param mixed $language Language of the site.
	 * @param mixed $post     The page.
	 * @return mixed Language of the page, such as ar or zh-CN.
	 */
	public function front_matter_language( mixed $language, mixed $post = null ): mixed {
		if ( ! $post instanceof WP_Post ) {
			return $language;
		}

		$code   = Language::of( $post );
		$locale = (string) ( $this->details( $post )['locale'] ?? $this->active()[ $code ]['default_locale'] ?? '' );
		$own    = '' === $locale ? $code : str_replace( '_', '-', $locale );

		return '' === $own ? $language : $own;
	}

	/**
	 * @param array<string, string> $headers Debug headers.
	 * @return array<string, string> Debug headers with what WPML says.
	 */
	public function debug_headers( array $headers ): array {
		$headers['WPML-Current']   = $this->current();
		$headers['WPML-Languages'] = implode( ', ', array_map( static fn( string $code, string $home ): string => $code . '=' . $home, array_keys( $this->homes() ), $this->homes() ) );

		return $headers;
	}

	/**
	 * Allows redirects to the domain of each language.
	 *
	 * @param string[] $hosts Allowed hosts.
	 * @return string[] Allowed hosts.
	 */
	public function redirect_hosts( array $hosts ): array {
		foreach ( $this->homes() as $home ) {
			$hosts[] = (string) wp_parse_url( $home, PHP_URL_HOST );
		}

		return $hosts;
	}
}
