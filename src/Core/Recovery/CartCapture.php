<?php
/**
 * Records checkout activity behind the consent gate.
 *
 * @package SimpleCheckoutFieldsEditor
 */

declare( strict_types=1 );

namespace DesignTich\SimpleCheckoutFieldsEditor\Core\Recovery;

use DesignTich\SimpleCheckoutFieldsEditor\Core\Database\CartColumns;
use DesignTich\SimpleCheckoutFieldsEditor\Core\Database\CartRepository;
use DesignTich\SimpleCheckoutFieldsEditor\Core\Privacy\ConsentPolicy;

/**
 * Turns a capture request into a stored cart, or refuses it.
 *
 * Cart contents and totals are read from WooCommerce, never from the request.
 * A browser may say who it is and how far it has got, but it may not state
 * what the order is worth.
 */
final class CartCapture {

	/**
	 * Maximum nested levels retained from extension-owned cart configuration.
	 */
	private const ITEM_DATA_DEPTH = 8;

	/**
	 * Maximum entries retained at each array level.
	 */
	private const ITEM_DATA_ENTRIES = 100;

	/**
	 * Maximum encoded bytes retained for one cart item's custom data.
	 */
	private const ITEM_DATA_BYTES = 16384;

	/**
	 * Create the capture service.
	 *
	 * @param CartRepository   $carts    Cart storage.
	 * @param ConsentPolicy    $consent  Consent gate.
	 * @param CapturedCheckout $captured Payload sanitizer.
	 * @param ResumeToken      $tokens   Resume token factory.
	 */
	public function __construct(
		private CartRepository $carts,
		private ConsentPolicy $consent,
		private CapturedCheckout $captured,
		private ResumeToken $tokens
	) {
	}

	/**
	 * Record checkout activity for the current session.
	 *
	 * @param array<string, mixed> $payload       Request payload.
	 * @param string               $session_key   Checkout session identifier.
	 * @param bool                 $consent_given Whether the customer agreed.
	 * @return array{stored: bool, reason: string, cart_id: int}
	 */
	public function capture( array $payload, string $session_key, bool $consent_given ): array {
		if ( ! $this->consent->may_store( $consent_given ) ) {
			return $this->refused( 'consent' );
		}

		if ( '' === $session_key ) {
			return $this->refused( 'no_session' );
		}

		$fields = $this->captured->customer_fields( $payload );

		if ( ! $this->captured->is_contactable( $fields ) ) {
			// Avoid retaining personal data when no recoverable contact exists.
			return $this->refused( 'not_contactable' );
		}

		$totals = $this->cart_totals();

		if ( 1 > (int) ( $totals['item_count'] ?? 0 ) ) {
			// Empty carts are unrecoverable and would permit cheap session-based row flooding.
			return $this->refused( 'empty_cart' );
		}

		$row = array_merge(
			$fields,
			$totals,
			$this->consent->record( $consent_given ),
			array(
				'session_key' => $session_key,
				'user_id'     => get_current_user_id(),
				'status'      => 'open',
			)
		);

		$existing = $this->carts->find_by_session( $session_key );

		// A finished row keeps its outcome; logged-in sessions reuse the user ID
		// as the session key, so the next checkout must start a new row.
		if ( null !== $existing && CartColumns::is_unfinished( $existing ) ) {
			$updated = $this->carts->update( (int) $existing['id'], $row );

			return array(
				'stored'  => $updated,
				'reason'  => $updated ? 'updated' : 'write_failed',
				'cart_id' => (int) $existing['id'],
			);
		}

		$token   = $this->tokens->create();
		$cart_id = $this->carts->insert( $row, $this->tokens->hash( $token ) );

		return array(
			'stored'  => $cart_id > 0,
			'reason'  => $cart_id > 0 ? 'created' : 'write_failed',
			'cart_id' => $cart_id,
		);
	}

	/**
	 * Read cart contents from WooCommerce.
	 *
	 * Deliberately server-side. Trusting a browser for the total would let
	 * anyone fabricate the revenue figures the dashboard reports.
	 *
	 * @return array<string, mixed>
	 */
	private function cart_totals(): array {
		if ( ! function_exists( 'WC' ) || null === WC()->cart ) {
			return array();
		}

		$cart  = WC()->cart;
		$items = array();

		foreach ( $cart->get_cart() as $item ) {
			$product = $item['data'] ?? null;

			$items[] = array(
				'product_id'     => (int) ( $item['product_id'] ?? 0 ),
				'variation_id'   => (int) ( $item['variation_id'] ?? 0 ),
				'variation'      => $this->json_safe_array( $item['variation'] ?? array() ),
				'quantity'       => is_numeric( $item['quantity'] ?? null ) ? (int) $item['quantity'] : 0,
				'cart_item_data' => $this->cart_item_data( $item ),
				'name'           => $product && method_exists( $product, 'get_name' ) ? $product->get_name() : '',
			);
		}

		return array(
			'currency'   => get_woocommerce_currency(),
			'total'      => wc_format_decimal( $cart->get_total( 'edit' ), 4 ),
			'item_count' => (int) $cart->get_cart_contents_count(),
			'items'      => (string) wp_json_encode( $items ),
		);
	}

	/**
	 * Preserve extension-owned cart configuration without runtime totals or objects.
	 *
	 * Recovery data already sits behind consent and retention. Keeping the
	 * scalar/array configuration here lets WooCommerce rebuild the same cart
	 * identity while excluding product objects and totals that must be
	 * recalculated against the current catalogue.
	 *
	 * @param array<string, mixed> $item WooCommerce cart item.
	 * @return array<string, mixed>
	 */
	private function cart_item_data( array $item ): array {
		foreach ( array( 'key', 'product_id', 'variation_id', 'variation', 'quantity', 'data', 'data_hash', 'line_tax_data', 'line_subtotal', 'line_subtotal_tax', 'line_total', 'line_tax' ) as $runtime_key ) {
			unset( $item[ $runtime_key ] );
		}

		$safe    = $this->json_safe_array( $item );
		$encoded = wp_json_encode( $safe );

		return is_string( $encoded ) && strlen( $encoded ) <= self::ITEM_DATA_BYTES ? $safe : array();
	}

	/**
	 * Keep only JSON-safe scalar arrays from server-side cart state.
	 *
	 * @param mixed $value Candidate value.
	 * @param int   $depth Current nesting depth.
	 * @return array<string|int, mixed>
	 */
	private function json_safe_array( $value, int $depth = 0 ): array {
		if ( ! is_array( $value ) || $depth >= self::ITEM_DATA_DEPTH ) {
			return array();
		}

		$safe = array();

		foreach ( array_slice( $value, 0, self::ITEM_DATA_ENTRIES, true ) as $key => $entry ) {
			if ( is_scalar( $entry ) || null === $entry ) {
				$safe[ $key ] = is_string( $entry ) ? mb_substr( $entry, 0, 4096 ) : $entry;
			} elseif ( is_array( $entry ) ) {
				$safe[ $key ] = $this->json_safe_array( $entry, $depth + 1 );
			}
		}

		return $safe;
	}

	/**
	 * Build a refusal result.
	 *
	 * @param string $reason Machine-readable reason.
	 * @return array{stored: bool, reason: string, cart_id: int}
	 */
	private function refused( string $reason ): array {
		return array(
			'stored'  => false,
			'reason'  => $reason,
			'cart_id' => 0,
		);
	}
}
