<?php
/**
 * Tests for reading the meta description from the HTML of a page.
 *
 * @package AgentWelcome
 */

declare(strict_types=1);

namespace AgentWelcome\Tests\Unit;

use AgentWelcome\Rendering\MetaDescription;
use PHPUnit\Framework\TestCase;

final class MetaDescriptionTest extends TestCase {

	/**
	 * @dataProvider pages
	 */
	public function test_finds_the_description( string $html, string $expected ): void {
		$this->assertSame( $expected, MetaDescription::find( $html ) );
	}

	/**
	 * @return array<string, array{string, string}>
	 */
	public function pages(): array {
		$wrap = static fn( string $head ): string => '<html><head><title>T</title>' . $head . '</head><body><p>Body</p></body></html>';

		return array(
			'plain'                    => array( $wrap( '<meta name="description" content="About us.">' ), 'About us.' ),
			'content before name'      => array( $wrap( '<meta content="About us." name="description">' ), 'About us.' ),
			'single quotes'            => array( $wrap( "<meta name='description' content='About us.'>" ), 'About us.' ),
			'unquoted'                 => array( $wrap( '<meta name=description content=Short>' ), 'Short' ),
			'self closing'             => array( $wrap( '<meta name="description" content="About us." />' ), 'About us.' ),
			'uppercase tag'            => array( $wrap( '<META NAME="Description" CONTENT="About us.">' ), 'About us.' ),
			'entities'                 => array( $wrap( '<meta name="description" content="Tom &amp; Jerry&#039;s &quot;show&quot;">' ), 'Tom & Jerry\'s "show"' ),
			'other quote inside'       => array( $wrap( '<meta name="description" content=\'Say "hi" > now\'>' ), 'Say "hi" > now' ),
			'whitespace is collapsed'  => array( $wrap( "<meta name=\"description\" content=\"  One\n   two  \">" ), 'One two' ),
			'skips other meta tags'    => array( $wrap( '<meta property="og:description" content="Social."><meta name="robots" content="index"><meta name="description" content="Real.">' ), 'Real.' ),
			'first one wins'           => array( $wrap( '<meta name="description" content="First."><meta name="description" content="Second.">' ), 'First.' ),
			'empty one is skipped'     => array( $wrap( '<meta name="description" content=""><meta name="description" content="Second.">' ), 'Second.' ),
			'in a comment'             => array( $wrap( '<!-- <meta name="description" content="Old."> -->' ), '' ),
			'in a script'              => array( $wrap( '<script>var s = \'<meta name="description" content="Old.">\';</script>' ), '' ),
			'none'                     => array( $wrap( '<meta charset="utf-8">' ), '' ),
			'only in the body'         => array( '<html><head><title>T</title></head><body><meta name="description" content="Body."></body></html>', '' ),
			'no head'                  => array( '<p><meta name="description" content="Loose."></p>', '' ),
			'head without a close tag' => array( '<head><meta name="description" content="Open."><body><p>x</p>', 'Open.' ),
			'empty html'               => array( '', '' ),
		);
	}
}
