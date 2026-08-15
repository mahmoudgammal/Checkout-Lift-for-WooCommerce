<?php
/**
 * Recovery attribution event consumer.
 *
 * @package SimpleCheckoutFieldsEditor
 */

declare( strict_types=1 );

namespace DesignTich\SimpleCheckoutFieldsEditor\Modules\RevenueAnalytics;

use WC_Order;

/**
 * Converts trusted recovery events into attribution facts.
 */
final class AttributionRecorder {

	/**
	 * Create the recorder.
	 *
	 * @param AttributionRepository $attributions Attribution storage.
	 * @param AttemptTracker        $tracker      Attempt tracker.
	 */
	public function __construct(
		private AttributionRepository $attributions,
		private AttemptTracker $tracker
	) {
	}

	/**
	 * Record server-confirmed recovery value.
	 *
	 * @param WC_Order $order  WooCommerce order.
	 * @param array    $values Product revenue keyed by recovery cart ID.
	 * @phpstan-param array<int, string> $values
	 */
	public function record_recovery( WC_Order $order, array $values ): void {
		$attempt_id = $this->tracker->current_attempt_id();
		if ( $attempt_id < 1 ) {
			return;
		}

		foreach ( $values as $cart_id => $value ) {
			$this->attributions->upsert(
				$attempt_id,
				$order->get_id(),
				'recovery',
				'cart-' . (int) $cart_id,
				(string) $order->get_currency(),
				$value
			);
		}
	}

	/**
	 * Reconcile server-confirmed recovery refunds.
	 *
	 * @param int   $order_id WooCommerce order ID.
	 * @param array $values   Refunded product revenue keyed by recovery cart ID.
	 * @phpstan-param array<int, string> $values
	 */
	public function record_recovery_refund( int $order_id, array $values ): void {
		$this->attributions->reconcile_refunds( $order_id, $values );
	}
}
