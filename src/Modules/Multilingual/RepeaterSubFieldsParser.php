<?php
/**
 * Repeater sub-field string parser.
 *
 * @package SimpleCheckoutFieldsEditor
 */

declare( strict_types=1 );

namespace DesignTich\SimpleCheckoutFieldsEditor\Modules\Multilingual;

/**
 * Parses the legacy repeater sub-field text shape.
 */
final class RepeaterSubFieldsParser {

	/**
	 * Parse newline-separated repeater field definitions.
	 *
	 * @param string $sub_field_lines Stored sub-field lines.
	 * @return list<array{key: string, label: string, type: string, placeholder: string}>
	 */
	public function parse( string $sub_field_lines ): array {
		$sub_fields = array();
		$lines      = explode( "\n", str_replace( "\r\n", "\n", $sub_field_lines ) );

		foreach ( $lines as $line ) {
			$sub_field = $this->parse_line( trim( $line ) );

			if ( null !== $sub_field ) {
				$sub_fields[] = $sub_field;
			}
		}

		return $sub_fields;
	}

	/**
	 * Parse one sub-field line.
	 *
	 * @param string $line Stored sub-field line.
	 * @return array{key: string, label: string, type: string, placeholder: string}|null
	 */
	private function parse_line( string $line ): ?array {
		if ( '' === $line ) {
			return null;
		}

		$parts     = explode( '|', $line );
		$field_key = trim( $parts[0] ?? '' );

		if ( '' === $field_key ) {
			return null;
		}

		return array(
			'key'         => $field_key,
			'label'       => trim( $parts[1] ?? '' ),
			'type'        => trim( $parts[2] ?? 'text' ),
			'placeholder' => trim( $parts[5] ?? '' ),
		);
	}
}
