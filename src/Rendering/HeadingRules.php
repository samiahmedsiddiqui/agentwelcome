<?php
/**
 * Reads the list of elements that are written as headings.
 *
 * @package AgentWelcome
 */

declare(strict_types=1);

namespace AgentWelcome\Rendering;

defined( 'ABSPATH' ) || exit;

/** Turns lines such as ".card-title => h3" into selectors with a heading level. */
final class HeadingRules {

	/**
	 * @param Selector $selector Checks the selectors.
	 */
	public function __construct( private Selector $selector ) {}

	/**
	 * Splits text with one rule per line into usable and unusable rules.
	 *
	 * @param string $text Lines of selector and level, such as ".card-title => h3".
	 * @return array{valid: array<string, int>, invalid: string[]} The usable rules by selector, and the lines that cannot be used.
	 */
	public function parse( string $text ): array {
		$result = array(
			'valid'   => array(),
			'invalid' => array(),
		);

		foreach ( (array) preg_split( '/[\r\n]+/', $text ) as $line ) {
			$line = trim( (string) $line );

			if ( '' === $line ) {
				continue;
			}

			if ( 1 !== preg_match( '/^(.*\S)\s*=>\s*h([1-6])$/i', $line, $match ) ) {
				$result['invalid'][] = $line;
				continue;
			}

			$parts = $this->selector->parse_list( $match[1] );

			foreach ( $parts['valid'] as $selector ) {
				$result['valid'][ $selector ] = (int) $match[2];
			}

			foreach ( $parts['invalid'] as $selector ) {
				$result['invalid'][] = $selector;
			}
		}

		return $result;
	}

	/**
	 * Writes rules back as lines of text.
	 *
	 * @param array<string, int> $rules Levels by selector.
	 * @return string One rule per line.
	 */
	public function to_text( array $rules ): string {
		$lines = array();

		foreach ( $rules as $selector => $level ) {
			$lines[] = $selector . ' => h' . $level;
		}

		return implode( "\n", $lines );
	}
}
