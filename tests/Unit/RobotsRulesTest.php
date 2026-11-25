<?php
/**
 * Tests for reading robots.txt rules.
 *
 * @package AgentWelcome
 */

declare(strict_types=1);

namespace AgentWelcome\Tests\Unit;

use AgentWelcome\Robots\RobotsRules;
use PHPUnit\Framework\TestCase;

final class RobotsRulesTest extends TestCase {

	/**
	 * @dataProvider cases
	 */
	public function test_allows( string $body, string $agent, string $path, bool $expected ): void {
		$this->assertSame( $expected, ( new RobotsRules( $body ) )->allows( $agent, $path ) );
	}

	/**
	 * @return array<string, array{string, string, string, bool}>
	 */
	public function cases(): array {
		return array(
			'empty file'                       => array( '', 'GPTBot', '/llms.txt', true ),
			'only comments'                    => array( "# nothing here\n", 'GPTBot', '/llms.txt', true ),
			'named group blocks all'           => array( "User-agent: GPTBot\nDisallow: /", 'GPTBot', '/llms.txt', false ),
			'other crawler is not blocked'     => array( "User-agent: GPTBot\nDisallow: /", 'ClaudeBot', '/llms.txt', true ),
			'agent name ignores case'          => array( "user-AGENT: gptbot\ndisallow: /", 'GPTBot', '/a.md', false ),
			'star group is the fallback'       => array( "User-agent: *\nDisallow: /", 'GPTBot', '/a.md', false ),
			'named group beats star group'     => array( "User-agent: *\nDisallow: /\n\nUser-agent: GPTBot\nAllow: /", 'GPTBot', '/a.md', true ),
			'named group is not merged'        => array( "User-agent: *\nDisallow: /a.md\n\nUser-agent: GPTBot\nDisallow: /b", 'GPTBot', '/a.md', true ),
			'stacked agents share the rules'   => array( "User-agent: GPTBot\nUser-agent: CCBot\nDisallow: /", 'CCBot', '/a.md', false ),
			'rules after a blank line stay'    => array( "User-agent: GPTBot\n\nDisallow: /", 'GPTBot', '/a.md', false ),
			'same agent in two groups merges'  => array( "User-agent: GPTBot\nDisallow: /a\n\nUser-agent: GPTBot\nDisallow: /b", 'GPTBot', '/b.md', false ),
			'empty disallow allows all'        => array( "User-agent: *\nDisallow:", 'GPTBot', '/a.md', true ),
			'prefix match'                     => array( "User-agent: *\nDisallow: /priv", 'GPTBot', '/private/a.md', false ),
			'prefix does not match elsewhere'  => array( "User-agent: *\nDisallow: /priv", 'GPTBot', '/public/priv.md', true ),
			'longest match wins for allow'     => array( "User-agent: *\nDisallow: /docs\nAllow: /docs/public", 'GPTBot', '/docs/public/a.md', true ),
			'longest match wins for disallow'  => array( "User-agent: *\nAllow: /docs\nDisallow: /docs/private", 'GPTBot', '/docs/private/a.md', false ),
			'tie goes to allow'                => array( "User-agent: *\nDisallow: /docs\nAllow: /docs", 'GPTBot', '/docs/a.md', true ),
			'order does not matter'            => array( "User-agent: *\nAllow: /docs/public\nDisallow: /docs", 'GPTBot', '/docs/public/a.md', true ),
			'star matches any text'            => array( "User-agent: *\nDisallow: /*.md", 'GPTBot', '/company/about.md', false ),
			'star pattern misses other files'  => array( "User-agent: *\nDisallow: /*.md", 'GPTBot', '/llms.txt', true ),
			'dollar anchors the end'           => array( "User-agent: *\nDisallow: /*.md$", 'GPTBot', '/about.md', false ),
			'dollar does not match a query'    => array( "User-agent: *\nDisallow: /*.md$", 'GPTBot', '/about.md?x=1', true ),
			'exact path with dollar'           => array( "User-agent: *\nDisallow: /llms.txt$", 'GPTBot', '/llms.txt', false ),
			'exact path with dollar, longer'   => array( "User-agent: *\nDisallow: /llms.txt$", 'GPTBot', '/llms.txt.bak', true ),
			'regex characters are literal'     => array( "User-agent: *\nDisallow: /a.b", 'GPTBot', '/axb', true ),
			'comments and spaces are ignored'  => array( "User-agent : GPTBot # ai\nDisallow : /llms.txt # keep out", 'GPTBot', '/llms.txt', false ),
			'windows line endings'             => array( "User-agent: GPTBot\r\nDisallow: /\r\n", 'GPTBot', '/a.md', false ),
			'byte order mark'                  => array( "\xEF\xBB\xBFUser-agent: GPTBot\nDisallow: /", 'GPTBot', '/a.md', false ),
			'sitemap lines are skipped'        => array( "Sitemap: https://example.com/s.xml\nUser-agent: GPTBot\nDisallow: /", 'GPTBot', '/a.md', false ),
			'rules before any agent are lost'  => array( "Disallow: /\nUser-agent: GPTBot\nAllow: /", 'GPTBot', '/a.md', true ),
			'no group means allowed'           => array( "Sitemap: https://example.com/s.xml", 'GPTBot', '/a.md', true ),
			'subfolder path'                   => array( "User-agent: GPTBot\nDisallow: /blog/", 'GPTBot', '/blog/about.md', false ),
		);
	}
}
