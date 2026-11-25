<?php
/**
 * Per-page, ID/URL pattern and taxonomy term exclusions.
 *
 * @package AgentWelcome
 */

declare(strict_types=1);

namespace AgentWelcome\Content;

use AgentWelcome\Settings\Options;
use WP_Post;

defined( 'ABSPATH' ) || exit;

/** Pages the site owner chose to leave out, and whether pages that say noindex are left out too. */
final class Exclusions {

	/**
	 * @param Options $options Saved settings.
	 */
	public function __construct( private Options $options ) {}

	/**
	 * Finds the rule that leaves a page out.
	 *
	 * @param WP_Post $post The page.
	 * @return string|null A reason code, or null when no rule applies.
	 */
	public function matched( WP_Post $post ): ?string {
		if ( get_post_meta( $post->ID, PostMeta::EXCLUDE, true ) ) {
			return 'excluded_page';
		}

		if ( $this->matches_pattern( $post ) ) {
			return 'excluded_pattern';
		}

		if ( $this->matches_term( $post ) ) {
			return 'excluded_term';
		}

		return null;
	}

	/**
	 * Whether the page matches an ID or a URL pattern from the exclusion list.
	 *
	 * @param WP_Post $post The page.
	 * @return bool True when it matches.
	 */
	private function matches_pattern( WP_Post $post ): bool {
		$lines = preg_split( '/[\r\n,]+/', (string) $this->options->get( 'excluded_posts', '' ) );
		$path  = $this->relative_path( $post );

		foreach ( (array) $lines as $line ) {
			$line = trim( $line );

			if ( '' === $line ) {
				continue;
			}

			if ( ctype_digit( $line ) ) {
				if ( (int) $line === $post->ID ) {
					return true;
				}
				continue;
			}

			if ( fnmatch( trim( $line, '/' ), $path ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Whether the page has one of the excluded terms.
	 *
	 * @param WP_Post $post The page.
	 * @return bool True when it has.
	 */
	private function matches_term( WP_Post $post ): bool {
		$terms = array_map( 'absint', (array) $this->options->get( 'excluded_terms', array() ) );

		$taxonomies = get_object_taxonomies( $post->post_type );

		if ( empty( $terms ) || empty( $taxonomies ) ) {
			return false;
		}

		$assigned = wp_get_object_terms( $post->ID, $taxonomies, array( 'fields' => 'ids' ) );

		return ! is_wp_error( $assigned ) && array() !== array_intersect( $terms, array_map( 'intval', $assigned ) );
	}

	/**
	 * Path of the page address, without the folder of the site.
	 *
	 * @param WP_Post $post The page.
	 * @return string Path without slashes at the ends.
	 */
	private function relative_path( WP_Post $post ): string {
		$path = (string) wp_parse_url( get_permalink( $post ), PHP_URL_PATH );
		$home = trim( (string) wp_parse_url( home_url(), PHP_URL_PATH ), '/' );
		$path = trim( $path, '/' );

		if ( '' !== $home && 0 === strpos( $path, $home ) ) {
			$path = ltrim( substr( $path, strlen( $home ) ), '/' );
		}

		return $path;
	}

	/**
	 * Whether pages that say noindex are left out.
	 *
	 * @return bool True when the setting is on.
	 */
	public function leaves_out_noindex(): bool {
		return (bool) $this->options->get( 'exclude_noindex', true );
	}

	/**
	 * Whether pages that visitors cannot read are left out.
	 *
	 * @return bool True when the setting is on.
	 */
	public function leaves_out_restricted(): bool {
		return (bool) $this->options->get( 'exclude_restricted', true );
	}
}
