<?php
/**
 * Shipping-address option access.
 *
 * @package SimpleCheckoutFieldsEditor
 */

declare( strict_types=1 );

namespace DesignTich\SimpleCheckoutFieldsEditor\Modules\CheckoutFields;

/**
 * Reads the existing shipping-address option once per request.
 */
final class ShippingAddressSettings {

	/**
	 * Request-cached option decision.
	 *
	 * @var bool|null
	 */
	private ?bool $disabled = null;

	/**
	 * Return whether the shipping-address section is disabled.
	 */
	public function is_disabled(): bool {
		if ( null === $this->disabled ) {
			$this->disabled = (bool) get_option( 'scfe_disable_shipping', false );
		}

		return $this->disabled;
	}
}
