<?php
/**
 * Tests for reading noindex from a page's source.
 *
 * @package AgentWelcome
 */

declare(strict_types=1);

namespace AgentWelcome\Tests\Unit;

use AgentWelcome\Rendering\Robots;
use PHPUnit\Framework\TestCase;

final class RobotsTest extends TestCase {

	/**
	 * @dataProvider pages
	 */
	public function test_reads_the_source( string $html, string $header, string $expected ): void {
		$this->assertSame( $expected, Robots::source( $html, $header ) );
		$this->assertSame( '' !== $expected, Robots::is_noindex( $html, $header ) );
	}

	/**
	 * @return array<string, array{string, string, string}>
	 */
	public function pages(): array {
		$head = static fn ( string $meta ): string => '<html><head><title>T</title>' . $meta . '</head><body><p>Body</p></body></html>';

		return array(
			'no robots information'        => array( $head( '' ), '', '' ),
			'noindex meta'                 => array( $head( '<meta name="robots" content="noindex">' ), '', Robots::META ),
			'noindex and nofollow'         => array( $head( "<meta name='robots' content='noindex, nofollow' />" ), '', Robots::META ),
			'none means noindex'           => array( $head( '<meta name="robots" content="none">' ), '', Robots::META ),
			'upper case'                   => array( $head( '<META NAME="ROBOTS" CONTENT="NoIndex">' ), '', Robots::META ),
			'content before name'          => array( $head( '<meta content="noindex" name="robots">' ), '', Robots::META ),
			'index and follow'             => array( $head( '<meta name="robots" content="index, follow">' ), '', '' ),
			'max-image-preview only'       => array( $head( '<meta name="robots" content="max-image-preview:large">' ), '', '' ),
			'one of two tags'              => array( $head( '<meta name="robots" content="index"><meta name="robots" content="noindex">' ), '', Robots::META ),
			'other bot is ignored'         => array( $head( '<meta name="googlebot" content="noindex">' ), '', '' ),
			'tag in the body is ignored'   => array( '<html><head></head><body><meta name="robots" content="noindex"></body></html>', '', '' ),
			'header noindex'               => array( $head( '' ), 'noindex', Robots::HEADER ),
			'header with other values'     => array( $head( '' ), 'noindex, nofollow', Robots::HEADER ),
			'header none'                  => array( $head( '' ), 'NONE', Robots::HEADER ),
			'header for one bot'           => array( $head( '' ), 'googlebot: noindex', '' ),
			'header for all with a name'   => array( $head( '' ), 'robots: noindex', Robots::HEADER ),
			'header value with a colon'    => array( $head( '' ), 'max-snippet: 50', '' ),
			'second header line'           => array( $head( '' ), "nofollow\nnoindex", Robots::HEADER ),
			'bot then global line'         => array( $head( '' ), "googlebot: noindex\nnofollow", '' ),
			'meta wins over the header'    => array( $head( '<meta name="robots" content="noindex">' ), 'noindex', Robots::META ),
			'entities in the content'      => array( $head( '<meta name="robots" content="&#110;oindex">' ), '', Robots::META ),
			'empty html'                   => array( '', '', '' ),
		);
	}
}
