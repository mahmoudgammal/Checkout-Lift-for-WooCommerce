<?php
/**
 * Phone field settings query.
 *
 * @package SimpleCheckoutFieldsEditor
 */

declare( strict_types=1 );

namespace DesignTich\SimpleCheckoutFieldsEditor\Modules\Phone;

use DesignTich\SimpleCheckoutFieldsEditor\Core\Settings\CheckoutFieldsRepository;

/**
 * Keeps Phone module activation decisions consistent.
 */
final class PhoneFieldSettings {

	/**
	 * Create the phone field settings query.
	 *
	 * @param CheckoutFieldsRepository $checkout_fields Checkout field settings.
	 */
	public function __construct( private CheckoutFieldsRepository $checkout_fields ) {
	}

	/**
	 * Return whether the billing phone field is enabled.
	 */
	public function is_enabled(): bool {
		$checkout_fields = $this->checkout_fields->fields();
		$phone_settings  = $checkout_fields['billing']['billing_phone'] ?? array();

		return ! isset( $phone_settings['enabled'] ) || (bool) $phone_settings['enabled'];
	}
}
