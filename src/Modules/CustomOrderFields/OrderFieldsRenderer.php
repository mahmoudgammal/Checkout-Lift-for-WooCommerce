<?php
/**
 * Custom order-field rendering.
 *
 * @package SimpleCheckoutFieldsEditor
 */

declare( strict_types=1 );

namespace DesignTich\SimpleCheckoutFieldsEditor\Modules\CustomOrderFields;

use WC_Order;

/**
 * Renders base custom fields in admin and customer order views.
 */
final class OrderFieldsRenderer {

	/**
	 * Create the order fields renderer.
	 *
	 * @param OrderFieldRows $field_rows Order display rows.
	 */
	public function __construct( private OrderFieldRows $field_rows ) {
	}

	/**
	 * Register order display hooks.
	 */
	public function register_hooks(): void {
		add_action( 'woocommerce_admin_order_data_after_billing_address', array( $this, 'render_admin' ) );
		add_action( 'woocommerce_order_details_after_order_table', array( $this, 'render_customer' ) );
	}

	/**
	 * Render fields once in the admin billing column.
	 *
	 * @param WC_Order $order Current order.
	 */
	public function render_admin( WC_Order $order ): void {
		$rows = $this->field_rows->for_order( $order );

		if ( array() === $rows ) {
			return;
		}

		echo '<div class="scfe-custom-fields"><h3>';
		esc_html_e( 'Custom Fields', 'checkout-lift-for-woocommerce' );
		echo '</h3>';

		foreach ( $rows as $row ) {
			$this->render_admin_row( $row );
		}

		echo '</div>';
	}

	/**
	 * Render fields after customer order details.
	 *
	 * @param WC_Order $order Current order.
	 */
	public function render_customer( WC_Order $order ): void {
		$rows = $this->field_rows->for_order( $order );

		if ( array() === $rows ) {
			return;
		}

		echo '<section class="woocommerce-customer-details scfe-custom-fields-thankyou"><h2>';
		esc_html_e( 'Additional Information', 'checkout-lift-for-woocommerce' );
		echo '</h2><table class="woocommerce-table woocommerce-table--customer-details shop_table customer_details"><tbody>';

		foreach ( $rows as $row ) {
			$this->render_customer_row( $row );
		}

		echo '</tbody></table></section>';
	}

	/**
	 * Render one admin row.
	 *
	 * @param array{label: string, value: string} $row Display row.
	 */
	private function render_admin_row( array $row ): void {
		echo '<p><strong>' . esc_html( $row['label'] ) . ':</strong> ' . esc_html( $row['value'] ) . '</p>';
	}

	/**
	 * Render one customer table row.
	 *
	 * @param array{label: string, value: string} $row Display row.
	 */
	private function render_customer_row( array $row ): void {
		echo '<tr><th>' . esc_html( $row['label'] ) . '</th><td>' . esc_html( $row['value'] ) . '</td></tr>';
	}
}
