<?php
/**
 * Compiles a small CSS selector subset to XPath.
 *
 * Supports tag, #id, .class, [attr], [attr=v], [attr^=v], [attr$=v], [attr*=v], [attr~=v], [attr|=v],
 * compound selectors, descendant, child (>), next sibling (+) and later sibling (~)
 * combinators, and comma lists.
 *
 * @package AgentWelcome
 */

declare(strict_types=1);

namespace AgentWelcome\Rendering;

defined( 'ABSPATH' ) || exit;

/** Turns simple CSS selectors into XPath queries. Supported: tags, #id, .class, attribute tests, descendants, children and siblings. */
final class Selector {

	/**
	 * Converts a list of selectors.
	 *
	 * @param string $selector Selectors separated by commas.
	 * @return string[] One XPath per valid selector, in order.
	 */
	public function to_xpaths( string $selector ): array {
		$xpaths = array();

		foreach ( $this->split( $selector ) as $part ) {
			$xpath = $this->compile( $part );

			if ( null !== $xpath ) {
				$xpaths[] = $xpath;
			}
		}

		return $xpaths;
	}

	/**
	 * Splits a list of selectors at commas that are not inside brackets.
	 *
	 * @param string $selector Selectors separated by commas.
	 * @return string[] The selectors, trimmed.
	 */
	public function split( string $selector ): array {
		$parts = preg_split( '/,(?![^\[]*\])/', $selector );

		return array_values(
			array_filter(
				array_map( 'trim', false === $parts ? array() : $parts ),
				static function ( string $part ): bool {
					return '' !== $part;
				}
			)
		);
	}

	/**
	 * Splits text with one or more selectors per line into supported and unsupported ones.
	 *
	 * @param string $text Lines of selectors.
	 * @return array{valid: string[], invalid: string[]} The selectors that can be used, and the ones that cannot.
	 */
	public function parse_list( string $text ): array {
		$result = array(
			'valid'   => array(),
			'invalid' => array(),
		);

		foreach ( (array) preg_split( '/[\r\n]+/', $text ) as $line ) {
			foreach ( $this->split( (string) $line ) as $part ) {
				$result[ array() === $this->to_xpaths( $part ) ? 'invalid' : 'valid' ][] = $part;
			}
		}

		return $result;
	}

	/**
	 * Converts one selector.
	 *
	 * @param string $selector A selector such as "main > .content".
	 * @return string|null The XPath, or null when the selector is not supported.
	 */
	private function compile( string $selector ): ?string {
		if ( '' === $selector ) {
			return null;
		}

		$pattern = '/\G\s*([>+~])?\s*((?:[a-zA-Z*][\w-]*)?(?:#[\w-]+|\.[\w-]+|\[[^\]]*\])*)/';
		$offset  = 0;
		$xpath   = '';
		$length  = strlen( $selector );

		while ( $offset < $length ) {
			if ( ! preg_match( $pattern, $selector, $match, 0, $offset ) || '' === $match[2] ) {
				return null;
			}

			$step       = $this->compound( $match[2] );
			$combinator = '' === $xpath ? '' : $match[1];

			if ( null === $step || ( '' === $xpath && in_array( $match[1], array( '+', '~' ), true ) ) ) {
				return null;
			}

			$xpath .= $this->join( $combinator, $step[0], $step[1] );

			$offset += strlen( $match[0] );

			while ( $offset < $length && ctype_space( $selector[ $offset ] ) ) {
				++$offset;
			}
		}

		return $xpath;
	}

	/**
	 * Converts one part of a selector, such as "div.card[data-x]".
	 *
	 * @param string $compound A tag with optional #id, .class and attribute tests.
	 * @return array{string, string}|null The tag and the XPath tests, or null when it is not supported.
	 */
	private function compound( string $compound ): ?array {
		if ( ! preg_match( '/^([a-zA-Z*][\w-]*)?(.*)$/', $compound, $match ) ) {
			return null;
		}

		$tag        = '' === $match[1] ? '*' : strtolower( $match[1] );
		$rest       = $match[2];
		$predicates = '';
		$attribute  = '/\G(?:#([\w-]+)|\.([\w-]+)|\[([\w:-]+)(?:([~^$*|]?=)\s*(?:"([^"]*)"|\'([^\']*)\'|([^\]]*)))?\])/';
		$offset     = 0;
		$length     = strlen( $rest );

		while ( $offset < $length ) {
			if ( ! preg_match( $attribute, $rest, $part, PREG_UNMATCHED_AS_NULL, $offset ) ) {
				return null;
			}

			if ( null !== $part[1] ) {
				$predicates .= '[@id=' . $this->literal( $part[1] ) . ']';
			} elseif ( null !== $part[2] ) {
				$predicates .= "[contains(concat(' ', normalize-space(@class), ' '), " . $this->literal( ' ' . $part[2] . ' ' ) . ')]';
			} else {
				$value       = $part[5] ?? $part[6] ?? $part[7] ?? '';
				$predicates .= $this->attribute_predicate( $part[3], $part[4] ?? '', trim( $value ) );
			}

			$offset += strlen( $part[0] );
		}

		return array( $tag, $predicates );
	}

	/**
	 * Adds a step to the XPath, according to the combinator in front of it.
	 *
	 * @param string $combinator Empty for a descendant, or one of >, + and ~.
	 * @param string $tag        Tag name, or * for any.
	 * @param string $tests      XPath tests for the step, in square brackets.
	 * @return string The XPath for the step.
	 */
	private function join( string $combinator, string $tag, string $tests ): string {
		switch ( $combinator ) {
			case '>':
				return '/' . $tag . $tests;
			case '+':
				return '/following-sibling::*[1][self::' . $tag . ']' . $tests;
			case '~':
				return '/following-sibling::' . $tag . $tests;
			default:
				return '//' . $tag . $tests;
		}
	}

	/**
	 * Builds the XPath test for an attribute.
	 *
	 * @param string $name     Attribute name.
	 * @param string $operator One of =, ^=, $=, *=, ~=, |= or an empty string for "has the attribute".
	 * @param string $value    Value to compare with.
	 * @return string The test, in square brackets.
	 */
	private function attribute_predicate( string $name, string $operator, string $value ): string {
		$attribute = '@' . $name;
		$quoted    = $this->literal( $value );

		switch ( $operator ) {
			case '=':
				return "[{$attribute}={$quoted}]";
			case '^=':
				return "[starts-with({$attribute}, {$quoted})]";
			case '$=':
				return "[substring({$attribute}, string-length({$attribute}) - string-length({$quoted}) + 1) = {$quoted}]";
			case '*=':
				return "[contains({$attribute}, {$quoted})]";
			case '~=':
				return "[contains(concat(' ', normalize-space({$attribute}), ' '), " . $this->literal( ' ' . $value . ' ' ) . ')]';
			case '|=':
				return "[{$attribute}={$quoted} or starts-with({$attribute}, " . $this->literal( $value . '-' ) . ')]';
			default:
				return "[{$attribute}]";
		}
	}

	/**
	 * Quotes a value as an XPath string.
	 *
	 * @param string $value Text.
	 * @return string The quoted text.
	 */
	private function literal( string $value ): string {
		if ( false === strpos( $value, "'" ) ) {
			return "'" . $value . "'";
		}

		if ( false === strpos( $value, '"' ) ) {
			return '"' . $value . '"';
		}

		$pieces = array_map(
			static function ( string $piece ): string {
				return "'" . $piece . "'";
			},
			explode( "'", $value )
		);

		return 'concat(' . implode( ', "\'", ', $pieces ) . ')';
	}
}
