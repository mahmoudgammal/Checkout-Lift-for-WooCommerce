<?php
/**
 * Field dependency frontend assets.
 *
 * @package SimpleCheckoutFieldsEditor
 */

declare( strict_types=1 );

namespace DesignTich\SimpleCheckoutFieldsEditor\Modules\FieldConditions;

use DesignTich\SimpleCheckoutFieldsEditor\Core\Assets\CheckoutBridgeAssets;

/**
 * Loads browser dependency UX assets when dependencies are configured.
 */
final class FieldDependencyAssets {

	/**
	 * Create the asset loader.
	 *
	 * @param BrowserDependencyConfigurations $configurations Browser dependency configurations.
	 * @param string                          $plugin_url Plugin URL.
	 * @param string                          $version Asset version.
	 */
	public function __construct(
		private BrowserDependencyConfigurations $configurations,
		private string $plugin_url,
		private string $version
	) {
	}

	/**
	 * Enqueue checkout-only dependency assets.
	 */
	public function enqueue(): void {
		$configurations = $this->configurations->configurations();

		if ( ! is_checkout() || array() === $configurations ) {
			return;
		}

		wp_enqueue_script( 'scfe-field-dependencies', $this->plugin_url . 'assets/js/field-dependencies.js', array( CheckoutBridgeAssets::HANDLE ), $this->version, true );
		wp_enqueue_style( 'scfe-field-dependencies', $this->plugin_url . 'assets/css/field-dependencies.css', array(), $this->version );
		wp_localize_script(
			'scfe-field-dependencies',
			'scfe_dependencies',
			array(
				'fields' => $configurations,
				'i18n'   => array(
					'loading' => __( 'Loading...', 'checkout-lift-for-woocommerce' ),
				),
			)
		);
	}
}
