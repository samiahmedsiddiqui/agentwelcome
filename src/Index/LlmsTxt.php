<?php
/**
 * Builds /llms.txt: a Markdown index of every eligible page.
 *
 * @package AgentWelcome
 */

declare(strict_types=1);

namespace AgentWelcome\Index;

use AgentWelcome\Cache\Store;
use AgentWelcome\Content\Description;
use AgentWelcome\Content\Eligibility;
use AgentWelcome\Content\Language;
use AgentWelcome\Content\PostMeta;
use AgentWelcome\Content\PostTypes;
use AgentWelcome\Http\Urls;
use AgentWelcome\Settings\LlmsOptions;
use WP_Post;

defined( 'ABSPATH' ) || exit;

/** Builds llms.txt, from the generated list of pages, a custom text, or both. */
final class LlmsTxt {

	/**
	 * @param PostTypes   $post_types  Post types that are served.
	 * @param Eligibility $eligibility Decides whether a page is served.
	 * @param Urls        $urls        Builds the addresses.
	 * @param Description $description Finds the description of a page.
	 * @param LlmsOptions $options     Settings of the llms files.
	 * @param Store       $store       Stored copies, which hold whether a page says noindex.
	 */
	public function __construct(
		private PostTypes $post_types,
		private Eligibility $eligibility,
		private Urls $urls,
		private Description $description,
		private LlmsOptions $options,
		private Store $store
	) {}

	/**
	 * Builds the file for the current mode.
	 *
	 * @return string The text of llms.txt.
	 */
	public function build(): string {
		$mode   = $this->options->mode();
		$custom = $this->options->custom();

		$languages = (array) apply_filters( 'agentwelcome_languages', array() );
		$default   = (string) array_key_first( $languages );

		$text = LlmsOptions::CUSTOM === $mode && '' !== $custom
			? self::compose( $mode, $custom, array(), array() )
			: self::compose( $mode, $custom, Language::run( $default, fn(): array => $this->heading() ), $this->all_sections( $languages ) );

		return (string) apply_filters( 'agentwelcome_llms_txt', $text );
	}

	/**
	 * Joins the parts of the file for a mode. A custom text is used as written, and an empty one falls back to the generated file.
	 *
	 * @param string   $mode     One of the LlmsOptions modes.
	 * @param string   $custom   Custom text.
	 * @param string[] $heading  Generated title and tagline lines.
	 * @param string[] $sections Generated page list lines.
	 * @return string The file.
	 */
	public static function compose( string $mode, string $custom, array $heading, array $sections ): string {
		if ( '' !== $custom && LlmsOptions::CUSTOM === $mode ) {
			return $custom . "\n";
		}

		if ( '' !== $custom && LlmsOptions::HYBRID === $mode ) {
			return $custom . "\n" . ( empty( $sections ) ? '' : "\n" . implode( "\n", $sections ) . "\n" );
		}

		return implode( "\n", empty( $sections ) ? $heading : array_merge( $heading, array( '' ), $sections ) ) . "\n";
	}

	/**
	 * Title and tagline lines from the site name and description.
	 *
	 * @return string[] Lines of text.
	 */
	private function heading(): array {
		$lines   = array( '# ' . SingleLine::from( get_bloginfo( 'name' ) ) );
		$tagline = SingleLine::from( get_bloginfo( 'description' ) );

		if ( '' !== $tagline ) {
			$lines[] = '';
			$lines[] = '> ' . $tagline;
		}

		return $lines;
	}

	/**
	 * The sections of the default language, then one part for every other language.
	 *
	 * @param array<string, string> $languages Name by language code, the default first. Empty on a site with one language.
	 * @return string[] Lines of text.
	 */
	private function all_sections( array $languages ): array {
		if ( array() === $languages ) {
			return $this->sections();
		}

		$default = (string) array_key_first( $languages );
		$name    = Language::run( $default, static fn(): string => SingleLine::from( get_bloginfo( 'name' ) ) );
		$lines   = Language::run( $default, fn(): array => $this->sections( $default ) );

		foreach ( array_slice( $languages, 1, null, true ) as $code => $label ) {
			$part = Language::run(
				(string) $code,
				fn(): array => $this->language_part( (string) $code, (string) $label, $name )
			);

			if ( array() !== $part ) {
				$lines = array() === $lines ? $part : array_merge( $lines, array( '' ), $part );
			}
		}

		return $lines;
	}

	/**
	 * Heading, tagline and sections of one language, which must be active.
	 *
	 * @param string $code      Language code.
	 * @param string $label     Name of the language.
	 * @param string $site_name Site name in the default language.
	 * @return string[] Lines of text, empty when the language has no page to list.
	 */
	private function language_part( string $code, string $label, string $site_name ): array {
		$sections = $this->sections( $code, '###' );

		if ( array() === $sections ) {
			return array();
		}

		$name    = SingleLine::from( get_bloginfo( 'name' ) );
		$tagline = SingleLine::from( get_bloginfo( 'description' ) );
		$lines   = array( '## ' . SingleLine::from( $label ) . ( $name === $site_name ? '' : ': ' . $name ), '' );

		if ( '' !== $tagline ) {
			array_push( $lines, '> ' . $tagline, '' );
		}

		return array_merge( $lines, $sections );
	}

	/**
	 * One section per served post type, with a line for each page.
	 *
	 * @param string $language Only pages of this language, or an empty string on a site with one language.
	 * @param string $level    Markdown heading marks of the sections.
	 * @return string[] Lines of text.
	 */
	private function sections( string $language = '', string $level = '##' ): array {
		$lines = array();

		foreach ( $this->post_types->enabled() as $type ) {
			$entries = $this->entries( $type, $language );

			if ( empty( $entries ) ) {
				continue;
			}

			$object = get_post_type_object( $type );

			if ( array() !== $lines ) {
				$lines[] = '';
			}

			$lines[] = $level . ' ' . ( $object ? $object->labels->name : $type );
			$lines[] = '';

			foreach ( $entries as $entry ) {
				$lines[] = $entry;
			}
		}

		return $lines;
	}

	/**
	 * Lines for the pages of one post type.
	 *
	 * @param string $type     Post type.
	 * @param string $language Language code, or an empty string.
	 * @return string[] One line per served page.
	 */
	private function entries( string $type, string $language ): array {
		$hierarchical = is_post_type_hierarchical( $type );
		$posts        = get_posts(
			array(
				'post_type'        => $type,
				'post_status'      => 'publish',
				'has_password'     => false,
				'posts_per_page'   => (int) apply_filters( 'agentwelcome_llms_max_items', 500, $type ),
				'orderby'          => $hierarchical ? 'menu_order title' : 'date',
				'order'            => $hierarchical ? 'ASC' : 'DESC',
				'no_found_rows'    => true,
				'suppress_filters' => false,
			)
		);

		$entries = array();
		$flags   = $this->store->noindex_flags( wp_list_pluck( $posts, 'ID' ) );
		$reasons = $this->store->restricted_reasons( wp_list_pluck( $posts, 'ID' ) );

		foreach ( $posts as $post ) {
			// A query can also return untranslated content, which is listed under its own language only.
			if ( '' !== $language && Language::of( $post ) !== $language ) {
				continue;
			}

			// A page that has not been generated yet is not listed, because it is not known whether it says noindex.
			if ( ! array_key_exists( $post->ID, $flags ) || $this->eligibility->hides_result( $flags[ $post->ID ] ) || $this->eligibility->hides_restricted( $reasons[ $post->ID ] ?? null ) ) {
				continue;
			}

			$entry = Language::run( $language, fn(): ?string => $this->entry( $post ) );

			if ( null !== $entry ) {
				$entries[] = $entry;
			}
		}

		return $entries;
	}

	/**
	 * Title of a page: the one stored when it was generated, or the WordPress title.
	 *
	 * @param WP_Post $post The page.
	 * @return string The title.
	 */
	private function title( WP_Post $post ): string {
		$stored = get_post_meta( $post->ID, PostMeta::TITLE, true );

		return is_string( $stored ) && '' !== $stored ? $stored : get_the_title( $post );
	}

	/**
	 * Line for one page: a link to the Markdown version, with its description.
	 *
	 * @param WP_Post $post The page.
	 * @return string|null The line, or null when the page is not served.
	 */
	private function entry( WP_Post $post ): ?string {
		$url = $this->urls->markdown_url( $post );

		if ( null === $url || ! $this->eligibility->is_eligible( $post ) ) {
			return null;
		}

		$title = str_replace( array( '[', ']' ), array( '\\[', '\\]' ), SingleLine::from( $this->title( $post ) ) );
		$title = '' === $title ? $post->post_name : $title;
		$text  = SingleLine::from( wp_trim_words( $this->description->get( $post ), 30 ) );

		return '- [' . $title . '](' . $url . ')' . ( '' === $text ? '' : ': ' . $text );
	}
}
