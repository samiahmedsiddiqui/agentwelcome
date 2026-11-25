<?php
/**
 * Decides whether a post may be exposed as Markdown.
 *
 * @package AgentWelcome
 */

declare(strict_types=1);

namespace AgentWelcome\Content;

use AgentWelcome\Cache\Entry;
use AgentWelcome\Http\Urls;
use WP_Post;

defined( 'ABSPATH' ) || exit;

/** Decides whether a page gets a Markdown version. */
final class Eligibility {

	/**
	 * @param PostTypes  $post_types Post types that are served.
	 * @param Exclusions $exclusions Pages that are left out.
	 * @param Urls       $urls       Builds the page addresses.
	 */
	public function __construct(
		private PostTypes $post_types,
		private Exclusions $exclusions,
		private Urls $urls
	) {}

	/**
	 * Whether the page is served.
	 *
	 * @param WP_Post $post The page.
	 * @return bool True when it is.
	 */
	public function is_eligible( WP_Post $post ): bool {
		return null === $this->reason( $post );
	}

	/**
	 * Whether a stored copy is left out because its page said noindex.
	 *
	 * @param Entry|null $entry The stored copy.
	 * @return bool True when it is left out.
	 */
	public function hides( ?Entry $entry ): bool {
		return null !== $entry && $this->hides_result( $entry->noindex );
	}

	/**
	 * Whether a noindex result is left out.
	 *
	 * @param bool|null $noindex Result of the page, or null when unknown.
	 * @return bool True when the page is noindex and such pages are left out.
	 */
	public function hides_result( ?bool $noindex ): bool {
		return true === $noindex && $this->exclusions->leaves_out_noindex();
	}

	/**
	 * Finds why a page is not served.
	 *
	 * @param WP_Post $post The page.
	 * @return string|null A reason code, or null when the page is served.
	 */
	public function reason( WP_Post $post ): ?string {
		if ( 'publish' !== $post->post_status ) {
			return 'not_published';
		}

		if ( '' !== $post->post_password ) {
			return 'password_protected';
		}

		if ( ! $this->post_types->is_enabled( $post->post_type ) ) {
			return 'excluded_post_type';
		}

		if ( null === $this->urls->permalink( $post ) ) {
			return 'plain_permalink';
		}

		$excluded = $this->exclusions->matched( $post );

		if ( null !== $excluded ) {
			return $excluded;
		}

		return apply_filters( 'agentwelcome_is_eligible', true, $post ) ? null : 'filtered';
	}

	/**
	 * Turns a reason code into a sentence.
	 *
	 * @param string $reason Code from reason().
	 * @return string Sentence for people, or the code when it is unknown.
	 */
	public function describe( string $reason ): string {
		$labels = array(
			'not_published'      => __( 'The page is not published.', 'agentwelcome' ),
			'password_protected' => __( 'The page is password protected.', 'agentwelcome' ),
			'excluded_post_type' => __( 'Its post type is excluded in the settings.', 'agentwelcome' ),
			'plain_permalink'    => __( 'It has no pretty permalink. Plain permalinks are not supported.', 'agentwelcome' ),
			'excluded_page'      => __( 'The page is hidden from AgentWelcome in the editor.', 'agentwelcome' ),
			'excluded_pattern'   => __( 'It matches an ID or URL pattern in the exclusion list.', 'agentwelcome' ),
			'excluded_term'      => __( 'It belongs to an excluded taxonomy term.', 'agentwelcome' ),
			'noindex'            => __( 'The page says noindex in its robots meta tag or X-Robots-Tag header. Untick "Leave out pages set to noindex" in the settings to include it.', 'agentwelcome' ),
			'filtered'           => __( 'A filter excluded it. WooCommerce cart, checkout, My account and terms pages are left out this way.', 'agentwelcome' ),
		);

		return $labels[ $reason ] ?? $reason;
	}
}
