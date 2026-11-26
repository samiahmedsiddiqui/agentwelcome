<?php
/**
 * Tests for reading the number of views from pagination links.
 *
 * @package AgentWelcome
 */

declare(strict_types=1);

namespace AgentWelcome\Tests\Unit;

use AgentWelcome\Rendering\Pagination;
use PHPUnit\Framework\TestCase;

final class PaginationTest extends TestCase {

	/**
	 * @dataProvider pages
	 */
	public function test_counts_views( string $html, int $expected ): void {
		$this->assertSame( $expected, Pagination::count( $html, 'https://example.com/news/blog/' ) );
	}

	/**
	 * @return array<string, array{string, int}>
	 */
	public function pages(): array {
		return array(
			'numbered links'      => array( '<a class="page-numbers" href="https://example.com/news/blog/page/2/">2</a><a href="https://example.com/news/blog/page/18/">18</a>', 18 ),
			'relative and query'  => array( "<a href='/news/blog/page/3?a=1&amp;b=2'>3</a>", 3 ),
			'next in the head'    => array( '<link rel="next" href="https://example.com/news/blog/page/2/" />', 2 ),
			'no pagination'       => array( '<a href="/news/blog/">Blog</a>', 1 ),
			'another list'        => array( '<a href="/shop/page/4/">4</a><a href="/news/blog/topic/page/5/">5</a>', 1 ),
			'another site'        => array( '<a href="https://other.example/news/blog/page/4/">4</a>', 1 ),
			'link from a comment' => array( '<a href="/news/blog/page/90/" rel="nofollow ugc">x</a>', 1 ),
			'commented out'       => array( '<!-- <a href="/news/blog/page/6/">6</a> -->', 1 ),
			'not an href'         => array( '<a data-href="/news/blog/page/6/">6</a>', 1 ),
		);
	}
}
