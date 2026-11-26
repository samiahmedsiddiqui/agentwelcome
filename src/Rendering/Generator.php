<?php
/**
 * Runs the pipeline: render, extract, convert, prepend front matter.
 *
 * @package AgentWelcome
 */

declare(strict_types=1);

namespace AgentWelcome\Rendering;

use AgentWelcome\Content\Description;
use AgentWelcome\Http\Urls;
use AgentWelcome\Settings\Options;
use WP_Post;

defined( 'ABSPATH' ) || exit;

/** Builds the Markdown of a page: reads the main content, converts it and adds the metadata block. */
final class Generator {

	/**
	 * @param Options     $options      Saved settings.
	 * @param Urls        $urls         Builds the page addresses.
	 * @param Renderer    $renderer     Reads the HTML of a page.
	 * @param Extractor   $extractor    Finds the main content in the HTML.
	 * @param Converter   $converter    Converts HTML to Markdown.
	 * @param FrontMatter $front_matter Builds the metadata block.
	 * @param Description $description  Finds the description of a page.
	 */
	public function __construct(
		private Options $options,
		private Urls $urls,
		private Renderer $renderer,
		private Extractor $extractor,
		private Converter $converter,
		private FrontMatter $front_matter,
		private Description $description
	) {}

	/**
	 * Renders a page and builds its Markdown.
	 *
	 * @param WP_Post                                                                   $post      The page.
	 * @param array{selector?: string, strip?: string[], headings?: array<string, int>} $overrides Settings to use instead of the saved ones.
	 * @param int                                                                       $paged     Number of the paginated view, 1 for the page itself.
	 * @return GeneratedPage The Markdown and where it came from.
	 */
	public function generate( WP_Post $post, array $overrides = array(), int $paged = 1 ): GeneratedPage {
		return $this->build( $post, $this->renderer->render( $post, $paged ), $overrides, $paged );
	}

	/**
	 * Selector of the main content.
	 *
	 * @param array{selector?: string, strip?: string[], headings?: array<string, int>} $overrides Settings to use instead of the saved ones.
	 * @return string The selector, or the default list when none is set.
	 */
	public function content_selector( array $overrides = array() ): string {
		$selector = ! empty( $overrides['selector'] ) ? $overrides['selector'] : $this->options->content_selector();

		return '' === $selector ? Options::DEFAULT_SELECTOR : $selector;
	}

	/**
	 * Text blocks of the content area, split into those kept and those removed by the exclusions.
	 *
	 * @param RenderResult                                                              $render    The rendered page.
	 * @param array{selector?: string, strip?: string[], headings?: array<string, int>} $overrides Settings to use instead of the saved ones.
	 * @return array{kept: string[], excluded: string[]} The blocks of text.
	 */
	public function text_blocks( RenderResult $render, array $overrides = array() ): array {
		$extractor = ! empty( $overrides['strip'] ) || ! empty( $overrides['headings'] ) ? $this->extractor->with_extra( $overrides['strip'] ?? array(), $overrides['headings'] ?? array() ) : $this->extractor;

		return $extractor->text_blocks( $render->html, $this->content_selector( $overrides ) );
	}

	/**
	 * Builds the Markdown from HTML that was already fetched.
	 *
	 * @param WP_Post                                                                   $post      The page.
	 * @param RenderResult                                                              $render    The rendered page.
	 * @param array{selector?: string, strip?: string[], headings?: array<string, int>} $overrides Settings to use instead of the saved ones.
	 * @param int                                                                       $paged     Number of the paginated view, 1 for the page itself.
	 * @return GeneratedPage The Markdown and where it came from.
	 */
	public function build( WP_Post $post, RenderResult $render, array $overrides = array(), int $paged = 1 ): GeneratedPage {
		if ( RenderResult::RESTRICTED === $render->source ) {
			return new GeneratedPage( '', $render->source, '', null, '', null, '', '', '', PageTitle::POST, $render->restricted );
		}

		$url       = (string) $this->urls->view_url( $post, $paged );
		$selector  = $this->content_selector( $overrides );
		$extractor = ! empty( $overrides['strip'] ) || ! empty( $overrides['headings'] ) ? $this->extractor->with_extra( $overrides['strip'] ?? array(), $overrides['headings'] ?? array() ) : $this->extractor;
		$node      = $extractor->extract( $render->html, $selector, $url );
		$body      = null === $node ? '' : $this->converter->convert( $node );
		$chosen    = $this->title( $post, $render, $paged );
		$title     = $chosen['title'];

		if ( '' !== $title && ( null === $node || 0 === $node->getElementsByTagName( 'h1' )->length ) ) {
			$body = "# {$title}\n\n" . $body;
		}

		$meta  = RenderResult::LOOPBACK === $render->source ? MetaDescription::find( $render->html ) : null;
		$found = $this->description->find( $post, $meta );
		$front = $this->front_matter->render(
			array(
				'title'       => $title,
				'url'         => $url,
				'description' => $found['text'],
				'type'        => $post->post_type,
				'modified'    => (string) get_post_modified_time( 'c', true, $post ),
				'language'    => str_replace( '_', '-', get_locale() ),
			)
		);

		$markdown = (string) apply_filters( 'agentwelcome_markdown', $front . "\n" . trim( $body ) . "\n", $post );

		$source = RenderResult::LOOPBACK === $render->source ? Robots::source( $render->html, $render->robots ) : '';
		/**
		 * Filters whether a page counts as noindex. The page's own robots meta tag and X-Robots-Tag header decide by default.
		 *
		 * @param bool    $noindex Whether the page says noindex.
		 * @param WP_Post $post    The page.
		 */
		$noindex = (bool) apply_filters( 'agentwelcome_is_noindex', '' !== $source, $post );

		if ( $noindex && '' === $source ) {
			$source = 'filter';
		}

		// A page built without its rendered HTML has no robots information, so its result is unknown.
		$known = RenderResult::LOOPBACK === $render->source || $noindex;

		$views = RenderResult::LOOPBACK === $render->source ? Pagination::count( $render->html, (string) $this->urls->view_url( $post, 1 ) ) : null;

		return new GeneratedPage( $markdown, $render->source, $render->notice, $meta, $found['source'], $known ? $noindex : null, $source, $title, $chosen['original'], $chosen['used'], RenderResult::LOOPBACK === $render->source ? '' : null, $views );
	}

	/**
	 * Chooses the title of a page: the title tag of the rendered page without the site name, or the post title.
	 *
	 * @param WP_Post      $post   The page.
	 * @param RenderResult $render The rendered page.
	 * @param int          $paged  Number of the paginated view, 1 for the page itself.
	 * @return array{title: string, original: string, used: string} The title, the title tag as found, and which one was used.
	 */
	private function title( WP_Post $post, RenderResult $render, int $paged ): array {
		$post_title = trim( html_entity_decode( wp_strip_all_tags( get_the_title( $post ) ), ENT_QUOTES, 'UTF-8' ) );

		if ( RenderResult::LOOPBACK !== $render->source ) {
			return PageTitle::choose( $post_title, '', array(), false, false, false );
		}

		$read  = PageTitle::read( $render->html );
		$names = array( trim( html_entity_decode( wp_strip_all_tags( get_bloginfo( 'name' ) ), ENT_QUOTES, 'UTF-8' ) ), $read['site_name'] );
		$names = array_map( 'strval', (array) apply_filters( 'agentwelcome_title_site_names', $names, $post ) );
		$home  = 'page' === get_option( 'show_on_front' ) && (int) get_option( 'page_on_front' ) === $post->ID;

		return PageTitle::choose( $post_title, $read['title'], $names, (bool) $this->options->get( 'title_strip_site', true ), 'post' !== $this->options->get( 'title_source', 'page' ), $home, $paged );
	}
}
