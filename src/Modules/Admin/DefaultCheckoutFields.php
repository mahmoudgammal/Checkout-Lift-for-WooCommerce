<?php
/**
 * Default checkout fields for the admin editor.
 *
 * @package SimpleCheckoutFieldsEditor
 */

declare( strict_types=1 );

namespace DesignTich\SimpleCheckoutFieldsEditor\Modules\Admin;

/**
 * Provides a stable admin-side fallback for WooCommerce standard fields.
 */
final class DefaultCheckoutFields {

	/**
	 * Return default checkout-field sections.
	 *
	 * @return array<string, array<string, array<string, mixed>>>
	 */
	public function fields(): array {
		return array(
			'billing'  => $this->billing_fields(),
			'shipping' => $this->shipping_fields(),
			'order'    => array(
				'order_comments' => array(
					'label'    => __( 'Order notes', 'checkout-lift-for-woocommerce' ),
					'required' => false,
				),
			),
		);
	}

	/**
	 * Return default billing fields.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	private function billing_fields(): array {
		return array(
			'billing_first_name' => array(
				'label'    => __( 'First name', 'checkout-lift-for-woocommerce' ),
				'required' => true,
			),
			'billing_last_name'  => array(
				'label'    => __( 'Last name', 'checkout-lift-for-woocommerce' ),
				'required' => true,
			),
			'billing_company'    => array(
				'label'    => __( 'Company name', 'checkout-lift-for-woocommerce' ),
				'required' => false,
			),
			'billing_country'    => array(
				'label'    => __( 'Country / Region', 'checkout-lift-for-woocommerce' ),
				'required' => true,
			),
			'billing_address_1'  => array(
				'label'    => __( 'Street address', 'checkout-lift-for-woocommerce' ),
				'required' => true,
			),
			'billing_address_2'  => array(
				'label'    => __( 'Apartment, suite, unit, etc.', 'checkout-lift-for-woocommerce' ),
				'required' => false,
			),
			'billing_city'       => array(
				'label'    => __( 'Town / City', 'checkout-lift-for-woocommerce' ),
				'required' => true,
			),
			'billing_state'      => array(
				'label'    => __( 'State / County', 'checkout-lift-for-woocommerce' ),
				'required' => true,
			),
			'billing_postcode'   => array(
				'label'    => __( 'Postcode / ZIP', 'checkout-lift-for-woocommerce' ),
				'required' => true,
			),
			'billing_phone'      => array(
				'label'    => __( 'Phone', 'checkout-lift-for-woocommerce' ),
				'required' => true,
			),
			'billing_email'      => array(
				'label'    => __( 'Email address', 'checkout-lift-for-woocommerce' ),
				'required' => true,
			),
		);
	}

	/**
	 * Return default shipping fields.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	private function shipping_fields(): array {
		return array(
			'shipping_first_name' => array(
				'label'    => __( 'First name', 'checkout-lift-for-woocommerce' ),
				'required' => true,
			),
			'shipping_last_name'  => array(
				'label'    => __( 'Last name', 'checkout-lift-for-woocommerce' ),
				'required' => true,
			),
			'shipping_company'    => array(
				'label'    => __( 'Company name', 'checkout-lift-for-woocommerce' ),
				'required' => false,
			),
			'shipping_country'    => array(
				'label'    => __( 'Country / Region', 'checkout-lift-for-woocommerce' ),
				'required' => true,
			),
			'shipping_address_1'  => array(
				'label'    => __( 'Street address', 'checkout-lift-for-woocommerce' ),
				'required' => true,
			),
			'shipping_address_2'  => array(
				'label'    => __( 'Apartment, suite, unit, etc.', 'checkout-lift-for-woocommerce' ),
				'required' => false,
			),
			'shipping_city'       => array(
				'label'    => __( 'Town / City', 'checkout-lift-for-woocommerce' ),
				'required' => true,
			),
			'shipping_state'      => array(
				'label'    => __( 'State / County', 'checkout-lift-for-woocommerce' ),
				'required' => true,
			),
			'shipping_postcode'   => array(
				'label'    => __( 'Postcode / ZIP', 'checkout-lift-for-woocommerce' ),
				'required' => true,
			),
		);
	}
}
