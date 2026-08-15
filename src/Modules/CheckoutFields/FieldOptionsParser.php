<?php
/**
 * Parse the existing custom-field option format.
 *
 * @package SimpleCheckoutFieldsEditor
 */

declare( strict_types=1 );

namespace DesignTich\SimpleCheckoutFieldsEditor\Modules\CheckoutFields;

/**
 * Converts newline-delimited value:label settings into WooCommerce options.
 */
final class FieldOptionsParser {

	/**
	 * Parse option text without changing its historical colon behavior.
	 *
	 * @param mixed $stored_options Stored custom-field options.
	 * @return array<string, string>
	 */
	public function parse( $stored_options ): array {
		if ( ! is_string( $stored_options ) || '' === $stored_options ) {
			return array();
		}

		$parsed_options = array();

		foreach ( explode( "\n", str_replace( "\r\n", "\n", $stored_options ) ) as $option_line ) {
			$parsed_line = $this->parsed_line( trim( $option_line ) );

			if ( null !== $parsed_line ) {
				$parsed_options[ $parsed_line['key'] ] = $parsed_line['label'];
			}
		}

		return $parsed_options;
	}

	/**
	 * Parse one non-empty historical option line.
	 *
	 * @param string $option_line Current line.
	 * @return array{key: string, label: string}|null
	 */
	private function parsed_line( string $option_line ): ?array {
		if ( '' === $option_line ) {
			return null;
		}

		$parts      = explode( ':', $option_line );
		$option_key = trim( $parts[0] );

		return array(
			'key'   => $option_key,
			'label' => isset( $parts[1] ) ? trim( $parts[1] ) : $option_key,
		);
	}
}
