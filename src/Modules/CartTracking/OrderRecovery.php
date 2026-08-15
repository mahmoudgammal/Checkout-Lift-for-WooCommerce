<?php
/**
 * Closes a stored cart when its checkout becomes an order.
 *
 * @package SimpleCheckoutFieldsEditor
 */

declare( strict_types=1 );

namespace DesignTich\SimpleCheckoutFieldsEditor\Modules\CartTracking;

use DesignTich\SimpleCheckoutFieldsEditor\Core\Database\CartRepository;
use WC_Abstract_Order;
use WC_Order;
use WC_Order_Item_Product;

/**
 * Records the outcome of a tracked checkout.
 *
 * This is what makes recovery measurable. A cart that ends in an order after
 * the customer followed a recovery link is `recovered`; one that ends in an
 * order on its own is `completed`. Both stop follow-up, but only the first is
 * a recovery, and reporting them as one number would overstate the feature's
 * value to the merchant.
 */
final class OrderRecovery {

	/**
	 * Session key holding the cart a recovery link reopened.
	 */
	public const RESUMED_KEY = 'scfe_resumed_cart';

	/**
	 * Session key mapping cart item keys to recovered quantities by cart id.
	 */
	public const CONTRIBUTIONS_KEY = 'scfe_recovery_contributions';

	/**
	 * Hidden order-item meta carrying recovery quantities into the order.
	 */
	private const ITEM_META = '_scfe_recovery_contributions';

	/**
	 * Create the outcome recorder.
	 *
	 * @param CartRepository $carts Cart storage.
	 */
	public function __construct( private CartRepository $carts ) {
	}

	/**
	 * Mark the tracked cart behind a classic checkout order.
	 *
	 * @param int $order_id Created order.
	 */
	public function from_classic_checkout( int $order_id ): void {
		$order = wc_get_order( $order_id );

		if ( $order instanceof WC_Order ) {
			$this->close( $order );
		}
	}

	/**
	 * Mark the tracked cart behind a Store API order.
	 *
	 * @param WC_Order $order Created order.
	 */
	public function from_store_api( WC_Order $order ): void {
		$this->close( $order );
	}

	/**
	 * Recalculate recovered product value removed by a refund.
	 *
	 * @param int $order_id  Parent order.
	 * @param int $refund_id Refund order.
	 */
	public function from_refund( int $order_id, int $refund_id ): void {
		unset( $refund_id );
		$order = wc_get_order( $order_id );

		if ( ! $order instanceof WC_Order ) {
			return;
		}

		$refunded_minor = array();

		foreach ( $order->get_refunds() as $refund ) {
			foreach ( $this->recovered_values( $refund, true ) as $cart_id => $value ) {
				$refunded_minor[ $cart_id ] = ( $refunded_minor[ $cart_id ] ?? 0 ) + (int) wc_add_number_precision( (float) $value );
			}
		}

		$refunded_values = array_map(
			static fn( int $value ): string => wc_format_decimal( wc_remove_number_precision( $value ), 4 ),
			$refunded_minor
		);

		do_action( 'scfe_recovery_order_refunded', $order_id, $refunded_values );
	}

	/**
	 * Carry recovered quantities from their cart line into hidden order meta.
	 *
	 * @param WC_Order_Item_Product $item          Order line being created.
	 * @param string                $cart_item_key Source cart item key.
	 * @param array<string, mixed>  $values        Source cart values.
	 * @param WC_Order              $order         Order being created.
	 */
	public function tag_line_item( WC_Order_Item_Product $item, string $cart_item_key, array $values, WC_Order $order ): void {
		unset( $values, $order );

		if ( ! function_exists( 'WC' ) || null === WC()->session ) {
			return;
		}

		$contributions = WC()->session->get( self::CONTRIBUTIONS_KEY, array() );
		$by_cart       = is_array( $contributions ) && is_array( $contributions[ $cart_item_key ] ?? null )
			? $contributions[ $cart_item_key ]
			: array();

		if ( array() !== $by_cart ) {
			$item->add_meta_data( self::ITEM_META, $by_cart, true );
		}
	}

	/**
	 * Record the outcome against the stored cart, if there is one.
	 *
	 * @param WC_Order $order Created order.
	 */
	private function close( WC_Order $order ): void {
		$order_id = $order->get_id();

		if ( $order_id < 1 || ! function_exists( 'WC' ) || null === WC()->session ) {
			return;
		}

		$resumed_id = (int) WC()->session->get( self::RESUMED_KEY, 0 );
		$recovered  = $this->recovered_values( $order );

		if ( $resumed_id > 0 && ! isset( $recovered[ $resumed_id ] ) ) {
			$recovered[ $resumed_id ] = '0.0000';
		}

		if ( array() !== $recovered ) {
			$this->record_recovered_carts( $order_id, $recovered );
			do_action( 'scfe_recovery_order_attributed', $order, $recovered );
		} else {
			$this->record_completed_cart( $order_id );
		}

		WC()->session->set( self::RESUMED_KEY, 0 );
		WC()->session->set( self::CONTRIBUTIONS_KEY, array() );
	}

	/**
	 * Store recovered outcomes for every contributing source cart.
	 *
	 * @param int                $order_id Order identifier.
	 * @param array<int, string> $recovered_values Decimal value by source cart.
	 */
	private function record_recovered_carts( int $order_id, array $recovered_values ): void {
		foreach ( $recovered_values as $cart_id => $recovered_value ) {
			$this->carts->update(
				(int) $cart_id,
				array(
					'status'             => 'recovered',
					'recovered_order_id' => $order_id,
					'recovered_total'    => $recovered_value,
				)
			);
		}
	}

	/**
	 * Store an ordinary completion for the cart tied to this session.
	 *
	 * @param int $order_id Order identifier.
	 */
	private function record_completed_cart( int $order_id ): void {
		$cart_id = $this->cart_id_for_session();

		if ( $cart_id > 0 ) {
			$this->carts->update(
				$cart_id,
				array(
					'status'             => 'completed',
					'recovered_order_id' => $order_id,
				)
			);
		}
	}

	/**
	 * Calculate final product revenue attributable to each recovered cart.
	 *
	 * Discounts and tax are already reflected in order line totals. Shipping
	 * and fees are deliberately excluded because they cannot be attributed to
	 * one merged product quantity without inventing an allocation rule.
	 *
	 * @param WC_Abstract_Order $order    Completed checkout order or refund.
	 * @param bool              $absolute Whether negative refund values become positive.
	 * @return array<int, string> Decimal revenue by recovered cart id.
	 */
	private function recovered_values( WC_Abstract_Order $order, bool $absolute = false ): array {
		$minor_values = array();

		foreach ( $order->get_items( 'line_item' ) as $item ) {
			if ( ! $item instanceof WC_Order_Item_Product ) {
				continue;
			}

			$line_quantity = (float) $item->get_quantity();

			if ( $absolute ) {
				$line_quantity = abs( $line_quantity );
			}
			$quantities = $this->recovered_quantities( $item );

			if ( $line_quantity <= 0 || array() === $quantities ) {
				continue;
			}

			$line_minor = (int) round(
				wc_add_number_precision( (float) $item->get_total() )
					+ wc_add_number_precision( (float) $item->get_total_tax() )
			);

			if ( $absolute ) {
				$line_minor = abs( $line_minor );
			}

			foreach ( $this->allocate_minor_values( $line_minor, $line_quantity, $quantities ) as $cart_id => $share ) {
				$minor_values[ $cart_id ] = ( $minor_values[ $cart_id ] ?? 0 ) + $share;
			}
		}

		$recovered_values = array();

		foreach ( $minor_values as $cart_id => $minor_value ) {
			$recovered_values[ $cart_id ] = wc_format_decimal( wc_remove_number_precision( $minor_value ), 4 );
		}

		return $recovered_values;
	}

	/**
	 * Read positive recovered quantities from one order line.
	 *
	 * @param WC_Order_Item_Product $item Order product line.
	 * @return array<int, float>
	 */
	private function recovered_quantities( WC_Order_Item_Product $item ): array {
		$stored      = $item->get_meta( self::ITEM_META, true );
		$quantities  = array();
		$stored_rows = is_array( $stored ) ? $stored : array();

		foreach ( $stored_rows as $cart_id => $quantity ) {
			$cart_id  = (int) $cart_id;
			$quantity = max( 0, (float) $quantity );

			if ( $cart_id > 0 && $quantity > 0 ) {
				$quantities[ $cart_id ] = ( $quantities[ $cart_id ] ?? 0.0 ) + $quantity;
			}
		}

		ksort( $quantities, SORT_NUMERIC );

		return $quantities;
	}

	/**
	 * Allocate one line's attributable minor units without over-counting.
	 *
	 * The final cart may contain contributions from multiple recovery links.
	 * Reducing that line must cap their combined attribution at the line value,
	 * while assigning every minor unit exactly once after rounding.
	 *
	 * @param int               $line_minor    Final line total and tax in minor units.
	 * @param float             $line_quantity Final ordered quantity.
	 * @param array<int, float> $quantities    Requested recovered quantities by cart.
	 * @return array<int, int>
	 */
	private function allocate_minor_values( int $line_minor, float $line_quantity, array $quantities ): array {
		$requested_quantity  = array_sum( $quantities );
		$attributed_quantity = min( $line_quantity, $requested_quantity );
		$remaining_minor     = (int) round( $line_minor * ( $attributed_quantity / $line_quantity ) );
		$remaining_quantity  = $requested_quantity;
		$remaining_carts     = count( $quantities );
		$allocated_values    = array();

		foreach ( $quantities as $cart_id => $quantity ) {
			$allocated_values[ $cart_id ] = $remaining_carts > 1
				? (int) round( $remaining_minor * ( $quantity / $remaining_quantity ) )
				: $remaining_minor;
			$remaining_minor             -= $allocated_values[ $cart_id ];
			$remaining_quantity          -= $quantity;
			--$remaining_carts;
		}

		return $allocated_values;
	}

	/**
	 * Return the cart tracked against the current session.
	 */
	private function cart_id_for_session(): int {
		$session_key = (string) WC()->session->get_customer_id();
		$cart        = $this->carts->find_by_session( $session_key );

		return null === $cart ? 0 : (int) ( $cart['id'] ?? 0 );
	}
}
