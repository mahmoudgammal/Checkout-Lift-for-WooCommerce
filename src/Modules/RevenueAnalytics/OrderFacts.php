<?php
/**
 * Revenue facts derived from WooCommerce orders.
 *
 * @package SimpleCheckoutFieldsEditor
 */

declare( strict_types=1 );

namespace DesignTich\SimpleCheckoutFieldsEditor\Modules\RevenueAnalytics;

use WC_Order;

/**
 * Defines placed and recognized revenue consistently.
 */
final class OrderFacts {

	/**
	 * Calculate stored order facts.
	 *
	 * @param WC_Order $order WooCommerce order.
	 * @return array{status:string,total:string,recognized:string,refunded:string,currency:string}
	 */
	public function for_order( WC_Order $order ): array {
		$total      = max( 0.0, (float) $order->get_total() );
		$refunded   = max( 0.0, min( $total, (float) $order->get_total_refunded() ) );
		$status     = (string) $order->get_status();
		$recognized = $this->is_recognized( $order, $status ) ? max( 0.0, $total - $refunded ) : 0.0;

		return array(
			'status'     => sanitize_key( $status ),
			'total'      => wc_format_decimal( $total, 4 ),
			'recognized' => wc_format_decimal( $recognized, 4 ),
			'refunded'   => wc_format_decimal( $refunded, 4 ),
			'currency'   => strtoupper( substr( (string) $order->get_currency(), 0, 3 ) ),
		);
	}

	/**
	 * Decide whether the order currently represents recognized revenue.
	 *
	 * @param WC_Order $order  WooCommerce order.
	 * @param string   $status Order status.
	 */
	private function is_recognized( WC_Order $order, string $status ): bool {
		$paid_statuses = function_exists( 'wc_get_is_paid_statuses' ) ? wc_get_is_paid_statuses() : array( 'processing', 'completed' );

		if ( ! in_array( $status, $paid_statuses, true ) ) {
			return false;
		}

		return 'cod' !== $order->get_payment_method() || 'completed' === $status;
	}
}
