<?php
/**
 * Generates every page once after an install or an upgrade.
 *
 * @package AgentWelcome
 */

declare(strict_types=1);

namespace AgentWelcome\Cache;

use AgentWelcome\Contracts\Hookable;
use AgentWelcome\Environment\Environment;

defined( 'ABSPATH' ) || exit;

/** Starts the background job that generates every page after an install or an upgrade, because llms.txt lists only generated pages. */
final class Warmup implements Hookable {

	/**
	 * @param Batch       $batch       Runs the background job.
	 * @param Environment $environment Tells whether the plugin is running on this site.
	 */
	public function __construct( private Batch $batch, private Environment $environment ) {}

	/**
	 * @return void
	 */
	public function register(): void {
		add_action( 'init', array( $this, 'maybe_start' ), 20 );
	}

	/**
	 * Starts the job once, when the plugin is running on this site.
	 *
	 * @return void
	 */
	public function maybe_start(): void {
		if ( '1' !== get_option( Schema::WARMUP_OPTION ) || ! $this->environment->is_active() ) {
			return;
		}

		delete_option( Schema::WARMUP_OPTION );

		if ( ! $this->batch->is_running() ) {
			$this->batch->start( false );
		}
	}
}
