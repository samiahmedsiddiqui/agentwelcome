<?php
/**
 * Tests for the table that holds the stored Markdown, on a real database.
 *
 * @package AgentWelcome
 */

declare(strict_types=1);

namespace AgentWelcome\Tests\WpEnv;

use AgentWelcome\Cache\Schema;
use WP_UnitTestCase;

final class SchemaTest extends WP_UnitTestCase {

	public function test_the_table_has_the_expected_columns(): void {
		global $wpdb;

		$columns = $wpdb->get_col( 'SHOW COLUMNS FROM ' . Schema::table() );

		$this->assertSame( array( 'post_id', 'markdown', 'content_hash', 'source', 'generator_version', 'generated_at', 'state', 'noindex', 'restricted' ), $columns );
	}

	public function test_upgrading_from_the_first_version_refreshes_copies_and_asks_for_a_warm_up(): void {
		global $wpdb;

		$wpdb->insert(
			Schema::table(),
			array(
				'post_id'           => 5,
				'markdown'          => '# A',
				'content_hash'      => sha1( 'a' ),
				'source'            => 'loopback',
				'generator_version' => '1.0.0',
				'generated_at'      => gmdate( 'Y-m-d H:i:s' ),
				'state'             => 'fresh',
			)
		);
		update_option( Schema::OPTION, '1' );
		delete_option( Schema::WARMUP_OPTION );

		( new Schema() )->maybe_upgrade();

		$this->assertSame( 'stale', $wpdb->get_var( 'SELECT state FROM ' . Schema::table() . ' WHERE post_id = 5' ) );
		$this->assertSame( '1', get_option( Schema::WARMUP_OPTION ) );
		$this->assertSame( Schema::DB_VERSION, get_option( Schema::OPTION ) );
	}

	public function test_upgrading_from_the_second_version_adds_the_restricted_column(): void {
		global $wpdb;

		$table = Schema::table();
		$wpdb->query( "ALTER TABLE {$table} DROP COLUMN restricted" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->insert(
			$table,
			array(
				'post_id'           => 6,
				'markdown'          => '# B',
				'content_hash'      => sha1( 'b' ),
				'source'            => 'loopback',
				'generator_version' => '1.0.0',
				'generated_at'      => gmdate( 'Y-m-d H:i:s' ),
				'state'             => 'fresh',
				'noindex'           => 0,
			)
		);
		update_option( Schema::OPTION, '2' );
		delete_option( Schema::WARMUP_OPTION );

		( new Schema() )->maybe_upgrade();

		$this->assertContains( 'restricted', $wpdb->get_col( "SHOW COLUMNS FROM {$table}" ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$this->assertSame( 'stale', $wpdb->get_var( "SELECT state FROM {$table} WHERE post_id = 6" ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$this->assertNull( $wpdb->get_var( "SELECT restricted FROM {$table} WHERE post_id = 6" ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$this->assertSame( '1', get_option( Schema::WARMUP_OPTION ) );
		$this->assertSame( '3', get_option( Schema::OPTION ) );
	}

	public function test_the_primary_key_is_the_page_id(): void {
		global $wpdb;

		$keys = $wpdb->get_results( 'SHOW INDEX FROM ' . Schema::table() . " WHERE Key_name = 'PRIMARY'", ARRAY_A );

		$this->assertCount( 1, $keys );
		$this->assertSame( 'post_id', $keys[0]['Column_name'] );
	}

	public function test_the_installed_version_is_recorded(): void {
		$this->assertSame( Schema::DB_VERSION, get_option( Schema::OPTION ) );
	}

	public function test_running_the_upgrade_again_changes_nothing(): void {
		global $wpdb;

		( new Schema() )->maybe_upgrade();

		$this->assertSame( Schema::table(), $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', Schema::table() ) ) );
	}

	public function test_the_table_is_dropped_with_a_deleted_site(): void {
		$tables = ( new Schema() )->drop_with_site( array( 'wp_2_posts' ) );

		$this->assertContains( Schema::table(), $tables );
	}
}
