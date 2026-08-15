<?php
/**
 * Select-option string parser.
 *
 * @package SimpleCheckoutFieldsEditor
 */

declare( strict_types=1 );

namespace DesignTich\SimpleCheckoutFieldsEditor\Modules\Multilingual;

/**
 * Parses the legacy newline option format.
 */
final class OptionsStringParser {

	/**
	 * Parse newline-separated options.
	 *
	 * @param string $option_lines Stored option lines.
	 * @return array<string, string>
	 */
	public function parse( string $option_lines ): array {
		$options = array();
		$lines   = preg_split( '/[\r\n]+/', $option_lines );

		foreach ( false === $lines ? array() : $lines as $line ) {
			$option_line = trim( $line );

			if ( '' === $option_line ) {
				continue;
			}

			$options += $this->parse_line( $option_line );
		}

		return $options;
	}

	/**
	 * Parse one option line.
	 *
	 * @param string $option_line Stored option line.
	 * @return array<string, string>
	 */
	private function parse_line( string $option_line ): array {
		if ( false === strpos( $option_line, '|' ) ) {
			return array( $option_line => $option_line );
		}

		list( $option_value, $option_label ) = explode( '|', $option_line, 2 );

		return array( trim( $option_value ) => trim( $option_label ) );
	}
}
