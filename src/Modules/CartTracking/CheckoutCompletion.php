<?php
/**
 * Tracked-checkout completion.
 *
 * @package SimpleCheckoutFieldsEditor
 */

declare( strict_types=1 );

namespace DesignTich\SimpleCheckoutFieldsEditor\Modules\CartTracking;

use DesignTich\SimpleCheckoutFieldsEditor\Core\Database\CartRepository;
use WC_Order;

/**
 * Closes the checkout tracked against the current WooCommerce session.
 */
final class CheckoutCompletion {

	/**
	 * Create the completion recorder.
	 *
	 * @param CartRepository $carts Cart storage.
	 */
	public function __construct( private CartRepository $carts ) {
	}

	/**
	 * Close a Classic Checkout session.
	 *
	 * @param int $order_id Created order ID.
	 */
	public function from_classic_checkout( int $order_id ): void {
		$order = wc_get_order( $order_id );

		if ( $order instanceof WC_Order ) {
			$this->close( $order );
		}
	}

	/**
	 * Close a Checkout Blocks session.
	 *
	 * @param WC_Order $order Created order.
	 */
	public function from_store_api( WC_Order $order ): void {
		$this->close( $order );
	}

	/**
	 * Mark the current tracked cart as completed.
	 *
	 * @param WC_Order $order Created order.
	 */
	private function close( WC_Order $order ): void {
		if ( $order->get_id() < 1 || ! function_exists( 'WC' ) || null === WC()->session ) {
			return;
		}

		$cart = $this->carts->find_by_session( (string) WC()->session->get_customer_id() );

		if ( null === $cart ) {
			return;
		}

		$this->carts->update(
			(int) $cart['id'],
			array(
				'status'             => 'completed',
				'recovered_order_id' => $order->get_id(),
			)
		);
	}
}
