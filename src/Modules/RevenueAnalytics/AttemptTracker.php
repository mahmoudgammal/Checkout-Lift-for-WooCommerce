<?php
/**
 * Checkout-attempt lifecycle integration.
 *
 * @package SimpleCheckoutFieldsEditor
 */

declare( strict_types=1 );

namespace DesignTich\SimpleCheckoutFieldsEditor\Modules\RevenueAnalytics;

use DesignTich\SimpleCheckoutFieldsEditor\Core\Checkout\CheckoutBlocks;
use DesignTich\SimpleCheckoutFieldsEditor\Core\Privacy\PrivacySettings;
use WC_Order;

/**
 * Connects checkout and order hooks to attempt storage.
 */
final class AttemptTracker {

	/**
	 * Create the tracker.
	 *
	 * @param AttemptRepository $attempts Attempt storage.
	 * @param AttemptSession    $session  Session token.
	 * @param PlacedOrderFacts  $facts    Placed-order fact calculator.
	 * @param PrivacySettings   $privacy  Tracking preference.
	 * @param CheckoutBlocks    $blocks   Runtime detector.
	 */
	public function __construct(
		private AttemptRepository $attempts,
		private AttemptSession $session,
		private PlacedOrderFacts $facts,
		private PrivacySettings $privacy,
		private CheckoutBlocks $blocks
	) {
	}

	/**
	 * Record meaningful checkout activity.
	 */
	public function touch(): void {
		if ( ! $this->privacy->tracking_enabled() ) {
			return;
		}

		$hash = $this->session->hash();

		if ( 0 === $this->attempts->id_for_hash( $hash ) ) {
			$this->start();
			return;
		}

		$this->attempts->touch( $hash );
	}

	/**
	 * Link a Classic Checkout order.
	 *
	 * @param int $order_id WooCommerce order ID.
	 */
	public function from_classic_checkout( int $order_id ): void {
		$order = wc_get_order( $order_id );

		if ( $order instanceof WC_Order ) {
			$this->place( $order );
		}
	}

	/**
	 * Link a Checkout Blocks order.
	 *
	 * @param WC_Order $order WooCommerce order.
	 */
	public function from_store_api( WC_Order $order ): void {
		$this->place( $order );
	}

	/**
	 * Persist the current attempt.
	 */
	private function start(): void {
		$this->attempts->start(
			$this->session->hash(),
			$this->blocks->in_use() ? 'blocks' : 'classic',
			function_exists( 'get_woocommerce_currency' ) ? (string) get_woocommerce_currency() : ''
		);
	}

	/**
	 * Close the current attempt with an order.
	 *
	 * @param WC_Order $order WooCommerce order.
	 */
	private function place( WC_Order $order ): void {
		if ( ! $this->privacy->tracking_enabled() ) {
			return;
		}

		$hash = $this->session->hash();

		if ( 0 === $this->attempts->id_for_hash( $hash ) ) {
			$this->start();
		}

		$this->attempts->place( $hash, $order->get_id(), $this->facts->for_order( $order ) );
		$this->session->rotate();
	}
}
