<?php
/**
 * Tests for removing the argument of the render request from links.
 *
 * @package AgentWelcome
 */

declare(strict_types=1);

namespace AgentWelcome\Tests\Unit;

use AgentWelcome\Rendering\Renderer;
use PHPUnit\Framework\TestCase;

final class RenderMarkerTest extends TestCase {

	/**
	 * @dataProvider links
	 */
	public function test_removes_the_argument( string $html, string $expected ): void {
		$this->assertSame( $expected, Renderer::without_marker( $html ) );
	}

	/**
	 * @return array<string, array{string, string}>
	 */
	public function links(): array {
		return array(
			'only argument'  => array( '<a href="/blog/page/2/?agentwelcome_render=123">', '<a href="/blog/page/2/">' ),
			'first of two'   => array( '<a href="/blog/?agentwelcome_render=123&#038;a=1">', '<a href="/blog/?a=1">' ),
			'after another'  => array( '<a href="/blog/?a=1&#038;agentwelcome_render=123#top">', '<a href="/blog/?a=1#top">' ),
			'plain markup'   => array( '<a href="/blog/?a=1&amp;b=2">', '<a href="/blog/?a=1&amp;b=2">' ),
		);
	}
}
