<?php
/**
 * Classic checkout field hooks.
 *
 * @package SimpleCheckoutFieldsEditor
 */

declare( strict_types=1 );

namespace DesignTich\SimpleCheckoutFieldsEditor\Modules\CheckoutFields;

use DesignTich\SimpleCheckoutFieldsEditor\Core\Settings\CheckoutFieldsRepository;
use WP_Error;

/**
 * Adapts pure field decisions to classic WooCommerce checkout hooks.
 */
final class ClassicCheckoutAdapter {

	/**
	 * Create the classic checkout adapter.
	 *
	 * @param CheckoutFieldsRepository        $checkout_fields Stored field settings.
	 * @param CheckoutFieldsTransformer       $checkout_transformer Checkout transformer.
	 * @param DefaultAddressFieldsTransformer $address_transformer Default address transformer.
	 * @param DisabledFields                  $disabled_fields Disabled-field decisions.
	 */
	public function __construct(
		private CheckoutFieldsRepository $checkout_fields,
		private CheckoutFieldsTransformer $checkout_transformer,
		private DefaultAddressFieldsTransformer $address_transformer,
		private DisabledFields $disabled_fields
	) {
	}

	/**
	 * Register classic checkout hooks.
	 */
	public function register_hooks(): void {
		add_filter( 'woocommerce_checkout_fields', array( $this, 'filter_checkout_fields' ), 1000 );
		add_filter( 'woocommerce_default_address_fields', array( $this, 'filter_default_address_fields' ), 1000 );
		add_filter( 'woocommerce_billing_fields', array( $this, 'filter_billing_fields' ), 1000 );
		add_filter( 'woocommerce_shipping_fields', array( $this, 'filter_shipping_fields' ), 1000 );
		add_action( 'woocommerce_after_checkout_validation', array( $this, 'remove_disabled_errors' ), 10, 2 );
		add_filter( 'woocommerce_cart_needs_shipping_address', array( $this, 'shipping_address_needed' ) );
		add_filter( 'scfe_hidden_checkout_fields', array( $this->disabled_fields, 'add_to_hidden_fields' ) );
	}

	/**
	 * Transform configured checkout sections.
	 *
	 * @param array<string, array<string, array<string, mixed>>> $checkout_fields Current checkout fields.
	 * @return array<string, array<string, array<string, mixed>>>
	 */
	public function filter_checkout_fields( array $checkout_fields ): array {
		return $this->checkout_transformer->transform( $checkout_fields, $this->checkout_fields->fields() );
	}

	/**
	 * Apply billing settings to shared address definitions.
	 *
	 * @param array<string, array<string, mixed>> $address_fields Default address fields.
	 * @return array<string, array<string, mixed>>
	 */
	public function filter_default_address_fields( array $address_fields ): array {
		$billing_settings = $this->checkout_fields->fields()['billing'] ?? array();

		return $this->address_transformer->transform( $address_fields, $billing_settings );
	}

	/**
	 * Remove disabled billing fields.
	 *
	 * @param array<string, array<string, mixed>> $billing_fields Billing fields.
	 * @return array<string, array<string, mixed>>
	 */
	public function filter_billing_fields( array $billing_fields ): array {
		return $this->disabled_fields->filter_section( $billing_fields, 'billing' );
	}

	/**
	 * Remove disabled shipping fields.
	 *
	 * @param array<string, array<string, mixed>> $shipping_fields Shipping fields.
	 * @return array<string, array<string, mixed>>
	 */
	public function filter_shipping_fields( array $shipping_fields ): array {
		return $this->disabled_fields->filter_section( $shipping_fields, 'shipping' );
	}

	/**
	 * Remove validation errors for disabled fields.
	 *
	 * WooCommerce verifies its checkout nonce before this hook runs.
	 *
	 * @param array<string, mixed> $posted_fields Processed checkout values.
	 * @param WP_Error             $validation_errors Checkout errors.
	 */
	public function remove_disabled_errors( array $posted_fields, WP_Error $validation_errors ): void {
		unset( $posted_fields );
		$this->disabled_fields->remove_validation_errors( $validation_errors );
	}

	/**
	 * Disable the shipping-address section when configured.
	 *
	 * @param bool $needs_shipping_address Current WooCommerce decision.
	 */
	public function shipping_address_needed( bool $needs_shipping_address ): bool {
		return $this->checkout_transformer->shipping_address_needed( $needs_shipping_address );
	}
}
