<?php
/**
 * Serves /llms.txt, /llms-full.txt and the .md version of pages, before WordPress runs its main query.
 *
 * @package AgentWelcome
 */

declare(strict_types=1);

namespace AgentWelcome\Http;

use AgentWelcome\Cache\PageCache;
use AgentWelcome\Content\Eligibility;
use AgentWelcome\Contracts\Hookable;
use AgentWelcome\Environment\Environment;
use AgentWelcome\Index\LlmsFullTxt;
use AgentWelcome\Index\LlmsTxt;
use AgentWelcome\Index\OutputCache;
use AgentWelcome\Settings\LlmsOptions;
use AgentWelcome\Settings\Options;
use WP;

defined( 'ABSPATH' ) || exit;

/** Answers requests for the Markdown pages, llms.txt and llms-full.txt before WordPress loads a template. */
final class Router implements Hookable {

	/**
	 * @param Urls             $urls         Reads and builds the addresses.
	 * @param Eligibility      $eligibility  Decides whether a page is served.
	 * @param PageCache        $pages        Gives the stored copy of a page.
	 * @param Environment      $environment  Tells whether the plugin is running on this site.
	 * @param LlmsTxt          $llms         Builds llms.txt.
	 * @param LlmsFullTxt      $llms_full    Builds llms-full.txt.
	 * @param LlmsOptions      $llms_options Settings of the llms files.
	 * @param MarkdownResponse $response     Sends the answer.
	 * @param Options|null     $options      Saved settings.
	 */
	public function __construct(
		private Urls $urls,
		private Eligibility $eligibility,
		private PageCache $pages,
		private Environment $environment,
		private LlmsTxt $llms,
		private LlmsFullTxt $llms_full,
		private LlmsOptions $llms_options,
		private MarkdownResponse $response,
		private ?Options $options = null
	) {}

	/**
	 * @return void
	 */
	public function register(): void {
		add_action( 'parse_request', array( $this, 'maybe_serve' ), 1 );
	}

	/**
	 * Serves the request when it is for one of the files of this plugin.
	 *
	 * @param WP $wp The WordPress request.
	 * @return void
	 */
	public function maybe_serve( WP $wp ): void {
		if ( ! Urls::has_pretty_permalinks() || ! $this->is_read_request() || ! $this->environment->is_active() ) {
			return;
		}

		$route = $this->urls->parse_route( (string) $wp->request );

		if ( null === $route ) {
			$this->maybe_serve_negotiated( $wp );
			return;
		}

		if ( Urls::ROUTE_LLMS === $route['type'] ) {
			$this->serve_index( OutputCache::LLMS, fn(): string => $this->llms->build() );
		}

		if ( Urls::ROUTE_LLMS_FULL === $route['type'] ) {
			if ( ! $this->llms_options->full_enabled() ) {
				return;
			}

			$this->serve_index( OutputCache::FULL, fn(): string => $this->llms_full->build() );
		}

		$this->serve_page( $route, trim( (string) $wp->request, '/' ) );
	}

	/**
	 * Sends the Markdown of a page that was requested by its normal address with an Accept header that prefers Markdown.
	 *
	 * Anything that is not served is left to WordPress, so the HTML page answers as usual.
	 *
	 * @param WP $wp The WordPress request.
	 * @return void
	 */
	private function maybe_serve_negotiated( WP $wp ): void {
		if ( null === $this->options || ! $this->options->get( 'markdown_by_accept' ) || ! $this->is_page_request( $wp ) ) {
			return;
		}

		$accept = isset( $_SERVER['HTTP_ACCEPT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_ACCEPT'] ) ) : '';

		if ( ! AcceptHeader::prefers_markdown( $accept ) ) {
			return;
		}

		$post = $this->urls->resolve( trim( (string) $wp->request, '/' ) );

		if ( null === $post || ! $this->eligibility->is_eligible( $post ) ) {
			return;
		}

		$served = $this->pages->fetch( $post );

		if ( null === $served ) {
			return;
		}

		$this->response->send(
			$served->entry->markdown,
			(string) apply_filters( 'agentwelcome_content_type', MarkdownResponse::MARKDOWN, $post ),
			(string) $this->urls->permalink( $post ),
			(int) strtotime( $post->post_modified_gmt . ' UTC' ),
			array(
				'X-AgentWelcome-Cache' => $served->cache,
				'Vary'                 => 'Accept',
				'Cache-Control'        => 'private, no-store',
			)
		);

		$this->terminate();
	}

	/**
	 * Whether the request is for a front end page, not the admin, REST, AJAX, a feed, a preview, a search or a file.
	 *
	 * @param WP $wp The WordPress request.
	 * @return bool True when it can be a page.
	 */
	private function is_page_request( WP $wp ): bool {
		if ( is_admin() || wp_doing_ajax() || wp_doing_cron() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
			return false;
		}

		$request = trim( (string) $wp->request, '/' );

		if ( 1 === preg_match( '#^(wp-json|wp-admin|wp-login\.php)(/|$)|(^|/)(feed|embed|trackback)(/|$)#', $request ) ) {
			return false;
		}

		foreach ( array( 's', 'preview', 'preview_id', 'feed', 'rest_route', 'embed', 'customize_changeset_uuid' ) as $var ) {
			if ( isset( $wp->query_vars[ $var ] ) || isset( $_GET[ $var ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Only checks that the argument exists.
				return false;
			}
		}

		return true;
	}

	/**
	 * Sends an llms file, from the saved copy when there is one, and stops.
	 *
	 * @param string             $key   Name of the saved copy.
	 * @param callable(): string $build Builds the file when there is no saved copy.
	 * @return never
	 */
	private function serve_index( string $key, callable $build ): void {
		$this->response->send( OutputCache::remember( $key, $build ), MarkdownResponse::TEXT );
		$this->terminate();
	}

	/**
	 * Sends the Markdown of a page, or redirects to its real address, or lets WordPress carry on.
	 *
	 * @param array{type: string, path: string, home_alias: bool} $route   The parsed route.
	 * @param string                                              $request The requested path.
	 * @return void
	 */
	private function serve_page( array $route, string $request ): void {
		$post  = $this->urls->resolve( $route['path'], $route['home_alias'] );
		$paged = 1;

		if ( null === $post ) {
			$view = Urls::split_view( $route['path'] );
			$post = null === $view ? null : $this->urls->resolve( $view['path'] );

			// A view that does not exist is not redirected to the page.
			if ( null !== $view && null !== $post ) {
				if ( $view['paged'] > $this->urls->view_count( $post ) || ! $this->eligibility->is_eligible( $post ) ) {
					$this->not_found( $route, $request );
					return;
				}

				$paged = $view['paged'];
			}
		}

		if ( null === $post ) {
			$this->redirect_to_canonical( $route, $request );
			$this->not_found( $route, $request );
			return;
		}

		if ( ! $this->eligibility->is_eligible( $post ) ) {
			$this->not_found( $route, $request );
			return;
		}

		$served = $this->pages->fetch( $post, $paged );

		if ( null === $served ) {
			$this->response->unavailable();
			$this->terminate();
		}

		// A paginated view that could not be rendered has no copy yet.
		if ( $this->eligibility->hides( $served->entry ) || ( $paged > 1 && '' === $served->entry->markdown ) ) {
			$this->not_found( $route, $request );
			return;
		}

		$this->response->send(
			$served->entry->markdown,
			(string) apply_filters( 'agentwelcome_content_type', MarkdownResponse::MARKDOWN, $post ),
			(string) $this->urls->view_url( $post, $paged ),
			(int) strtotime( $post->post_modified_gmt . ' UTC' ),
			array( 'X-AgentWelcome-Cache' => $served->cache )
		);

		$this->terminate();
	}

	/**
	 * Sends a request for a valid page under the wrong path (such as its original permalink) to the real Markdown address.
	 *
	 * @param array{type: string, path: string, home_alias: bool} $route   The parsed route.
	 * @param string                                              $request The requested path.
	 * @return void
	 */
	private function redirect_to_canonical( array $route, string $request ): void {
		$post = $this->urls->resolve( $route['path'], $route['home_alias'], false );

		if ( null === $post || ! $this->eligibility->is_eligible( $post ) ) {
			return;
		}

		$target = $this->urls->markdown_url( $post );

		if ( null === $target || $this->urls->markdown_path( $post ) === $request ) {
			return;
		}

		wp_safe_redirect( $target, 301 );
		$this->terminate();
	}

	/**
	 * Answers a Markdown address that is not served with a plain text 404, before WordPress or another plugin can redirect it.
	 *
	 * @param array{type: string, path: string, home_alias: bool} $route   The parsed route.
	 * @param string                                              $request The requested path.
	 * @return void
	 */
	private function not_found( array $route, string $request ): void {
		/**
		 * Filters whether a Markdown address that is not served gets a 404 from this plugin.
		 *
		 * @param bool   $answer  True to answer with a 404, false to leave the request to WordPress.
		 * @param array  $route   The parsed route.
		 * @param string $request The requested path.
		 */
		if ( ! apply_filters( 'agentwelcome_answer_unserved_md', true, $route, $request ) ) {
			return;
		}

		$this->response->not_found();
		$this->terminate();
	}

	/**
	 * Whether the request only reads.
	 *
	 * @return bool True for GET and HEAD.
	 */
	private function is_read_request(): bool {
		$method = isset( $_SERVER['REQUEST_METHOD'] ) ? strtoupper( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) ) : 'GET';

		return in_array( $method, array( 'GET', 'HEAD' ), true );
	}

	/**
	 * Ends the request.
	 *
	 * @return never
	 */
	private function terminate(): void {
		exit;
	}
}
