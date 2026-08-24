<?php
/**
 * Multilingual module.
 *
 * @package SimpleCheckoutFieldsEditor
 */

declare( strict_types=1 );

namespace DesignTich\SimpleCheckoutFieldsEditor\Modules\Multilingual;

use DesignTich\SimpleCheckoutFieldsEditor\Core\Contracts\Module;

/**
 * Registers WPML and Polylang integration hooks.
 */
final class MultilingualModule implements Module {

	/**
	 * Create the module.
	 *
	 * @param MultilingualProviders       $providers Provider detector.
	 * @param CheckoutFieldStringRegistry $checkout_strings Checkout-field string registry.
	 * @param CheckoutFieldsTranslator    $checkout_translator Checkout-field translator.
	 * @param MultilingualAdminNotice     $notice Admin notice and dismiss action.
	 */
	public function __construct(
		private MultilingualProviders $providers,
		private CheckoutFieldStringRegistry $checkout_strings,
		private CheckoutFieldsTranslator $checkout_translator,
		private MultilingualAdminNotice $notice
	) {
	}

	/**
	 * {@inheritDoc}
	 */
	public function id(): string {
		return 'multilingual';
	}

	/**
	 * {@inheritDoc}
	 */
	public function dependencies(): array {
		return array( 'checkout-fields' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function boot(): void {
		add_action( 'update_option_scfe_checkout_fields', array( $this->checkout_strings, 'register_on_save' ), 10, 2 );
		add_filter( 'woocommerce_checkout_fields', array( $this->checkout_translator, 'translate' ), 999 );
		add_action( 'admin_notices', array( $this->notice, 'render' ) );
		add_action( 'wp_ajax_scfe_dismiss_multilingual_notice', array( $this->notice, 'dismiss' ) );
		$this->register_provider_boot_hooks();
	}

	/**
	 * Register provider-specific delayed string registration hooks.
	 */
	private function register_provider_boot_hooks(): void {
		if ( $this->providers->wpml_active() ) {
			add_action( 'wpml_loaded', array( $this->checkout_strings, 'register_saved_settings' ) );
		}

		if ( $this->providers->polylang_active() ) {
			add_action( 'pll_init', array( $this->checkout_strings, 'register_saved_settings' ) );
		}
	}
}
