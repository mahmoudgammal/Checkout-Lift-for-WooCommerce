<?php
/**
 * Pro field string registration.
 *
 * @package SimpleCheckoutFieldsEditor
 */

declare( strict_types=1 );

namespace DesignTich\SimpleCheckoutFieldsEditor\Modules\Multilingual;

/**
 * Registers strings owned by advanced field types.
 */
final class ProFieldStringRegistry {

	/**
	 * Create the registry.
	 *
	 * @param CheckoutFieldStringRegistry $checkout_strings Checkout-field string registry.
	 * @param RepeaterSubFieldsParser     $repeaters Repeater sub-field parser.
	 */
	public function __construct(
		private CheckoutFieldStringRegistry $checkout_strings,
		private RepeaterSubFieldsParser $repeaters
	) {
	}

	/**
	 * Register pro strings through the public multilingual hook.
	 *
	 * @param mixed                $multilingual Legacy-compatible registrar.
	 * @param array<string, mixed> $settings Checkout field settings.
	 */
	public function register( $multilingual, array $settings ): void {
		foreach ( $settings as $fields ) {
			$this->register_fields( is_array( $fields ) ? $fields : array() );
		}
	}

	/**
	 * Register one section.
	 *
	 * @param array<string, mixed> $fields Field settings.
	 */
	private function register_fields( array $fields ): void {
		foreach ( $fields as $field_key => $field_data ) {
			if ( is_array( $field_data ) ) {
				$this->register_field( (string) $field_key, $field_data );
			}
		}
	}

	/**
	 * Register pro strings for one field.
	 *
	 * @param string               $field_key Field key.
	 * @param array<string, mixed> $field_data Field settings.
	 */
	private function register_field( string $field_key, array $field_data ): void {
		$type = (string) ( $field_data['type'] ?? '' );

		if ( 'repeater' === $type ) {
			$this->register_repeater( $field_key, $field_data );
		}

		if ( 'fee' === $type ) {
			$this->checkout_strings->register_scalar( $field_key . '_fee_description', $field_data['fee_description'] ?? null );
			$this->checkout_strings->register_scalar( $field_key . '_fee_name', $field_data['fee_name'] ?? null );
		}

		if ( in_array( $type, array( 'date', 'time', 'datetime' ), true ) ) {
			$this->checkout_strings->register_scalar( $field_key . '_datetime_format', $field_data['datetime_format_display'] ?? null );
		}
	}

	/**
	 * Register repeater strings.
	 *
	 * @param string               $field_key Field key.
	 * @param array<string, mixed> $field_data Field settings.
	 */
	private function register_repeater( string $field_key, array $field_data ): void {
		$this->checkout_strings->register_scalar( $field_key . '_repeater_button', $field_data['repeater_button'] ?? null );

		$sub_fields = $this->sub_fields( $field_data['repeater_fields'] ?? null );

		foreach ( $sub_fields as $index => $sub_field ) {
			$this->checkout_strings->register_scalar( $field_key . '_subfield_' . $index . '_label', $sub_field['label'] );
			$this->checkout_strings->register_scalar( $field_key . '_subfield_' . $index . '_placeholder', $sub_field['placeholder'] );
		}
	}

	/**
	 * Normalize repeater sub-field data.
	 *
	 * @param mixed $sub_fields Stored sub-field shape.
	 * @return list<array{key: string, label: string, type: string, placeholder: string}>
	 */
	private function sub_fields( $sub_fields ): array {
		if ( is_string( $sub_fields ) ) {
			return $this->repeaters->parse( $sub_fields );
		}

		if ( ! is_array( $sub_fields ) ) {
			return array();
		}

		return array_values( array_filter( array_map( array( $this, 'normalized_sub_field' ), $sub_fields ) ) );
	}

	/**
	 * Normalize one stored repeater sub-field.
	 *
	 * @param mixed $sub_field Stored sub-field.
	 * @return array{key: string, label: string, type: string, placeholder: string}|null
	 */
	private function normalized_sub_field( $sub_field ): ?array {
		if ( ! is_array( $sub_field ) || empty( $sub_field['key'] ) ) {
			return null;
		}

		return array(
			'key'         => (string) $sub_field['key'],
			'label'       => (string) ( $sub_field['label'] ?? '' ),
			'type'        => (string) ( $sub_field['type'] ?? 'text' ),
			'placeholder' => (string) ( $sub_field['placeholder'] ?? '' ),
		);
	}
}
