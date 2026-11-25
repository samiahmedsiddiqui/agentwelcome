<?php
/**
 * Tests for reading the Accept header.
 *
 * @package AgentWelcome
 */

declare(strict_types=1);

namespace AgentWelcome\Tests\Unit;

use AgentWelcome\Http\AcceptHeader;
use PHPUnit\Framework\TestCase;

final class AcceptHeaderTest extends TestCase {

	/**
	 * @dataProvider headers
	 */
	public function test_prefers_markdown( string $header, bool $expected ): void {
		$this->assertSame( $expected, AcceptHeader::prefers_markdown( $header ) );
	}

	/**
	 * @return array<string, array{string, bool}>
	 */
	public function headers(): array {
		return array(
			'markdown only'                  => array( 'text/markdown', true ),
			'markdown with charset'          => array( 'text/markdown; charset=utf-8', true ),
			'upper case'                     => array( 'TEXT/Markdown', true ),
			'markdown first, html lower'     => array( 'text/markdown, text/html;q=0.5', true ),
			'html lower, listed first'       => array( 'text/html;q=0.5, text/markdown', true ),
			'markdown q above html q'        => array( 'text/markdown;q=0.9, text/html;q=0.8', true ),
			'markdown with wildcard below'   => array( 'text/markdown, */*;q=0.1', true ),
			'markdown above text wildcard'   => array( 'text/markdown, text/*;q=0.5', true ),
			'spaces around q'                => array( 'text/markdown ; q = 0.9 , text/html ; q = 0.1', true ),
			'html excluded'                  => array( 'text/markdown, text/html;q=0', true ),
			'repeated range keeps highest'   => array( 'text/markdown;q=0.2, text/markdown;q=0.9, text/html;q=0.5', true ),
			'missing header'                 => array( '', false ),
			'blank header'                   => array( '   ', false ),
			'browser header'                 => array( 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8', false ),
			'html only'                      => array( 'text/html', false ),
			'html listed above markdown'    => array( 'text/html, text/markdown;q=0.5', false ),
			'same quality'                   => array( 'text/markdown, text/html', false ),
			'same quality with q'            => array( 'text/markdown;q=0.5, text/html;q=0.5', false ),
			'any type'                       => array( '*/*', false ),
			'any text type'                  => array( 'text/*', false ),
			'any with markdown at same q'    => array( 'text/markdown, */*', false ),
			'text wildcard beats star'       => array( 'text/markdown;q=0.5, text/*;q=0.8, */*;q=0.1', false ),
			'markdown refused'               => array( 'text/markdown;q=0, text/html', false ),
			'markdown refused alone'         => array( 'text/markdown;q=0', false ),
			'other type'                     => array( 'application/json', false ),
			'similar type'                   => array( 'text/markdown-extra', false ),
			'x-markdown is not markdown'     => array( 'text/x-markdown', false ),
			'invalid q is ignored'           => array( 'text/markdown;q=abc, text/html;q=0.5', true ),
			'q above one is capped'          => array( 'text/markdown;q=5, text/html', false ),
			'garbage'                        => array( ';;,,,/;q=1', false ),
			'empty items around markdown'    => array( ',, text/markdown ,,', true ),
		);
	}
}
