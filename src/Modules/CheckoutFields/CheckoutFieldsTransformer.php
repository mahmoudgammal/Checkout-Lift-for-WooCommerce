<?php
/**
 * Transform classic WooCommerce checkout fields.
 *
 * @package SimpleCheckoutFieldsEditor
 */

declare( strict_types=1 );

namespace DesignTich\SimpleCheckoutFieldsEditor\Modules\CheckoutFields;

/**
 * Reorders and decorates configured checkout sections.
 */
final class CheckoutFieldsTransformer {

	/**
	 * Create the checkout-fields transformer.
	 *
	 * @param CustomFieldBuilder      $custom_fields Custom field builder.
	 * @param FieldDecorator          $field_decorator Common field decorator.
	 * @param ShippingAddressSettings $shipping_settings Shipping option.
	 */
	public function __construct(
		private CustomFieldBuilder $custom_fields,
		private FieldDecorator $field_decorator,
		private ShippingAddressSettings $shipping_settings
	) {
	}

	/**
	 * Apply all stored sections.
	 *
	 * @param array<string, array<string, array<string, mixed>>> $checkout_fields Current checkout fields.
	 * @param array<string, array<string, array<string, mixed>>> $stored_fields Stored settings.
	 * @return array<string, array<string, array<string, mixed>>>
	 */
	public function transform( array $checkout_fields, array $stored_fields ): array {
		if ( $this->shipping_settings->is_disabled() ) {
			unset( $checkout_fields['shipping'] );
		}

		foreach ( $stored_fields as $section_key => $section_settings ) {
			if ( $this->skip_section( $section_key ) ) {
				continue;
			}

			$current_section                 = $checkout_fields[ $section_key ] ?? array();
			$checkout_fields[ $section_key ] = $this->transform_section( $current_section, $section_settings );
		}

		return $checkout_fields;
	}

	/**
	 * Apply the configured shipping-address decision.
	 *
	 * @param bool $needs_shipping_address Current WooCommerce decision.
	 */
	public function shipping_address_needed( bool $needs_shipping_address ): bool {
		return $this->shipping_settings->is_disabled() ? false : $needs_shipping_address;
	}

	/**
	 * Apply one configured section and preserve unconfigured WooCommerce fields.
	 *
	 * @param array<string, array<string, mixed>> $current_fields Current section fields.
	 * @param array<string, array<string, mixed>> $stored_fields Stored section settings.
	 * @return array<string, array<string, mixed>>
	 */
	private function transform_section( array $current_fields, array $stored_fields ): array {
		$transformed_fields = array();
		$priority           = 0;

		foreach ( $stored_fields as $field_key => $settings ) {
			$priority += 10;
			$field     = $this->configured_field( $field_key, $settings, $current_fields, $priority );
			unset( $current_fields[ $field_key ] );

			if ( null !== $field ) {
				$transformed_fields[ $field_key ] = $field;
			}
		}

		return $this->append_remaining_fields( $transformed_fields, $current_fields, $priority );
	}

	/**
	 * Build or retrieve one enabled configured field.
	 *
	 * @param string                              $field_key Field identifier.
	 * @param array<string, mixed>                $settings Stored settings.
	 * @param array<string, array<string, mixed>> $current_fields Current fields.
	 * @param int                                 $priority Calculated priority.
	 * @return array<string, mixed>|null
	 */
	private function configured_field(
		string $field_key,
		array $settings,
		array $current_fields,
		int $priority
	): ?array {
		if ( isset( $settings['enabled'] ) && ! $settings['enabled'] ) {
			return null;
		}

		$field = ! empty( $settings['custom'] )
			? $this->custom_fields->build( $field_key, $settings )
			: ( $current_fields[ $field_key ] ?? null );

		return is_array( $field ) ? $this->field_decorator->decorate( $field, $settings, $priority ) : null;
	}

	/**
	 * Append fields unknown to the saved settings at the bottom.
	 *
	 * @param array<string, array<string, mixed>> $transformed_fields Configured fields.
	 * @param array<string, array<string, mixed>> $remaining_fields Unconfigured fields.
	 * @param int                                 $priority Last configured priority.
	 * @return array<string, array<string, mixed>>
	 */
	private function append_remaining_fields(
		array $transformed_fields,
		array $remaining_fields,
		int $priority
	): array {
		foreach ( $remaining_fields as $field_key => $field ) {
			$priority                        += 10;
			$field['priority']                = $priority;
			$transformed_fields[ $field_key ] = $this->with_form_row_class( $field );
		}

		return $transformed_fields;
	}

	/**
	 * Preserve the historical form-row class addition.
	 *
	 * @param array<string, mixed> $field Remaining WooCommerce field.
	 * @return array<string, mixed>
	 */
	private function with_form_row_class( array $field ): array {
		if ( isset( $field['class'] ) && is_array( $field['class'] ) && ! in_array( 'form-row', $field['class'], true ) ) {
			$field['class'][] = 'form-row';
		}

		return $field;
	}

	/**
	 * Check whether the disabled shipping section should be skipped.
	 *
	 * @param string $section_key Checkout section key.
	 */
	private function skip_section( string $section_key ): bool {
		return 'shipping' === $section_key && $this->shipping_settings->is_disabled();
	}
}
