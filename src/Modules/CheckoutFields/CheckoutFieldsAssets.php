<?php
/**
 * Base checkout field presentation assets.
 *
 * @package SimpleCheckoutFieldsEditor
 */

declare( strict_types=1 );

namespace DesignTich\SimpleCheckoutFieldsEditor\Modules\CheckoutFields;

use DesignTich\SimpleCheckoutFieldsEditor\Core\Checkout\CheckoutBlocks;
use DesignTich\SimpleCheckoutFieldsEditor\Core\Settings\CheckoutFieldsRepository;

/**
 * Loads shared classic checkout field presentation styles.
 */
final class CheckoutFieldsAssets {

	/**
	 * Create the asset loader.
	 *
	 * @param CheckoutFieldsRepository $checkout_fields Checkout field settings.
	 * @param FieldWidthStyles         $width_styles    Classic per-field width CSS.
	 * @param BlocksFieldWidthStyles   $block_widths    Block per-field width CSS.
	 * @param CheckoutBlocks           $blocks          Which checkout is configured.
	 * @param string                   $plugin_url      Plugin URL.
	 * @param string                   $version         Asset version.
	 */
	public function __construct(
		private CheckoutFieldsRepository $checkout_fields,
		private FieldWidthStyles $width_styles,
		private BlocksFieldWidthStyles $block_widths,
		private CheckoutBlocks $blocks,
		private string $plugin_url,
		private string $version
	) {
	}

	/**
	 * Register asset hooks.
	 */
	public function register_hooks(): void {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue' ) );
	}

	/**
	 * Enqueue checkout-only presentation CSS.
	 */
	public function enqueue(): void {
		if ( ! is_checkout() ) {
			return;
		}

		wp_enqueue_style( 'scfe-checkout-fields', $this->plugin_url . 'assets/css/checkout-fields.css', array(), $this->version );

		// Each checkout lays its fields out differently, so each needs its own
		// rules. Emitting both would leave one set matching nothing.
		$width_css = $this->blocks->in_use()
			? $this->block_widths->css()
			: $this->width_styles->css();

		if ( '' !== $width_css ) {
			wp_add_inline_style( 'scfe-checkout-fields', $width_css );
		}

		if ( ! $this->blocks->in_use() && $this->has_basic_conditions() ) {
			wp_enqueue_script( 'scfe-checkout-field-conditions', $this->plugin_url . 'assets/js/checkout-field-conditions.js', array( 'jquery' ), $this->version, true );
		}
	}

	/**
	 * Check whether any configured field has basic field conditions.
	 */
	private function has_basic_conditions(): bool {
		foreach ( $this->checkout_fields->fields() as $section_fields ) {
			foreach ( $section_fields as $field ) {
				if (
					is_array( $field['conditions'] ?? null )
					&& isset( $field['conditions']['rules'] )
					&& is_array( $field['conditions']['rules'] )
					&& array() !== $field['conditions']['rules']
				) {
					return true;
				}
			}
		}

		return false;
	}
}
