<?php
/**
 * Shared asset registration module.
 *
 * @package SimpleCheckoutFieldsEditor
 */

declare( strict_types=1 );

namespace DesignTich\SimpleCheckoutFieldsEditor\Core\Assets;

use DesignTich\SimpleCheckoutFieldsEditor\Core\Contracts\Module;

/**
 * Registers assets shared by more than one feature module.
 */
final class CoreAssetsModule implements Module {

	/**
	 * Create the shared asset module.
	 *
	 * @param CheckoutBridgeAssets $bridge Classic checkout event bridge.
	 */
	public function __construct( private CheckoutBridgeAssets $bridge ) {
	}

	/**
	 * {@inheritDoc}
	 */
	public function id(): string {
		return 'core-assets';
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
		// Priority 1 keeps registration ahead of module enqueue callbacks.
		add_action( 'wp_enqueue_scripts', array( $this->bridge, 'register' ), 1 );
	}
}
