<?php
/**
 * Tests for writing chosen elements as headings.
 *
 * @package AgentWelcome
 */

declare(strict_types=1);

namespace AgentWelcome\Tests\Unit;

use AgentWelcome\Rendering\Converter;
use AgentWelcome\Rendering\Extractor;
use AgentWelcome\Rendering\HeadingRules;
use AgentWelcome\Rendering\Selector;
use PHPUnit\Framework\TestCase;

final class HeadingsTest extends TestCase {

	/**
	 * @dataProvider pages
	 *
	 * @param array<string, int> $rules Heading levels by selector.
	 */
	public function test_writes_headings( string $html, array $rules, string $expected ): void {
		$node = ( new Extractor( new Selector(), Extractor::DEFAULT_STRIP, $rules ) )->extract( '<html><body>' . $html . '</body></html>', 'body', 'https://example.com/page/' );

		$this->assertNotNull( $node );
		$this->assertSame( $expected, ( new Converter() )->convert( $node ) );
	}

	/**
	 * @return array<string, array{string, array<string, int>, string}>
	 */
	public function pages(): array {
		return array(
			'block title'              => array( '<div class="egs-title">Why us</div><p>Body</p>', array( '.egs-title' => 3 ), "### Why us\n\nBody\n" ),
			'level is per rule'        => array( '<div class="hero">Big</div><div class="card">Small</div>', array( '.hero' => 2, '.card' => 4 ), "## Big\n\n#### Small\n" ),
			'nothing without a rule'   => array( '<div class="egs-title">Why us</div>', array(), "Why us\n" ),
			'real heading is kept'     => array( '<h5 class="egs-title">Title</h5>', array( '.egs-title' => 3 ), "##### Title\n" ),
			'inside a real heading'    => array( '<h2><span class="t">Title</span></h2>', array( '.t' => 4 ), "## Title\n" ),
			'link inside the title'    => array( '<div class="t"><a href="/x/">Title</a></div>', array( '.t' => 3 ), "### [Title](https://example.com/x/)\n" ),
			'link is the title'        => array( '<div><a class="t" href="/x/">Title</a></div>', array( '.t' => 3 ), "### [Title](https://example.com/x/)\n" ),
			'span that is the block'   => array( '<p><span class="t">Title</span></p>', array( '.t' => 3 ), "### Title\n" ),
			'span inside a sentence'   => array( '<p>Read <span class="t">this</span> now</p>', array( '.t' => 3 ), "Read this now\n" ),
			'block with blocks inside' => array( '<div class="t"><p>One</p><p>Two</p></div>', array( '.t' => 3 ), "One\n\nTwo\n" ),
			'nested matches'           => array( '<div class="t"><span class="t">X</span></div>', array( '.t' => 3 ), "### X\n" ),
			'empty element'            => array( '<div class="t"> </div><p>Body</p>', array( '.t' => 3 ), "Body\n" ),
			'emphasis is kept'         => array( '<div class="t">Fast <em>and</em> safe</div>', array( '.t' => 3 ), "### Fast *and* safe\n" ),
			'sibling selector'         => array( '<div class="a">One</div><div class="a">Two</div>', array( '.a + .a' => 3 ), "One\n\n### Two\n" ),
		);
	}

	public function test_reads_rules(): void {
		$result = ( new HeadingRules( new Selector() ) )->parse( ".a => h3\n.b, .c =>H2\n\n.d\n.e => h7\np:hover => h3\n[x=\"=>\"] => h4" );

		$this->assertSame(
			array(
				'.a'        => 3,
				'.b'        => 2,
				'.c'        => 2,
				'[x="=>"]' => 4,
			),
			$result['valid']
		);
		$this->assertSame( array( '.d', '.e => h7', 'p:hover' ), $result['invalid'] );
	}

	public function test_writes_rules_back(): void {
		$this->assertSame( ".a => h3\n.b => h2", ( new HeadingRules( new Selector() ) )->to_text( array( '.a' => 3, '.b' => 2 ) ) );
	}
}
