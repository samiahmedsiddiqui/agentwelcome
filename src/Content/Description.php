<?php
/**
 * One-line page description for llms.txt: editor override, then the meta description of the rendered page, then the excerpt. Never the page content.
 *
 * @package AgentWelcome
 */

declare(strict_types=1);

namespace AgentWelcome\Content;

use WP_Post;

defined( 'ABSPATH' ) || exit;

/** Works out the short description of a page for llms.txt and the front matter. */
final class Description {

	/** An SEO plugin template tag such as %%sitename%% or #post_title. */
	private const TEMPLATE_TAG = '/%{1,2}[a-z_]+(?:\([^)]*\))?%{1,2}|#[a-z]+_[a-z_]+/i';

	/** Meta keys where SEO plugins keep the description of a page. They only tell AgentWelcome when to refresh the page. */
	public const SEO_META_KEYS = array( '_yoast_wpseo_metadesc', 'rank_math_description', '_aioseo_description', '_seopress_titles_desc', '_genesis_description' );

	/** The description was typed in the editor box. */
	public const EDITOR = 'editor';
	/** The description is the meta description of the rendered page. */
	public const META = 'meta';
	/** The description is the excerpt. */
	public const EXCERPT = 'excerpt';
	/** The page has no description. */
	public const NONE = 'none';

	/**
	 * Whether the text still holds template tags such as %%sitename%%, %title% or #site_title.
	 *
	 * @param string $text Description text.
	 * @return bool True when a tag is left.
	 */
	public static function has_unresolved_tags( string $text ): bool {
		return 1 === preg_match( self::TEMPLATE_TAG, $text );
	}

	/**
	 * Finds the description of a page: the one set in the editor box, then the meta description of the rendered page, then the excerpt.
	 *
	 * @param WP_Post     $post The page.
	 * @param string|null $meta Meta description read from the page just rendered, or null to use the one stored when the page was last generated.
	 * @return string The description, or an empty string when there is none.
	 */
	public function get( WP_Post $post, ?string $meta = null ): string {
		return $this->find( $post, $meta )['text'];
	}

	/**
	 * Finds the description of a page and where it came from.
	 *
	 * @param WP_Post     $post The page.
	 * @param string|null $meta Meta description read from the page just rendered, or null to use the stored one.
	 * @return array{text: string, source: string} The description and one of the source constants.
	 */
	public function find( WP_Post $post, ?string $meta = null ): array {
		$source = self::EDITOR;
		$text   = trim( (string) get_post_meta( $post->ID, PostMeta::DESCRIPTION, true ) );

		if ( '' === $text ) {
			$source = self::META;
			$text   = $this->clean( $meta ?? (string) get_post_meta( $post->ID, PostMeta::PAGE_DESCRIPTION, true ) );
		}

		if ( '' === $text ) {
			$source = self::EXCERPT;
			$text   = $this->clean( $post->post_excerpt );
		}

		$text = (string) apply_filters( 'agentwelcome_description', $text, $post );

		return array(
			'text'   => $text,
			'source' => '' === $text ? self::NONE : $source,
		);
	}

	/**
	 * Turns an excerpt or meta description into plain text of at most 30 words.
	 *
	 * @param string $text Excerpt or meta description.
	 * @return string Plain text.
	 */
	private function clean( string $text ): string {
		$text = wp_strip_all_tags( strip_shortcodes( $text ) );
		$text = (string) preg_replace( '/\s+/', ' ', html_entity_decode( $text, ENT_QUOTES, 'UTF-8' ) );

		return wp_trim_words( trim( $text ), 30 );
	}
}
