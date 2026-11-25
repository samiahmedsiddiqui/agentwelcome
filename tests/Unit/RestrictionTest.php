<?php
/**
 * Tests for telling that visitors cannot read a page.
 *
 * @package AgentWelcome
 */

declare(strict_types=1);

namespace AgentWelcome\Tests\Unit;

use AgentWelcome\Rendering\Restriction;
use PHPUnit\Framework\TestCase;

final class RestrictionTest extends TestCase {

	/**
	 * @dataProvider statuses
	 */
	public function test_reads_the_status( int $code, bool $basic, ?string $expected ): void {
		$this->assertSame( $expected, Restriction::from_status( $code, $basic ) );
	}

	/**
	 * @return array<string, array{int, bool, string|null}>
	 */
	public function statuses(): array {
		return array(
			'forbidden'                 => array( 403, false, 'http_403' ),
			'forbidden with a challenge' => array( 403, true, 'http_403' ),
			'unauthorized'              => array( 401, false, 'http_401' ),
			'site-wide basic password'  => array( 401, true, null ),
			'ok'                        => array( 200, false, null ),
			'not found'                 => array( 404, false, null ),
			'server error'              => array( 500, false, null ),
		);
	}

	/**
	 * @dataProvider redirects
	 */
	public function test_compares_the_paths( string $page, string $landed, ?string $expected ): void {
		$this->assertSame( $expected, Restriction::from_redirect( $page, $landed ) );
	}

	/**
	 * @return array<string, array{string, string, string|null}>
	 */
	public function redirects(): array {
		$page = 'http://example.test/members/area/';

		return array(
			'the same address'         => array( $page, $page, null ),
			'trailing slash removed'   => array( $page, 'http://example.test/members/area', null ),
			'trailing slash added'     => array( 'http://example.test/members/area', $page, null ),
			'http to https'            => array( $page, 'https://example.test/members/area/', null ),
			'www added'                => array( $page, 'http://www.example.test/members/area/', null ),
			'www removed'              => array( 'http://www.example.test/members/area/', $page, null ),
			'the render argument'      => array( $page, $page . '?agentwelcome_render=123', null ),
			'a fragment'               => array( $page, $page . '#top', null ),
			'a login page'             => array( $page, 'http://example.test/login/?redirect_to=%2Fmembers%2Farea%2F', 'redirect:/login/' ),
			'the login script'         => array( $page, 'http://example.test/wp-login.php', 'redirect:/wp-login.php' ),
			'the home page'            => array( $page, 'http://example.test/', 'redirect:/' ),
			'another site'             => array( $page, 'https://sso.example.org/auth/start', 'redirect:/auth/start' ),
			'a site in a subfolder'    => array( 'http://example.test/blog/members/', 'http://example.test/blog/login/', 'redirect:/blog/login/' ),
		);
	}

	public function test_the_code_has_no_path(): void {
		$this->assertSame( 'redirect', Restriction::code( 'redirect:/login/' ) );
		$this->assertSame( 'http_403', Restriction::code( 'http_403' ) );
	}
}
