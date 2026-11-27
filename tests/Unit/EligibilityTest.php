<?php
/**
 * Tests for which stored copies are left out.
 *
 * @package AgentWelcome
 */

declare(strict_types=1);

namespace AgentWelcome\Tests\Unit;

use AgentWelcome\Cache\Entry;
use AgentWelcome\Content\Eligibility;
use AgentWelcome\Content\Exclusions;
use AgentWelcome\Content\PostTypes;
use AgentWelcome\Http\Urls;
use AgentWelcome\Settings\Options;
use PHPUnit\Framework\TestCase;

final class EligibilityTest extends TestCase {

	protected function tearDown(): void {
		$GLOBALS['agentwelcome_test_options'] = array();
	}

	/**
	 * @dataProvider copies
	 */
	public function test_hides_noindex_and_restricted_copies( ?bool $noindex, ?string $restricted, bool $hidden ): void {
		$this->assertSame( $hidden, $this->eligibility()->hides( $this->entry( $noindex, $restricted ) ) );
	}

	/**
	 * @return array<string, array{bool|null, string|null, bool}>
	 */
	public function copies(): array {
		return array(
			'readable and indexable' => array( false, '', false ),
			'unknown'                => array( null, null, false ),
			'noindex'                => array( true, '', true ),
			'restricted'             => array( null, 'http_403', true ),
		);
	}

	public function test_a_page_without_a_copy_is_not_hidden(): void {
		$this->assertFalse( $this->eligibility()->hides( null ) );
	}

	public function test_the_settings_include_such_pages_again(): void {
		$GLOBALS['agentwelcome_test_options'][ Options::KEY ] = array(
			'exclude_noindex'    => false,
			'exclude_restricted' => false,
		);

		$this->assertFalse( $this->eligibility()->hides( $this->entry( true, '' ) ) );
		$this->assertFalse( $this->eligibility()->hides( $this->entry( null, 'http_403' ) ) );
	}

	private function eligibility(): Eligibility {
		$options = new Options();

		return new Eligibility( new PostTypes( $options ), new Exclusions( $options ), new Urls() );
	}

	private function entry( ?bool $noindex, ?string $restricted ): Entry {
		return new Entry( 1, '', '', 'loopback', '1.0.0', '2026-01-01 00:00:00', Entry::FRESH, $noindex, $restricted );
	}
}
