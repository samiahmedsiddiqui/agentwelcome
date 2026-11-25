<?php
/**
 * Reads the meta description from the HTML of a page.
 *
 * @package AgentWelcome
 */

declare(strict_types=1);

namespace AgentWelcome\Rendering;

defined( 'ABSPATH' ) || exit;

/** Finds the content of the description meta tag in the head of a page. */
final class MetaDescription {

	/**
	 * Gets the meta description from the head of a page.
	 *
	 * @param string $html HTML of the page.
	 * @return string The text with entities decoded, or an empty string when the head has none.
	 */
	public static function find( string $html ): string {
		if ( 1 !== preg_match( '/<head\b[^>]*>(.*?)(?:<\/head\s*>|<body\b|$)/is', $html, $head ) ) {
			return '';
		}

		$head = (string) preg_replace( '/<!--.*?-->|<(script|style|noscript|template)\b.*?<\/\1\s*>/is', '', $head[1] );

		if ( false === preg_match_all( '/<meta\b((?:"[^"]*"|\'[^\']*\'|[^>"\'])*)>/i', $head, $tags ) ) {
			return '';
		}

		foreach ( $tags[1] as $tag ) {
			$attributes = self::attributes( $tag );

			if ( 'description' !== strtolower( trim( $attributes['name'] ?? '' ) ) ) {
				continue;
			}

			$text = (string) preg_replace( '/\s+/u', ' ', html_entity_decode( $attributes['content'] ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8' ) );
			$text = trim( $text );

			if ( '' !== $text ) {
				return $text;
			}
		}

		return '';
	}

	/**
	 * Reads the attributes of a tag.
	 *
	 * @param string $tag The text between the tag name and the closing bracket.
	 * @return array<string, string> Attribute values by lowercase name. The first one wins when a name repeats.
	 */
	private static function attributes( string $tag ): array {
		$found = array();

		preg_match_all( '/([^\s"\'<>\/=]+)(?:\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|([^\s"\'<>]+)))?/', $tag, $matches, PREG_SET_ORDER );

		foreach ( $matches as $match ) {
			$name = strtolower( $match[1] );

			if ( ! isset( $found[ $name ] ) ) {
				$found[ $name ] = $match[4] ?? ( '' !== ( $match[3] ?? '' ) ? $match[3] : ( $match[2] ?? '' ) );
			}
		}

		return $found;
	}
}
