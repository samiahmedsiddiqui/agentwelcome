<?php
/**
 * Stores generated Markdown in the agentwelcome_pages table.
 *
 * @package AgentWelcome
 */

declare(strict_types=1);

namespace AgentWelcome\Cache;

use AgentWelcome\Content\PostMeta;
use AgentWelcome\Http\Urls;
use AgentWelcome\Index\OutputCache;
use WP_Post;

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Plugin-owned table.

/** Store backed by the agentwelcome_pages table. The cached llms files are cleared whenever stored copies change. */
final class WpdbStore implements Store {

	/**
	 * @param Urls $urls Builds the Markdown address for the update action.
	 */
	public function __construct( private Urls $urls ) {}

	/**
	 * @param int  $post_id       Page ID.
	 * @param bool $with_markdown False to leave the Markdown text out of the query.
	 * @return Entry|null The stored copy, or null when none exists.
	 */
	public function get( int $post_id, bool $with_markdown = true ): ?Entry {
		global $wpdb;

		$table   = Schema::table();
		$columns = $with_markdown ? '*' : "post_id, '' AS markdown, content_hash, source, generator_version, generated_at, state, noindex";
		$row     = $wpdb->get_row( $wpdb->prepare( "SELECT {$columns} FROM {$table} WHERE post_id = %d", $post_id ), ARRAY_A );

		if ( ! is_array( $row ) ) {
			return null;
		}

		return new Entry(
			(int) $row['post_id'],
			(string) $row['markdown'],
			(string) $row['content_hash'],
			(string) $row['source'],
			(string) $row['generator_version'],
			(string) $row['generated_at'],
			(string) $row['state'],
			null === $row['noindex'] ? null : (bool) (int) $row['noindex']
		);
	}

	/**
	 * @param Entry $entry Copy to store, replacing any earlier one.
	 * @return void
	 */
	public function save( Entry $entry ): void {
		global $wpdb;

		$table    = Schema::table();
		$previous = $wpdb->get_var( $wpdb->prepare( "SELECT content_hash FROM {$table} WHERE post_id = %d", $entry->post_id ) );

		$wpdb->replace(
			Schema::table(),
			array(
				'post_id'           => $entry->post_id,
				'markdown'          => $entry->markdown,
				'content_hash'      => $entry->content_hash,
				'source'            => $entry->source,
				'generator_version' => $entry->generator_version,
				'generated_at'      => $entry->generated_at,
				'state'             => $entry->state,
				'noindex'           => null === $entry->noindex ? null : (int) $entry->noindex,
			),
			array( '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%d' )
		);

		OutputCache::purge();

		if ( $previous !== $entry->content_hash ) {
			$this->announce( $entry->post_id );
		}
	}

	/**
	 * @param int $post_id Page ID.
	 * @return void
	 */
	public function delete( int $post_id ): void {
		global $wpdb;

		$removed = $wpdb->delete( Schema::table(), array( 'post_id' => $post_id ), array( '%d' ) );
		delete_post_meta( $post_id, PostMeta::PAGE_DESCRIPTION );
		delete_post_meta( $post_id, PostMeta::TITLE );
		OutputCache::purge();

		if ( $removed ) {
			$this->announce( $post_id );
		}
	}

	/**
	 * @param int $post_id Page ID.
	 * @return void
	 */
	public function mark_stale( int $post_id ): void {
		global $wpdb;

		$table = Schema::table();

		$wpdb->query( $wpdb->prepare( "UPDATE {$table} SET state = %s WHERE post_id = %d", Entry::STALE, $post_id ) );
	}

	/**
	 * @return void
	 */
	public function mark_all_stale(): void {
		global $wpdb;

		$table = Schema::table();

		$wpdb->query( $wpdb->prepare( "UPDATE {$table} SET state = %s", Entry::STALE ) );
	}

	/**
	 * @return void
	 */
	public function clear(): void {
		global $wpdb;

		$table = Schema::table();

		$wpdb->query( "DELETE FROM {$table}" );
		delete_post_meta_by_key( PostMeta::TITLE );
		OutputCache::purge();

		/**
		 * Fires after every stored copy has been cleared.
		 */
		do_action( 'agentwelcome_cache_cleared' );
	}

	/**
	 * Tells other code that the Markdown at a page's address has changed, so a host or CDN cache can be purged.
	 *
	 * @param int $post_id Page ID.
	 * @return void
	 */
	private function announce( int $post_id ): void {
		$post = get_post( $post_id );
		$url  = $post instanceof WP_Post ? $this->urls->markdown_url( $post ) : null;

		if ( null === $url ) {
			return;
		}

		/**
		 * Fires after the stored Markdown of a page changed or was removed.
		 *
		 * @param int    $post_id Page ID.
		 * @param string $url     Markdown address of the page.
		 */
		do_action( 'agentwelcome_markdown_updated', $post_id, $url );
	}

	/**
	 * @param int[] $post_ids Page IDs.
	 * @return array<int, bool|null> Result by page ID, for the pages that have a stored copy.
	 */
	public function noindex_flags( array $post_ids ): array {
		global $wpdb;

		$ids = array_values( array_filter( array_map( 'absint', $post_ids ) ) );

		if ( array() === $ids ) {
			return array();
		}

		$table = Schema::table();
		$marks = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
		$rows  = (array) $wpdb->get_results( $wpdb->prepare( "SELECT post_id, noindex FROM {$table} WHERE post_id IN ({$marks})", $ids ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- Placeholders are built from the count of IDs.
		$flags = array();

		foreach ( $rows as $row ) {
			$flags[ (int) $row['post_id'] ] = null === $row['noindex'] ? null : (bool) (int) $row['noindex'];
		}

		return $flags;
	}

	/**
	 * @return array{fresh: int, stale: int} Copies per state.
	 */
	public function counts(): array {
		global $wpdb;

		$table  = Schema::table();
		$rows   = (array) $wpdb->get_results( "SELECT state, COUNT(*) AS total FROM {$table} GROUP BY state", ARRAY_A );
		$counts = array(
			'fresh' => 0,
			'stale' => 0,
		);

		foreach ( $rows as $row ) {
			$counts[ Entry::STALE === $row['state'] ? 'stale' : 'fresh' ] += (int) $row['total'];
		}

		return $counts;
	}

	/**
	 * @param int $after Only IDs above this one.
	 * @param int $limit Largest number of IDs to return.
	 * @return int[] Page IDs in ascending order.
	 */
	public function ids( int $after, int $limit ): array {
		global $wpdb;

		$table = Schema::table();

		return array_map( 'intval', (array) $wpdb->get_col( $wpdb->prepare( "SELECT post_id FROM {$table} WHERE post_id > %d ORDER BY post_id ASC LIMIT %d", $after, $limit ) ) );
	}
}
