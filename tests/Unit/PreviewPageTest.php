<?php
/**
 * Tests for the formatted page and its headers.
 *
 * @package AgentWelcome
 */

declare(strict_types=1);

namespace AgentWelcome\Tests\Unit;

use AgentWelcome\Http\PreviewPage;
use PHPUnit\Framework\TestCase;

final class PreviewPageTest extends TestCase {

	private function page(): PreviewPage {
		return new PreviewPage( 'https://example.com/wp-content/plugins/agentwelcome/assets/js/markdown.js?ver=1.0.0.5', 'https://example.com/wp-content/plugins/agentwelcome/assets/css/preview.css?ver=1.0.0.7' );
	}

	public function test_the_markdown_is_written_as_escaped_text(): void {
		$html = $this->page()->render( 'About', "---\ntitle: \"A\"\n---\n<script>alert(1)</script> & &amp; \"q\"", 'https://example.com/about/', 'https://example.com/about.md', 'en-US' );

		$this->assertStringContainsString( '&lt;script&gt;alert(1)&lt;/script&gt; &amp; &amp;amp; &quot;q&quot;', $html );
		$this->assertStringNotContainsString( '<script>alert', $html );
		$this->assertSame( 1, substr_count( $html, '<script' ) );
	}

	public function test_the_title_and_links_are_escaped(): void {
		$html = $this->page()->render( 'A <b>"bold"</b> title', 'x', 'https://example.com/a/?x="y"', 'https://example.com/a.md', 'en-US' );

		$this->assertStringNotContainsString( '<b>', $html );
		$this->assertStringContainsString( '<title>A &lt;b&gt;&quot;bold&quot;&lt;/b&gt; title (Markdown)</title>', $html );
		$this->assertStringNotContainsString( 'x="y"', $html );
	}

	public function test_a_markdown_text_cannot_close_the_block(): void {
		$html = $this->page()->render( 'T', "</pre><h1 id=\"x\">hi</h1>\n</main>", '', 'https://example.com/a.md', 'en-US' );

		$this->assertSame( 1, substr_count( $html, '</pre>' ) );
		$this->assertStringNotContainsString( '<h1 id="x">', $html );
	}

	public function test_the_page_is_not_indexed_and_links_back(): void {
		$html = $this->page()->render( 'About', 'x', 'https://example.com/about/', 'https://example.com/about.md?raw=1', 'en-US' );

		$this->assertStringContainsString( '<meta name="robots" content="noindex, nofollow">', $html );
		$this->assertStringContainsString( '<link rel="canonical" href="https://example.com/about/">', $html );
		$this->assertStringContainsString( '<a href="https://example.com/about/">Open the page</a>', $html );
		$this->assertStringContainsString( '<a href="https://example.com/about.md?raw=1">Raw Markdown</a>', $html );
		$this->assertStringContainsString( '<html lang="en-US">', $html );
		$this->assertStringNotContainsString( '<style', $html );
		$this->assertStringNotContainsString( 'onclick', $html );
	}

	public function test_without_a_page_address_there_is_no_link_to_it(): void {
		$html = $this->page()->render( 'T', 'x', '', 'https://example.com/a.md', 'en-US' );

		$this->assertStringNotContainsString( 'Open the page', $html );
		$this->assertStringNotContainsString( 'rel="canonical"', $html );
	}

	public function test_headers_allow_only_the_two_plugin_files(): void {
		$headers = $this->page()->headers();

		$this->assertSame(
			"default-src 'none'; script-src https://example.com/wp-content/plugins/agentwelcome/assets/js/markdown.js; style-src https://example.com/wp-content/plugins/agentwelcome/assets/css/preview.css; base-uri 'none'; form-action 'none'; frame-ancestors 'none'",
			$headers['Content-Security-Policy']
		);
		$this->assertSame( 'private, no-cache', $headers['Cache-Control'] );
		$this->assertSame( 'noindex, nofollow', $headers['X-Robots-Tag'] );
		$this->assertSame( 'DENY', $headers['X-Frame-Options'] );
		$this->assertSame( 'no-referrer', $headers['Referrer-Policy'] );
	}

	public function test_a_file_address_cannot_add_policy_directives(): void {
		$page   = new PreviewPage( "https://example.com/a.js; script-src *\n", 'https://example.com/a.css' );
		$policy = $page->headers()['Content-Security-Policy'];

		$this->assertSame( 1, substr_count( $policy, '; script-src ' ) );
		$this->assertStringNotContainsString( ' *', $policy );
		$this->assertStringNotContainsString( "\n", $policy );
	}
}
