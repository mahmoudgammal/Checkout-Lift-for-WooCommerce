<?php
/**
 * Submitted custom order-field values.
 *
 * @package SimpleCheckoutFieldsEditor
 */

declare( strict_types=1 );

namespace DesignTich\SimpleCheckoutFieldsEditor\Modules\CustomOrderFields;

/**
 * Maps accepted checkout data to the existing order-meta contract.
 */
final class SubmittedFieldValues {

	/**
	 * Create the submitted-value mapper.
	 *
	 * @param FieldValueSanitizer $sanitizer Field value sanitizer.
	 */
	public function __construct( private FieldValueSanitizer $sanitizer ) {
	}

	/**
	 * Return sanitized meta values for visible base custom fields.
	 *
	 * @param array<string, array{label: string, type: string}> $definitions Base field definitions.
	 * @param array<string, mixed>                              $checkout_data Processed WooCommerce data.
	 * @param array<mixed>                                      $hidden_fields Server-hidden field keys.
	 * @return array<string, string>
	 */
	public function collect( array $definitions, array $checkout_data, array $hidden_fields ): array {
		$meta_values = array();
		$hidden      = $this->hidden_lookup( $hidden_fields );

		foreach ( array_keys( $definitions ) as $field_key ) {
			$value = $this->accepted_value( $field_key, $checkout_data, $hidden );

			if ( null !== $value ) {
				$meta_values[ '_' . $field_key ] = $value;
			}
		}

		return $meta_values;
	}

	/**
	 * Return an accepted submitted value.
	 *
	 * @param string               $field_key Field identifier.
	 * @param array<string, mixed> $checkout_data Processed WooCommerce data.
	 * @param array<string, true>  $hidden Hidden-field lookup.
	 */
	private function accepted_value( string $field_key, array $checkout_data, array $hidden ): ?string {
		if ( isset( $hidden[ $field_key ] ) || ! array_key_exists( $field_key, $checkout_data ) ) {
			return null;
		}

		return $this->sanitizer->sanitize( $checkout_data[ $field_key ] );
	}

	/**
	 * Normalize an extension-facing hidden-field list.
	 *
	 * @param array<mixed> $hidden_fields Filtered hidden fields.
	 * @return array<string, true>
	 */
	private function hidden_lookup( array $hidden_fields ): array {
		$hidden = array();

		foreach ( $hidden_fields as $field_key ) {
			if ( is_string( $field_key ) ) {
				$hidden[ $field_key ] = true;
			}
		}

		return $hidden;
	}
}
