<?php
/**
 * Plugin Name:          Checkout Lift for WooCommerce
 * Plugin URI:           https://designtich.com/checkout-lift-for-woocommerce/
 * Description:          Customize WooCommerce checkout fields, track unfinished checkouts, and measure potential abandoned cart value.
 * Version:              1.0.2
 * Author:               Design Tich
 * Author URI:           https://designtich.com/
 * Text Domain:          checkout-lift-for-woocommerce
 * Domain Path:          /languages
 * License:              GPL-2.0-or-later
 * License URI:          https://www.gnu.org/licenses/gpl-2.0.html
 * Requires at least:    7.0
 * Requires PHP:         8.0
 * WC requires at least: 10.0
 * WC tested up to:      11.3
 * Requires Plugins:     woocommerce
 *
 * @package SimpleCheckoutFieldsEditor
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'SCFE_VERSION' ) ) {
	define( 'SCFE_VERSION', '1.0.2' );
}
if ( ! defined( 'SCFE_PLUGIN_DIR' ) ) {
	define( 'SCFE_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
}
if ( ! defined( 'SCFE_PLUGIN_URL' ) ) {
	define( 'SCFE_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
}
if ( ! defined( 'SCFE_PLUGIN_BASENAME' ) ) {
	define( 'SCFE_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
}
if ( ! defined( 'SCFE_PLUGIN_FILE' ) ) {
	define( 'SCFE_PLUGIN_FILE', __FILE__ );
}
/**
 * Autoload first-party namespaced classes.
 *
 * @param string $class_name Fully qualified class name.
 */
function scfe_autoload( $class_name ) {
	$prefix = 'DesignTich\\SimpleCheckoutFieldsEditor\\';

	if ( 0 !== strpos( $class_name, $prefix ) ) {
		return;
	}

	$relative_class = substr( $class_name, strlen( $prefix ) );
	$file           = SCFE_PLUGIN_DIR . 'src/' . str_replace( '\\', '/', $relative_class ) . '.php';

	if ( is_readable( $file ) ) {
		require_once $file;
	}
}
spl_autoload_register( 'scfe_autoload' );

/**
 * Check if WooCommerce is active.
 *
 * @return bool
 */
function scfe_is_woocommerce_active() {
	return class_exists( 'WooCommerce' );
}

/**
 * Display admin notice if WooCommerce is not active.
 */
function scfe_woocommerce_missing_notice() {
	$woocommerce_file = WP_PLUGIN_DIR . '/woocommerce/woocommerce.php';
	$woocommerce_url  = self_admin_url( 'plugins.php' );
	$button_label     = __( 'Activate WooCommerce', 'checkout-lift-for-woocommerce' );

	if ( ! is_readable( $woocommerce_file ) && current_user_can( 'install_plugins' ) ) {
		$woocommerce_url = self_admin_url( 'plugin-install.php?s=woocommerce&tab=search&type=term' );
		$button_label    = __( 'Install WooCommerce', 'checkout-lift-for-woocommerce' );
	}
	?>
	<div class="notice notice-error" id="scfe-woocommerce-required">
		<p>
			<strong><?php esc_html_e( 'Checkout Lift was not activated.', 'checkout-lift-for-woocommerce' ); ?></strong><br>
			<?php esc_html_e( 'Install and activate WooCommerce first, then activate Checkout Lift again.', 'checkout-lift-for-woocommerce' ); ?>
		</p>
		<p><a class="button button-primary" href="<?php echo esc_url( $woocommerce_url ); ?>"><?php echo esc_html( $button_label ); ?></a></p>
	</div>
	<?php
}

/**
 * Keep the plugin inactive when WooCommerce is unavailable.
 */
function scfe_enforce_woocommerce_dependency() {
	if ( scfe_is_woocommerce_active() || ! function_exists( 'deactivate_plugins' ) ) {
		return;
	}

	$network_wide = is_multisite() && is_plugin_active_for_network( SCFE_PLUGIN_BASENAME );
	$capability   = $network_wide ? 'manage_network_plugins' : 'activate_plugins';

	if ( ! current_user_can( $capability ) ) {
		return;
	}

	deactivate_plugins( SCFE_PLUGIN_BASENAME, false, $network_wide );
	unset( $_GET['activate'], $_GET['activate-multi'] );
}
add_action( 'admin_init', 'scfe_enforce_woocommerce_dependency' );

/**
 * Declare compatibility with WooCommerce features.
 * Must run before plugins_loaded to properly declare compatibility.
 */
function scfe_declare_compatibility() {
	if ( class_exists( '\\Automattic\\WooCommerce\\Utilities\\FeaturesUtil' ) ) {
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', SCFE_PLUGIN_FILE, true );
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', SCFE_PLUGIN_FILE, false );
	}
}
add_action( 'before_woocommerce_init', 'scfe_declare_compatibility' );

/**
 * Initialize the plugin after plugins are loaded.
 */
function scfe_init() {
	if ( ! scfe_is_woocommerce_active() ) {
		add_action( 'admin_notices', 'scfe_woocommerce_missing_notice' );
		add_action( 'network_admin_notices', 'scfe_woocommerce_missing_notice' );
		return;
	}
	\DesignTich\SimpleCheckoutFieldsEditor\Core\Plugin::get_instance();
}
add_action( 'plugins_loaded', 'scfe_init' );

/**
 * Stop scheduled work when the plugin is inactive.
 */
function scfe_deactivate() {
	$recovery_scan_hook = class_exists( \DesignTich\SimpleCheckoutFieldsEditor\Core\Recovery\RecoveryHooks::class )
		? \DesignTich\SimpleCheckoutFieldsEditor\Core\Recovery\RecoveryHooks::SCAN
		: 'scfe_recovery_scan';
	$recovery_send_hook = class_exists( \DesignTich\SimpleCheckoutFieldsEditor\Core\Recovery\RecoveryHooks::class )
		? \DesignTich\SimpleCheckoutFieldsEditor\Core\Recovery\RecoveryHooks::SEND
		: 'scfe_recovery_send';

	wp_clear_scheduled_hook( \DesignTich\SimpleCheckoutFieldsEditor\Core\Privacy\RetentionSweep::HOOK );
	wp_clear_scheduled_hook( $recovery_scan_hook );
	wp_clear_scheduled_hook( $recovery_send_hook );
	wp_clear_scheduled_hook( \DesignTich\SimpleCheckoutFieldsEditor\Core\Settings\ModuleScheduleHooks::REVENUE_CLASSIFY );
}
register_deactivation_hook( SCFE_PLUGIN_FILE, 'scfe_deactivate' );
