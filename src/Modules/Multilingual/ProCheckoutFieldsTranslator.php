<?php
/**
 * Pro checkout field translator.
 *
 * @package SimpleCheckoutFieldsEditor
 */

declare( strict_types=1 );

namespace DesignTich\SimpleCheckoutFieldsEditor\Modules\Multilingual;

/**
 * Applies advanced-field translations to checkout fields.
 */
final class ProCheckoutFieldsTranslator {

	/**
	 * Create the translator.
	 *
	 * @param MultilingualProviders     $providers Provider detector.
	 * @param TranslatableStringService $strings Translation string service.
	 */
	public function __construct(
		private MultilingualProviders $providers,
		private TranslatableStringService $strings
	) {
	}

	/**
	 * Translate pro field strings.
	 *
	 * @param array<string, mixed> $fields Checkout fields.
	 * @return array<string, mixed>
	 */
	public function translate( array $fields ): array {
		if ( ! $this->providers->active() ) {
			return $fields;
		}

		foreach ( array( 'billing', 'shipping', 'order' ) as $section ) {
			if ( isset( $fields[ $section ] ) ) {
				$fields[ $section ] = $this->translated_section( $fields[ $section ] );
			}
		}

		return $fields;
	}

	/**
	 * Translate one section.
	 *
	 * @param mixed $section_fields Checkout section fields.
	 * @return mixed
	 */
	private function translated_section( $section_fields ) {
		if ( ! is_array( $section_fields ) ) {
			return $section_fields;
		}

		foreach ( $section_fields as $field_key => $field ) {
			$section_fields[ $field_key ] = $this->translated_field( (string) $field_key, $field );
		}

		return $section_fields;
	}

	/**
	 * Translate one field.
	 *
	 * @param string $field_key Checkout field key.
	 * @param mixed  $field Checkout field.
	 * @return mixed
	 */
	private function translated_field( string $field_key, $field ) {
		if ( ! is_array( $field ) || 'fee' !== ( $field['type'] ?? '' ) ) {
			return $field;
		}

		foreach ( array( 'fee_description', 'fee_name' ) as $key ) {
			if ( isset( $field[ $key ] ) && is_string( $field[ $key ] ) ) {
				$field[ $key ] = $this->strings->translate( $field_key . '_' . $key, $field[ $key ] );
			}
		}

		return $field;
	}
}
