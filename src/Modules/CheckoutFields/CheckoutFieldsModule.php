<?php
/**
 * Base classic checkout fields module.
 *
 * @package SimpleCheckoutFieldsEditor
 */

declare( strict_types=1 );

namespace DesignTich\SimpleCheckoutFieldsEditor\Modules\CheckoutFields;

use DesignTich\SimpleCheckoutFieldsEditor\Core\Contracts\Module;

/**
 * Registers the classic checkout field adapter.
 */
final class CheckoutFieldsModule implements Module {

	/**
	 * Create the checkout fields module.
	 *
	 * @param ClassicCheckoutAdapter $classic_checkout Classic checkout hooks.
	 * @param BlocksLocaleAdapter    $block_checkout   Block checkout hooks.
	 * @param BlocksCustomFields     $block_fields     Block checkout custom fields.
	 * @param CheckoutFieldsAssets   $assets           Checkout field assets.
	 */
	public function __construct(
		private ClassicCheckoutAdapter $classic_checkout,
		private BlocksLocaleAdapter $block_checkout,
		private BlocksCustomFields $block_fields,
		private CheckoutFieldsAssets $assets
	) {
	}

	/**
	 * {@inheritDoc}
	 */
	public function id(): string {
		return 'checkout-fields';
	}

	/**
	 * {@inheritDoc}
	 */
	public function dependencies(): array {
		return array();
	}

	/**
	 * {@inheritDoc}
	 */
	public function boot(): void {
		// Each adapter hooks only the checkout type it supports.
		$this->classic_checkout->register_hooks();
		$this->block_checkout->register_hooks();
		$this->assets->register_hooks();

		// Additional fields must register here to enter the Store API schema.
		add_action( 'woocommerce_init', array( $this->block_fields, 'register' ) );
	}
}
