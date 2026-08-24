<?php
/**
 * Basic facts for a placed checkout.
 *
 * @package SimpleCheckoutFieldsEditor
 */

declare( strict_types=1 );

namespace DesignTich\SimpleCheckoutFieldsEditor\Modules\RevenueAnalytics;

use WC_Order;

/**
 * Normalizes the order value used by Lite checkout analytics.
 */
final class PlacedOrderFacts {

	/**
	 * Calculate the basic placed-order facts.
	 *
	 * @param WC_Order $order WooCommerce order.
	 * @return array{total:string,currency:string}
	 */
	public function for_order( WC_Order $order ): array {
		return array(
			'total'    => wc_format_decimal( max( 0.0, (float) $order->get_total() ), 4 ),
			'currency' => strtoupper( substr( (string) $order->get_currency(), 0, 3 ) ),
		);
	}
}
