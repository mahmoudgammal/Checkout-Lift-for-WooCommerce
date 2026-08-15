<?php
/**
 * Shared classic checkout event bridge asset.
 *
 * @package SimpleCheckoutFieldsEditor
 */

declare( strict_types=1 );

namespace DesignTich\SimpleCheckoutFieldsEditor\Core\Assets;

/**
 * Registers the single script permitted to depend on jQuery.
 *
 * WooCommerce publishes classic checkout lifecycle events through jQuery, so
 * one bridge republishes them as DOM events and every feature module consumes
 * the bridge instead of jQuery.
 */
final class CheckoutBridgeAssets {

	/**
	 * Script handle other modules declare as a dependency.
	 */
	public const HANDLE = 'scfe-wc-bridge';

	/**
	 * Create the bridge asset registrar.
	 *
	 * @param string $plugin_url Plugin URL.
	 * @param string $version Asset version.
	 */
	public function __construct(
		private string $plugin_url,
		private string $version
	) {
	}

	/**
	 * Register the bridge without enqueueing it.
	 *
	 * Modules that list the handle as a dependency cause WordPress to enqueue
	 * it, so pages without a migrated module never load it.
	 */
	public function register(): void {
		wp_register_script(
			self::HANDLE,
			$this->plugin_url . 'assets/js/core/wc-bridge.js',
			array( 'jquery' ),
			$this->version,
			true
		);
	}
}
