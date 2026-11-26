<?php
/**
 * Works out the title of a page from its rendered HTML.
 *
 * @package AgentWelcome
 */

declare(strict_types=1);

namespace AgentWelcome\Rendering;

defined( 'ABSPATH' ) || exit;

/** Reads the title tag of a page and removes the site name from it. */
final class PageTitle {

	/** The title came from the rendered page. */
	public const PAGE = 'page';
	/** The title is the WordPress post title. */
	public const POST = 'post';

	/** Characters that can join a title and the site name. */
	private const SEPARATORS = '[|\-–—·:»•]';

	/**
	 * Reads the title tag and the site name of the page from its HTML.
	 *
	 * @param string $html Rendered page.
	 * @return array{title: string, site_name: string} Both on one line, with entities decoded.
	 */
	public static function read( string $html ): array {
		$title = 1 === preg_match( '#<title[^>]*>(.*?)</title>#is', $html, $match ) ? self::text( $match[1] ) : '';
		$site  = '';

		if ( 1 === preg_match_all( '#<meta\b[^>]*>#i', $html, $tags ) ) {
			foreach ( $tags[0] as $tag ) {
				if ( 1 === preg_match( '#\bproperty\s*=\s*["\']og:site_name["\']#i', $tag ) && 1 === preg_match( '#\bcontent\s*=\s*(?:"([^"]*)"|\'([^\']*)\')#i', $tag, $content ) ) {
					$site = self::text( $content[1] . ( $content[2] ?? '' ) );
					break;
				}
			}
		}

		return array(
			'title'     => $title,
			'site_name' => $site,
		);
	}

	/**
	 * Chooses the title of a page.
	 *
	 * @param string   $post_title Post title, as plain text.
	 * @param string   $page_title Text of the title tag, or an empty string when the page was not rendered.
	 * @param string[] $names      Site names that may be removed.
	 * @param bool     $strip      Whether to remove the site name.
	 * @param bool     $use_page   Whether to prefer the page title over the post title.
	 * @param bool     $is_home    Whether this is the homepage, which keeps its full title.
	 * @param int      $paged      Number of the paginated view, 1 for the page itself.
	 * @return array{title: string, original: string, used: string} The title, the title tag as found, and PAGE or POST.
	 */
	public static function choose( string $post_title, string $page_title, array $names, bool $strip, bool $use_page, bool $is_home, int $paged = 1 ): array {
		if ( ! $use_page || '' === $page_title ) {
			return array(
				'title'    => $post_title,
				'original' => $page_title,
				'used'     => self::POST,
			);
		}

		return array(
			'title'    => $strip && ! $is_home ? self::strip_site_name( $page_title, $names, $paged ) : $page_title,
			'original' => $page_title,
			'used'     => self::PAGE,
		);
	}

	/**
	 * Removes the site name when a separator and exactly the name stand at one end of the title.
	 *
	 * @param string   $title Title on one line.
	 * @param string[] $names Site names that may be removed.
	 * @param int      $paged Number of the paginated view. Its part at the end of the title, such as "Page 2", is kept.
	 * @return string The title, or the original when nothing would be left.
	 */
	public static function strip_site_name( string $title, array $names, int $paged = 1 ): string {
		// The site name can stand before the page number, as in "Blog - Site - Page 2".
		if ( $paged > 1 && 1 === preg_match( '/\A(.+)(\s+' . self::SEPARATORS . '\s+.*)\z/u', $title, $parts ) && 1 === preg_match( '/(?<!\d)' . $paged . '(?!\d)/', $parts[2] ) ) {
			$head = self::strip_site_name( $parts[1], $names );

			if ( $head !== $parts[1] ) {
				return $head . $parts[2];
			}
		}

		$names = array_filter( array_map( 'trim', $names ), static fn( string $name ): bool => '' !== $name );

		usort(
			$names,
			static fn( string $a, string $b ): int => mb_strlen( $b ) - mb_strlen( $a )
		);

		$title = self::trim_separators( $title );

		foreach ( $names as $name ) {
			$quoted = preg_quote( $name, '/' );

			foreach ( array( '/\s*' . self::SEPARATORS . '\s*' . $quoted . '\z/iu', '/\A' . $quoted . '\s*' . self::SEPARATORS . '\s*/iu' ) as $pattern ) {
				$stripped = trim( (string) preg_replace( $pattern, '', $title, 1, $count ) );

				if ( $count > 0 ) {
					return '' === $stripped ? $title : $stripped;
				}
			}
		}

		return $title;
	}

	/**
	 * Removes a separator left at either end of a title.
	 *
	 * @param string $title Title on one line.
	 * @return string The title, or the original when nothing would be left.
	 */
	private static function trim_separators( string $title ): string {
		$trimmed = trim( (string) preg_replace( '/\A(?:\s|' . self::SEPARATORS . ')+|(?:\s|' . self::SEPARATORS . ')+\z/u', '', $title ) );

		return '' === $trimmed ? $title : $trimmed;
	}

	/**
	 * Plain text of a piece of HTML, on one line.
	 *
	 * @param string $value HTML text.
	 * @return string The text.
	 */
	private static function text( string $value ): string {
		return trim( (string) preg_replace( '/\s+/u', ' ', html_entity_decode( $value, ENT_QUOTES | ENT_HTML5, 'UTF-8' ) ) );
	}
}
