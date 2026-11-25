<?php
/**
 * Leaves the WooCommerce cart, checkout, account and terms pages out.
 *
 * @package AgentWelcome
 */

declare(strict_types=1);

namespace AgentWelcome\Integrations;

use AgentWelcome\Cache\PageCache;
use AgentWelcome\Contracts\Hookable;
use AgentWelcome\Index\OutputCache;
use WP_Post;

defined( 'ABSPATH' ) || exit;

/** Per-visitor shop pages have nothing for an agent to read, so they are not listed or served. */
final class WooCommerce implements Hookable {

	/** WooCommerce page names that are left out by default. */
	public const PAGES = array( 'cart', 'checkout', 'myaccount', 'terms' );

	/** @var int[]|null IDs of the pages left out, kept for the request. */
	private ?array $ids = null;

	/**
	 * @param PageCache $pages Refreshes pages whose status changes.
	 */
	public function __construct( private PageCache $pages ) {}

	/**
	 * @return void
	 */
	public function register(): void {
		add_filter( 'agentwelcome_is_eligible', array( $this, 'filter' ), 10, 2 );

		foreach ( self::PAGES as $page ) {
			add_action( 'update_option_woocommerce_' . $page . '_page_id', array( $this, 'on_page_changed' ), 10, 2 );
		}
	}

	/**
	 * @param mixed $eligible Whether the page is served so far.
	 * @param mixed $post     The page.
	 * @return mixed False for a shop page, otherwise the value unchanged.
	 */
	public function filter( mixed $eligible, mixed $post = null ): mixed {
		if ( ! $eligible || ! $post instanceof WP_Post || ! $this->is_active() ) {
			return $eligible;
		}

		return ! in_array( $post->ID, $this->ids(), true );
	}

	/**
	 * Refreshes the pages that gained or lost their shop role, and the llms files.
	 *
	 * @param mixed $old Previous page ID.
	 * @param mixed $current New page ID.
	 * @return void
	 */
	public function on_page_changed( mixed $old, mixed $current ): void {
		$this->ids = null;

		$this->pages->queue( array_values( array_filter( array( absint( $old ), absint( $current ) ) ) ) );
		OutputCache::purge();
	}

	/**
	 * @return bool True when WooCommerce is active.
	 */
	private function is_active(): bool {
		return defined( 'WC_VERSION' ) || class_exists( 'WooCommerce' );
	}

	/**
	 * Reads the page IDs the way WooCommerce does, so translation plugins that filter them are respected.
	 *
	 * @return int[] IDs of the pages left out.
	 */
	private function ids(): array {
		if ( null !== $this->ids ) {
			return $this->ids;
		}

		$this->ids = array();

		foreach ( (array) apply_filters( 'agentwelcome_woocommerce_excluded_pages', self::PAGES ) as $page ) {
			$id = absint( apply_filters( 'woocommerce_get_' . $page . '_page_id', get_option( 'woocommerce_' . $page . '_page_id' ) ) ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound, WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound -- WooCommerce's own filter.

			if ( $id > 0 ) {
				$this->ids[] = $id;
			}
		}

		return $this->ids;
	}
}
