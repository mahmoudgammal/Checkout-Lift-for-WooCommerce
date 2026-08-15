<?php
/**
 * Checkout fields administration module.
 *
 * @package SimpleCheckoutFieldsEditor
 */

declare( strict_types=1 );

namespace DesignTich\SimpleCheckoutFieldsEditor\Modules\CheckoutFieldsAdmin;

use DesignTich\SimpleCheckoutFieldsEditor\Core\Contracts\Module;

/**
 * Registers completed checkout-fields admin capabilities.
 */
final class CheckoutFieldsAdminModule implements Module {

	/**
	 * Create the admin module.
	 *
	 * @param SaveSettingsAction $save_settings Save-settings endpoint.
	 */
	public function __construct( private SaveSettingsAction $save_settings ) {
	}

	/**
	 * Return the module identifier.
	 */
	public function id(): string {
		return 'checkout-fields-admin';
	}

	/**
	 * Return module dependencies.
	 *
	 * @return list<string>
	 */
	public function dependencies(): array {
		return array();
	}

	/**
	 * Register completed admin capabilities.
	 */
	public function boot(): void {
		$this->save_settings->register();
	}
}
