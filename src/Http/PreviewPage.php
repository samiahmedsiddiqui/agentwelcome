<?php
/**
 * Builds the small web page that shows a Markdown page formatted, and the headers it is sent with.
 *
 * @package AgentWelcome
 */

declare(strict_types=1);

namespace AgentWelcome\Http;

defined( 'ABSPATH' ) || exit;

/** The formatted view of a Markdown page, for people who open a Markdown link in a browser. */
final class PreviewPage {

	/** Content type of the page. */
	public const CONTENT_TYPE = 'text/html; charset=utf-8';

	/**
	 * @param string $script_url Address of the script that formats the Markdown.
	 * @param string $style_url  Address of the stylesheet.
	 */
	public function __construct( private string $script_url, private string $style_url ) {}

	/**
	 * Builds the page. The Markdown is written into the page as escaped text and formatted by the script in the browser.
	 *
	 * @param string $title    Title of the page.
	 * @param string $markdown The stored Markdown.
	 * @param string $page_url Address of the HTML page.
	 * @param string $raw_url  Address of the plain Markdown.
	 * @param string $language Language of the site, such as en-US.
	 * @return string The HTML.
	 */
	public function render( string $title, string $markdown, string $page_url, string $raw_url, string $language ): string {
		$html  = "<!DOCTYPE html>\n";
		$html .= '<html lang="' . esc_attr( $language ) . "\">\n<head>\n";
		$html .= "<meta charset=\"utf-8\">\n";
		$html .= "<meta name=\"viewport\" content=\"width=device-width, initial-scale=1\">\n";
		$html .= "<meta name=\"robots\" content=\"noindex, nofollow\">\n";
		$html .= "<meta name=\"referrer\" content=\"no-referrer\">\n";
		/* translators: %s: page title. */
		$html .= '<title>' . esc_html( sprintf( __( '%s (Markdown)', 'agentwelcome' ), $title ) ) . "</title>\n";

		if ( '' !== $page_url ) {
			$html .= '<link rel="canonical" href="' . esc_url( $page_url ) . "\">\n";
		}

		// This page is not a WordPress template, so the files cannot be enqueued.
		$html .= '<link rel="stylesheet" href="' . esc_url( $this->style_url ) . "\">\n"; // phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedStylesheet
		$html .= '<script src="' . esc_url( $this->script_url ) . "\" defer></script>\n"; // phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedScript
		$html .= "</head>\n<body>\n";
		$html .= '<header class="agentwelcome-bar"><span class="agentwelcome-badge">' . esc_html__( 'Markdown', 'agentwelcome' ) . '</span><nav>';

		if ( '' !== $page_url ) {
			$html .= '<a href="' . esc_url( $page_url ) . '">' . esc_html__( 'Open the page', 'agentwelcome' ) . '</a>';
		}

		$html .= '<a href="' . esc_url( $raw_url ) . '">' . esc_html__( 'Raw Markdown', 'agentwelcome' ) . "</a></nav></header>\n";
		$html .= "<main>\n<article id=\"agentwelcome-rendered\" class=\"agentwelcome-rendered\" data-agentwelcome-metadata=\"list\"></article>\n";
		// esc_textarea() keeps "&amp;" in the Markdown as text, where esc_html() would not. The first line break after the tag is ignored by browsers.
		$html .= '<pre id="agentwelcome-markdown" class="agentwelcome-source" data-agentwelcome-hide-source>' . "\n" . esc_textarea( $markdown ) . "</pre>\n";
		$html .= "</main>\n</body>\n</html>\n";

		return $html;
	}

	/**
	 * Headers that go with the page. They keep it out of search results and shared caches and allow only the plugin's own script and stylesheet.
	 *
	 * @return array<string, string> Header values by name.
	 */
	public function headers(): array {
		$script = $this->source( $this->script_url );
		$style  = $this->source( $this->style_url );

		return array(
			'Content-Security-Policy' => "default-src 'none'; script-src {$script}; style-src {$style}; base-uri 'none'; form-action 'none'; frame-ancestors 'none'",
			'X-Frame-Options'         => 'DENY',
			'Referrer-Policy'         => 'no-referrer',
			'Cache-Control'           => 'private, no-cache',
			'X-Robots-Tag'            => 'noindex, nofollow',
		);
	}

	/**
	 * Turns a file address into a source for the content security policy: the address without its query string.
	 *
	 * @param string $url File address.
	 * @return string The source, safe to put in a policy.
	 */
	private function source( string $url ): string {
		return (string) preg_replace( '/[\s;,\']/', '', (string) strtok( $url, '?#' ) );
	}
}
