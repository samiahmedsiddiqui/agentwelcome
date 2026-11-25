<?php
/**
 * Tests for reading and cleaning page titles.
 *
 * @package AgentWelcome
 */

declare(strict_types=1);

namespace AgentWelcome\Tests\Unit;

use AgentWelcome\Rendering\PageTitle;
use PHPUnit\Framework\TestCase;

final class PageTitleTest extends TestCase {

	/**
	 * @dataProvider strip_cases
	 * @param string   $title    Title tag.
	 * @param string[] $names    Site names.
	 * @param string   $expected Expected title.
	 */
	public function test_strip_site_name( string $title, array $names, string $expected ): void {
		$this->assertSame( $expected, PageTitle::strip_site_name( $title, $names ) );
	}

	/**
	 * @return array<string, array{string, string[], string}>
	 */
	public static function strip_cases(): array {
		return array(
			'pipe at the end'        => array( 'Product | Site Name', array( 'Site Name' ), 'Product' ),
			'dash at the end'        => array( 'Product - Site Name', array( 'Site Name' ), 'Product' ),
			'en dash'                => array( 'Product – Site Name', array( 'Site Name' ), 'Product' ),
			'em dash'                => array( 'Product — Site Name', array( 'Site Name' ), 'Product' ),
			'middle dot'             => array( 'Product · Site Name', array( 'Site Name' ), 'Product' ),
			'colon'                  => array( 'Product: Site Name', array( 'Site Name' ), 'Product' ),
			'guillemet'              => array( 'Product » Site Name', array( 'Site Name' ), 'Product' ),
			'bullet'                 => array( 'Product • Site Name', array( 'Site Name' ), 'Product' ),
			'no spaces'              => array( 'Product|Site Name', array( 'Site Name' ), 'Product' ),
			'ignores case'           => array( 'Product | SITE NAME', array( 'site name' ), 'Product' ),
			'name at the start'      => array( 'Site Name | Product', array( 'Site Name' ), 'Product' ),
			'one end only'           => array( 'Pricing | Plans | Site Name', array( 'Site Name' ), 'Pricing | Plans' ),
			'end is tried first'     => array( 'Site Name | Pricing | Site Name', array( 'Site Name' ), 'Site Name | Pricing' ),
			'other name is kept'     => array( 'Foo - Bar', array( 'Site Name' ), 'Foo - Bar' ),
			'name needs a separator' => array( 'About Site Name', array( 'Site Name' ), 'About Site Name' ),
			'partial name is kept'   => array( 'Foo | My Site Name', array( 'Site Name' ), 'Foo | My Site Name' ),
			'only the name'          => array( 'Site Name', array( 'Site Name' ), 'Site Name' ),
			'would be empty'         => array( '| Site Name', array( 'Site Name' ), '| Site Name' ),
			'second name matches'    => array( 'Product | Brand', array( 'Site Name', 'Brand' ), 'Product' ),
			'empty names are skipped' => array( 'Product | Site', array( '', 'Site' ), 'Product' ),
			'special characters'     => array( 'Product | A+B (Co.)', array( 'A+B (Co.)' ), 'Product' ),
		);
	}

	public function test_read_decodes_the_title_and_finds_the_site_name(): void {
		$html = '<html><head><title>Fish &amp; Chips &#8211;
 Caf&eacute;</title><meta property="og:site_name" content="Caf&eacute; &amp; Co"></head></html>';

		$this->assertSame(
			array(
				'title'     => 'Fish & Chips – Café',
				'site_name' => 'Café & Co',
			),
			PageTitle::read( $html )
		);
	}

	public function test_read_without_a_title(): void {
		$this->assertSame(
			array(
				'title'     => '',
				'site_name' => '',
			),
			PageTitle::read( '<html><head></head></html>' )
		);
	}

	public function test_choose_uses_the_page_title_without_the_site_name(): void {
		$this->assertSame(
			array(
				'title'    => 'Product',
				'original' => 'Product | Site',
				'used'     => 'page',
			),
			PageTitle::choose( 'Post', 'Product | Site', array( 'Site' ), true, true, false )
		);
	}

	public function test_choose_keeps_the_full_title_when_stripping_is_off(): void {
		$this->assertSame( 'Product | Site', PageTitle::choose( 'Post', 'Product | Site', array( 'Site' ), false, true, false )['title'] );
	}

	public function test_choose_keeps_the_full_title_on_the_homepage(): void {
		$this->assertSame( 'Home | Site', PageTitle::choose( 'Home', 'Home | Site', array( 'Site' ), true, true, true )['title'] );
	}

	public function test_choose_uses_the_post_title_when_asked(): void {
		$chosen = PageTitle::choose( 'Post', 'Product | Site', array( 'Site' ), true, false, false );

		$this->assertSame( 'Post', $chosen['title'] );
		$this->assertSame( 'post', $chosen['used'] );
	}

	public function test_choose_falls_back_to_the_post_title_without_a_title_tag(): void {
		$chosen = PageTitle::choose( 'Post', '', array(), true, true, false );

		$this->assertSame( 'Post', $chosen['title'] );
		$this->assertSame( 'post', $chosen['used'] );
	}
}
