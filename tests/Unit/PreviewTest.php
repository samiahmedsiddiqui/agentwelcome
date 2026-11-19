<?php
/**
 * Tests for the choice between the formatted page and the plain Markdown.
 *
 * @package AgentWelcome
 */

declare(strict_types=1);

namespace AgentWelcome\Tests\Unit;

use AgentWelcome\Http\Preview;
use PHPUnit\Framework\TestCase;

final class PreviewTest extends TestCase {

	private const NAVIGATION = array(
		'accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
		'dest'   => 'document',
		'mode'   => 'navigate',
	);

	public function test_agents_get_the_plain_markdown_by_default(): void {
		$this->assertFalse( Preview::decide( null, null, array(), false ) );
		$this->assertFalse( Preview::decide( null, null, array( 'accept' => 'text/html,*/*' ), false ) );
		$this->assertFalse( Preview::decide( null, null, array( 'accept' => 'text/html,*/*' ), true ) );
		$this->assertFalse( Preview::decide( null, null, array( 'accept' => 'text/markdown' ), true ) );
	}

	public function test_the_preview_argument_asks_for_the_formatted_page(): void {
		$this->assertTrue( Preview::decide( '', null, array(), false ) );
		$this->assertTrue( Preview::decide( '1', null, array(), false ) );
		$this->assertTrue( Preview::decide( 'yes', null, array(), false ) );
	}

	public function test_a_switched_off_argument_does_not_count(): void {
		foreach ( array( '0', 'false', 'OFF', 'no' ) as $value ) {
			$this->assertFalse( Preview::decide( $value, null, array(), false ), $value );
		}
	}

	public function test_the_raw_argument_wins(): void {
		$this->assertFalse( Preview::decide( '1', '', array(), false ) );
		$this->assertFalse( Preview::decide( null, '1', self::NAVIGATION, true ) );
		$this->assertTrue( Preview::decide( '1', '0', array(), false ) );
	}

	public function test_a_browser_page_navigation_gets_the_formatted_page_only_when_the_setting_is_on(): void {
		$this->assertTrue( Preview::decide( null, null, self::NAVIGATION, true ) );
		$this->assertFalse( Preview::decide( null, null, self::NAVIGATION, false ) );
	}

	public function test_a_browser_needs_all_three_signs(): void {
		foreach ( array( 'accept', 'dest', 'mode' ) as $missing ) {
			$headers = self::NAVIGATION;
			unset( $headers[ $missing ] );

			$this->assertFalse( Preview::decide( null, null, $headers, true ), $missing );
		}
	}

	public function test_other_fetches_from_a_browser_get_the_plain_markdown(): void {
		$this->assertFalse( Preview::decide( null, null, array_merge( self::NAVIGATION, array( 'dest' => 'empty' ) ), true ) );
		$this->assertFalse( Preview::decide( null, null, array_merge( self::NAVIGATION, array( 'mode' => 'cors' ) ), true ) );
		$this->assertFalse( Preview::decide( null, null, array_merge( self::NAVIGATION, array( 'dest' => 'iframe' ) ), true ) );
	}

	public function test_header_values_are_compared_without_case(): void {
		$this->assertTrue( Preview::decide( null, null, array( 'accept' => 'TEXT/HTML', 'dest' => 'Document', 'mode' => 'Navigate' ), true ) ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing
	}
}
