<?php
/**
 * Tests for the language and path read from an address.
 *
 * @package AgentWelcome
 */

declare(strict_types=1);

namespace AgentWelcome\Tests\Unit;

use AgentWelcome\Integrations\Wpml;
use PHPUnit\Framework\TestCase;

final class WpmlTest extends TestCase {

	private const DIRECTORIES = array(
		'en'      => 'https://example.org',
		'ar'      => 'https://example.org/ar',
		'zh-hans' => 'https://example.org/zh-hans',
	);

	/**
	 * @dataProvider addresses
	 *
	 * @param array<string, string> $homes    Home address by language.
	 * @param array{string, string} $expected Language and path.
	 */
	public function test_matches_address( string $host, string $path, array $homes, array $expected ): void {
		$this->assertSame(
			array(
				'language' => $expected[0],
				'path'     => $expected[1],
			),
			Wpml::match( $host, $path, $homes )
		);
	}

	/**
	 * @return array<string, array{string, string, array<string, string>, array{string, string}}>
	 */
	public function addresses(): array {
		$subdirectory = array(
			'en' => 'https://example.org/blog',
			'ar' => 'https://example.org/blog/ar',
		);
		$all          = array(
			'en' => 'https://example.org/en',
			'ar' => 'https://example.org/ar',
		);
		$domains      = array(
			'en' => 'https://example.org',
			'ar' => 'https://example.ae',
		);

		return array(
			'default'                    => array( 'example.org', '/about.md', self::DIRECTORIES, array( 'en', 'about.md' ) ),
			'directory'                  => array( 'example.org', '/ar/products/example.md', self::DIRECTORIES, array( 'ar', 'products/example.md' ) ),
			'directory with a hyphen'    => array( 'example.org', '/zh-hans/index.md', self::DIRECTORIES, array( 'zh-hans', 'index.md' ) ),
			'language home'              => array( 'example.org', '/ar/', self::DIRECTORIES, array( 'ar', '' ) ),
			'slug that starts like one'  => array( 'example.org', '/arabic.md', self::DIRECTORIES, array( 'en', 'arabic.md' ) ),
			'www and port'               => array( 'WWW.example.org:443', '/ar/about.md', self::DIRECTORIES, array( 'ar', 'about.md' ) ),
			'unknown host'               => array( 'internal', '/ar/about.md', self::DIRECTORIES, array( 'ar', 'about.md' ) ),
			'index.php permalinks'       => array( 'example.org', '/index.php/ar/about.md', self::DIRECTORIES, array( 'ar', 'about.md' ) ),
			'site in a folder'           => array( 'example.org', '/blog/ar/about.md', $subdirectory, array( 'ar', 'about.md' ) ),
			'default with its directory' => array( 'example.org', '/en/about.md', $all, array( 'en', 'about.md' ) ),
			'domain'                     => array( 'example.ae', '/about.md', $domains, array( 'ar', 'about.md' ) ),
			'default domain'             => array( 'example.org', '/ar/about.md', $domains, array( 'en', 'ar/about.md' ) ),
		);
	}

	public function test_moves_an_address_under_its_language(): void {
		$homes = self::DIRECTORIES;

		$this->assertSame( 'https://example.org/ar/blog/post/', Wpml::localise( 'https://example.org/blog/post/', 'ar', $homes ) );
		$this->assertSame( 'https://example.org/ar/blog/post/', Wpml::localise( 'https://example.org/ar/blog/post/', 'ar', $homes ) );
		$this->assertSame( 'https://example.org/zh-hans/contact', Wpml::localise( 'https://example.org/ar/contact', 'zh-hans', $homes ) );
		$this->assertSame( 'https://example.org/blog/post/', Wpml::localise( 'https://example.org/ar/blog/post/', 'en', $homes ) );
		$this->assertSame( 'https://example.org/ar/', Wpml::localise( 'https://example.org/', 'ar', $homes ) );
	}

	public function test_address_outside_every_home_matches_nothing(): void {
		$this->assertNull( Wpml::match( 'example.org', '/llms.txt', array( 'en' => 'https://example.org/en' ) ) );
	}

	public function test_writes_percent_escapes_in_lower_case(): void {
		$this->assertSame( 'a/%d9%85-B.md', Wpml::lower_escapes( 'a/%D9%85-B.md' ) );
	}
}
