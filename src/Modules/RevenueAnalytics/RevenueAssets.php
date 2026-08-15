<?php
/**
 * Revenue analytics admin assets.
 *
 * @package SimpleCheckoutFieldsEditor
 */

declare( strict_types=1 );

namespace DesignTich\SimpleCheckoutFieldsEditor\Modules\RevenueAnalytics;

/**
 * Loads the existing admin bundle only on the revenue screen.
 */
final class RevenueAssets {

	private const HANDLE = 'scfe-revenue-analytics';

	/**
	 * Create the asset loader.
	 *
	 * @param string $plugin_dir Plugin path.
	 * @param string $plugin_url Plugin URL.
	 * @param string $version    Fallback version.
	 */
	public function __construct(
		private string $plugin_dir,
		private string $plugin_url,
		private string $version
	) {
	}

	/**
	 * Enqueue the revenue screen bundle.
	 *
	 * @param string $hook_suffix Current admin screen.
	 */
	public function enqueue( string $hook_suffix ): void {
		if ( ! str_contains( $hook_suffix, RevenuePage::SLUG ) ) {
			return;
		}

		$asset_file = $this->plugin_dir . 'build/dashboard.asset.php';

		if ( ! is_readable( $asset_file ) ) {
			return;
		}

		$asset         = require $asset_file;
		$dependencies  = is_array( $asset['dependencies'] ?? null ) ? $asset['dependencies'] : array();
		$asset_version = is_string( $asset['version'] ?? null ) ? $asset['version'] : $this->version;
		$dependencies  = apply_filters( 'scfe_admin_asset_dependencies', $dependencies, $hook_suffix );
		$dependencies  = is_array( $dependencies ) ? array_values( array_filter( $dependencies, 'is_string' ) ) : array();

		add_filter(
			'admin_body_class',
			static fn( string $classes ): string => $classes . ' scfe-admin'
		);

		wp_enqueue_script( self::HANDLE, $this->plugin_url . 'build/dashboard.js', $dependencies, $asset_version, true );
		wp_set_script_translations( self::HANDLE, 'checkout-lift-for-woocommerce', $this->plugin_dir . 'languages' );

		if ( is_readable( $this->plugin_dir . 'build/style-dashboard.css' ) ) {
			wp_enqueue_style( self::HANDLE, $this->plugin_url . 'build/style-dashboard.css', array( 'wp-components' ), $asset_version );
			wp_style_add_data( self::HANDLE, 'rtl', 'replace' );
		}
	}
}
