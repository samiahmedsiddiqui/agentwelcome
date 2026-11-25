<?php
/**
 * A generated Markdown page and how its HTML was obtained.
 *
 * @package AgentWelcome
 */

declare(strict_types=1);

namespace AgentWelcome\Rendering;

defined( 'ABSPATH' ) || exit;

/** Markdown built for a page, with where its content came from. */
final class GeneratedPage {

	/**
	 * @param string    $markdown Markdown including the front matter block.
	 * @param string    $source   Where the content came from: a RenderResult constant.
	 * @param string    $notice   A sentence for the admin when the rendered page could not be used.
	 * @param bool|null $noindex Whether the page says noindex, or null when the rendered page could not be read.
	 * @param string    $noindex_source Where the noindex was found: a Robots constant, or filter.
	 * @param string    $title    Title used in the Markdown.
	 * @param string    $original Title tag of the rendered page, before the site name was removed.
	 * @param string    $used     Which title was used: a PageTitle constant.
	 */
	public function __construct(
		public string $markdown,
		public string $source,
		public string $notice = '',
		public ?bool $noindex = null,
		public string $noindex_source = '',
		public string $title = '',
		public string $original = '',
		public string $used = PageTitle::POST
	) {}
}
