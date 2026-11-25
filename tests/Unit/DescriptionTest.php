<?php
/**
 * Tests for the order of the description sources and for spotting template tags.
 *
 * @package AgentWelcome
 */

declare(strict_types=1);

namespace AgentWelcome\Tests\Unit;

use AgentWelcome\Content\Description;
use AgentWelcome\Content\PostMeta;
use PHPUnit\Framework\TestCase;
use WP_Post;

final class DescriptionTest extends TestCase {

	protected function setUp(): void {
		$GLOBALS['agentwelcome_test_meta']    = array();
		$GLOBALS['agentwelcome_test_filters'] = array();
	}

	/**
	 * @param array<string, string> $meta    Post meta of the page.
	 * @param string                $excerpt Excerpt.
	 */
	private function post( array $meta = array(), string $excerpt = '' ): WP_Post {
		$post               = new WP_Post();
		$post->ID           = 7;
		$post->post_excerpt = $excerpt;

		$GLOBALS['agentwelcome_test_meta'][7] = $meta;

		return $post;
	}

	public function test_editor_description_comes_first(): void {
		$post = $this->post( array( PostMeta::DESCRIPTION => 'Editor.', PostMeta::PAGE_DESCRIPTION => 'Stored.' ), 'Excerpt.' );

		$this->assertSame(
			array(
				'text'   => 'Editor.',
				'source' => Description::EDITOR,
			),
			( new Description() )->find( $post, 'Rendered.' )
		);
	}

	public function test_rendered_meta_description_comes_before_the_excerpt(): void {
		$post = $this->post( array( PostMeta::PAGE_DESCRIPTION => 'Stored.' ), 'Excerpt.' );

		$this->assertSame(
			array(
				'text'   => 'Rendered.',
				'source' => Description::META,
			),
			( new Description() )->find( $post, 'Rendered.' )
		);
	}

	public function test_stored_meta_description_is_used_when_the_page_was_not_rendered(): void {
		$post = $this->post( array( PostMeta::PAGE_DESCRIPTION => 'Stored.' ), 'Excerpt.' );

		$this->assertSame( 'Stored.', ( new Description() )->get( $post ) );
	}

	public function test_an_empty_rendered_description_does_not_fall_back_to_the_stored_one(): void {
		$post = $this->post( array( PostMeta::PAGE_DESCRIPTION => 'Stored.' ), 'Excerpt.' );

		$this->assertSame( 'Excerpt.', ( new Description() )->get( $post, '' ) );
	}

	public function test_excerpt_is_used_without_a_meta_description(): void {
		$post = $this->post( array(), '<b>Bold</b> [gallery] and &amp; more.' );

		$this->assertSame(
			array(
				'text'   => 'Bold and & more.',
				'source' => Description::EXCERPT,
			),
			( new Description() )->find( $post )
		);
	}

	public function test_saved_seo_plugin_descriptions_are_not_read(): void {
		$post = $this->post( array( '_yoast_wpseo_metadesc' => 'Yoast.' ), 'Excerpt.' );

		$this->assertSame( 'Excerpt.', ( new Description() )->get( $post ) );
	}

	public function test_no_source_gives_no_description(): void {
		$this->assertSame(
			array(
				'text'   => '',
				'source' => Description::NONE,
			),
			( new Description() )->find( $this->post() )
		);
	}

	public function test_filter_can_change_the_description(): void {
		$GLOBALS['agentwelcome_test_filters']['agentwelcome_description'] = static fn( $text ) => $text . ' Filtered.';

		$this->assertSame( 'Rendered. Filtered.', ( new Description() )->get( $this->post(), 'Rendered.' ) );
	}

	/**
	 * @dataProvider texts
	 */
	public function test_detects_unresolved_tags( string $text, bool $expected ): void {
		$this->assertSame( $expected, Description::has_unresolved_tags( $text ) );
	}

	/**
	 * @return array<string, array{string, bool}>
	 */
	public function texts(): array {
		return array(
			'plain text'                    => array( 'Welcome to our contact page.', false ),
			'Yoast variable'                => array( 'Welcome to %%sitename%%.', true ),
			'Yoast variable with a filter'  => array( '%%title%% %%sep%% %%sitename%%', true ),
			'Rank Math variable'            => array( 'Welcome to %sitename%.', true ),
			'Rank Math variable with args'  => array( '%count(varname)%', true ),
			'AIOSEO tag'                    => array( 'Welcome to #site_title.', true ),
			'AIOSEO separator tag'          => array( '#post_title #separator_sa #site_title', true ),
			'a percentage is not a tag'     => array( 'Save 50% today and 30% more.', false ),
			'a hashtag is not a tag'        => array( 'Follow us #AI for news.', false ),
			'a number sign is not a tag'    => array( 'We are #1 in speed.', false ),
			'empty'                         => array( '', false ),
		);
	}
}
