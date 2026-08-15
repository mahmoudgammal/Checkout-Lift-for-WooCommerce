<?php
/**
 * Recovery dashboard assets.
 *
 * @package SimpleCheckoutFieldsEditor
 */

declare( strict_types=1 );

namespace DesignTich\SimpleCheckoutFieldsEditor\Modules\RecoveryDashboard;

use DesignTich\SimpleCheckoutFieldsEditor\Core\Checkout\CheckoutBlocks;
use DesignTich\SimpleCheckoutFieldsEditor\Core\Features\Features;
use DesignTich\SimpleCheckoutFieldsEditor\Core\Settings\ModuleSettings;

/**
 * Loads the built React bundle on its own screen only.
 *
 * Dependencies and version come from the `.asset.php` file the build emits,
 * so WordPress packages such as `wp-element` are declared accurately rather
 * than guessed, and cache busting follows the build rather than the plugin
 * version.
 */
final class DashboardAssets {

	/**
	 * Script and style handle.
	 */
	private const HANDLE = 'scfe-recovery-dashboard';

	/**
	 * Create the asset loader.
	 *
	 * @param CheckoutBlocks     $blocks     Which checkout the store runs.
	 * @param ModuleSettings     $modules    Which parts the merchant switched on.
	 * @param Features           $features   Which parts this build ships.
	 * @param string             $plugin_dir Plugin directory path.
	 * @param string             $plugin_url Plugin URL.
	 * @param string             $version    Fallback asset version.
	 * @param array<int, string> $slugs      Screens this bundle serves.
	 */
	public function __construct(
		private CheckoutBlocks $blocks,
		private ModuleSettings $modules,
		private Features $features,
		private string $plugin_dir,
		private string $plugin_url,
		private string $version,
		private array $slugs = array( DashboardPage::SLUG )
	) {
	}

	/**
	 * Return whether this screen is one the bundle serves.
	 *
	 * @param string $hook_suffix Current admin screen.
	 */
	private function serves( string $hook_suffix ): bool {
		foreach ( $this->slugs as $slug ) {
			if ( str_contains( $hook_suffix, $slug ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Enqueue the dashboard bundle.
	 *
	 * @param string $hook_suffix Current admin screen.
	 */
	public function enqueue( string $hook_suffix ): void {
		if ( ! $this->serves( $hook_suffix ) ) {
			return;
		}

		$asset_file = $this->plugin_dir . 'build/dashboard.asset.php';

		if ( ! is_readable( $asset_file ) ) {
			// Leave the server-rendered fallback visible when the bundle is absent.
			return;
		}

		// The admin shell sits outside the React root and needs a body class.
		add_filter(
			'admin_body_class',
			static function ( $classes ) {
				return $classes . ' scfe-admin';
			}
		);

		$asset        = require $asset_file;
		$dependencies = is_array( $asset['dependencies'] ?? null ) ? $asset['dependencies'] : array();

		/**
		 * Filter admin bundle dependencies for add-on extension entries.
		 *
		 * @since 1.0.0
		 *
		 * @param list<string> $dependencies Script handles required before the Lite admin app.
		 * @param string       $hook_suffix  Current admin screen.
		 */
		$dependencies = apply_filters( 'scfe_admin_asset_dependencies', $dependencies, $hook_suffix );
		$dependencies = is_array( $dependencies ) ? array_values( array_filter( $dependencies, 'is_string' ) ) : array();

		wp_enqueue_script(
			self::HANDLE,
			$this->plugin_url . 'build/dashboard.js',
			$dependencies,
			is_string( $asset['version'] ?? null ) ? $asset['version'] : $this->version,
			true
		);

		wp_set_script_translations(
			self::HANDLE,
			'checkout-lift-for-woocommerce',
			$this->plugin_dir . 'languages'
		);

		// Checkout type is immutable for this screen and is embedded with the initial state.
		$effective_modules = array();

		foreach ( $this->modules->all() as $module => $enabled ) {
			$effective_modules[ $module ] = $enabled && $this->features->enabled( $module );
		}

		wp_localize_script(
			self::HANDLE,
			'scfe_admin',
			array(
				'blockCheckout' => $this->blocks->in_use(),
				// Tabs need module availability before their first render.
				'modules'       => $effective_modules,
			)
		);

		// Webpack prefixes entry-imported stylesheets with `style-`.
		if ( is_readable( $this->plugin_dir . 'build/style-dashboard.css' ) ) {
			wp_enqueue_style(
				self::HANDLE,
				$this->plugin_url . 'build/style-dashboard.css',
				array( 'wp-components' ),
				is_string( $asset['version'] ?? null ) ? $asset['version'] : $this->version
			);

			// WordPress serves the mirrored build for RTL admin locales.
			wp_style_add_data( self::HANDLE, 'rtl', 'replace' );
		}
	}
}
