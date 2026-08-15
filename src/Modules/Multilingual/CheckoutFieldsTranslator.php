<?php
/**
 * Checkout field translator.
 *
 * @package SimpleCheckoutFieldsEditor
 */

declare( strict_types=1 );

namespace DesignTich\SimpleCheckoutFieldsEditor\Modules\Multilingual;

/**
 * Applies registered translations to WooCommerce checkout fields.
 */
final class CheckoutFieldsTranslator {

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
	 * Translate checkout fields.
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
	 * Translate one field section.
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
	 * Translate one checkout field.
	 *
	 * @param string $field_key Checkout field key.
	 * @param mixed  $field Checkout field.
	 * @return mixed
	 */
	private function translated_field( string $field_key, $field ) {
		if ( ! is_array( $field ) ) {
			return $field;
		}

		foreach ( array( 'label', 'placeholder', 'default', 'description' ) as $key ) {
			$field = $this->translate_scalar_field_value( $field_key, $field, $key );
		}

		$field['options'] = $this->translated_options( $field_key, $field['options'] ?? null );

		return $field;
	}

	/**
	 * Translate one scalar field setting.
	 *
	 * @param string               $field_key Checkout field key.
	 * @param array<string, mixed> $field Checkout field.
	 * @param string               $key Field setting key.
	 * @return array<string, mixed>
	 */
	private function translate_scalar_field_value( string $field_key, array $field, string $key ): array {
		if ( isset( $field[ $key ] ) && is_string( $field[ $key ] ) && '' !== $field[ $key ] ) {
			$field[ $key ] = $this->strings->translate( $field_key . '_' . $key, $field[ $key ] );
		}

		return $field;
	}

	/**
	 * Translate field options.
	 *
	 * @param string $field_key Checkout field key.
	 * @param mixed  $options Checkout options.
	 * @return mixed
	 */
	private function translated_options( string $field_key, $options ) {
		if ( ! is_array( $options ) ) {
			return $options;
		}

		foreach ( $options as $option_value => $option_label ) {
			if ( is_string( $option_label ) ) {
				$name                     = $field_key . '_option_' . sanitize_key( (string) $option_value );
				$options[ $option_value ] = $this->strings->translate( $name, $option_label );
			}
		}

		return $options;
	}
}
