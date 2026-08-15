<?php
/**
 * Classic WooCommerce checkout validation for billing phone.
 *
 * @package SimpleCheckoutFieldsEditor
 */

declare( strict_types=1 );

namespace DesignTich\SimpleCheckoutFieldsEditor\Modules\Phone;

use WP_Error;

/**
 * Applies authoritative phone-format validation to classic checkout requests.
 */
final class ClassicCheckoutValidator {

	/**
	 * Create the classic checkout validation adapter.
	 *
	 * @param InternationalPhoneValidator $phone_validator Phone format rule.
	 * @param PhoneFieldSettings          $field_settings Current phone field settings.
	 */
	public function __construct(
		private InternationalPhoneValidator $phone_validator,
		private PhoneFieldSettings $field_settings
	) {
	}

	/**
	 * Register the classic checkout validation boundary.
	 */
	public function register_hooks(): void {
		// Run before the existing priority-10 hidden/disabled-field error cleanup.
		add_action( 'woocommerce_after_checkout_validation', array( $this, 'validate' ), 9, 2 );
	}

	/**
	 * Reject a non-empty billing phone that is not normalized international format.
	 *
	 * @param array<string, mixed> $posted_fields Processed checkout fields.
	 * @param WP_Error             $validation_errors Checkout validation errors.
	 */
	public function validate( array $posted_fields, WP_Error $validation_errors ): void {
		if ( ! $this->field_settings->is_enabled() ) {
			return;
		}

		$phone_number = $posted_fields['billing_phone'] ?? '';

		if ( '' === $phone_number ) {
			return;
		}

		if ( ! is_string( $phone_number ) || ! $this->phone_validator->is_valid( $phone_number ) ) {
			$validation_errors->add(
				'scfe_invalid_billing_phone',
				__( 'Enter a valid international phone number including the country code.', 'checkout-lift-for-woocommerce' )
			);
		}
	}
}
