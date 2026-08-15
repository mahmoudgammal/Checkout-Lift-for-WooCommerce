<?php
/**
 * Revenue-attribution persistence.
 *
 * @package SimpleCheckoutFieldsEditor
 */

declare( strict_types=1 );

namespace DesignTich\SimpleCheckoutFieldsEditor\Modules\RevenueAnalytics;

use DesignTich\SimpleCheckoutFieldsEditor\Core\Database\TableNames;

/**
 * Stores idempotent, server-confirmed attribution facts.
 */
final class AttributionRepository {

	private const SOURCES = array( 'recovery' );

	/**
	 * Create the repository.
	 *
	 * @param TableNames $tables Table resolver.
	 */
	public function __construct( private TableNames $tables ) {
	}

	/**
	 * Insert or replace one logical attribution.
	 *
	 * @param int    $attempt_id Attempt ID.
	 * @param int    $order_id   WooCommerce order ID.
	 * @param string $source     Allowlisted source.
	 * @param string $source_key Opaque source key.
	 * @param string $currency   Order currency.
	 * @param string $gross      Gross attributed amount.
	 */
	public function upsert( int $attempt_id, int $order_id, string $source, string $source_key, string $currency, string $gross ): void {
		global $wpdb;

		if ( $order_id < 1 || ! in_array( $source, self::SOURCES, true ) ) {
			return;
		}

		$table = $this->tables->name( TableNames::ATTRIBUTIONS );
		$now   = gmdate( 'Y-m-d H:i:s' );
		$sql   = 'INSERT INTO %i (attempt_id,order_id,source_type,source_key,currency,gross_amount,created_at,updated_at) VALUES (%d,%d,%s,%s,%s,%s,%s,%s) ON DUPLICATE KEY UPDATE attempt_id=VALUES(attempt_id),currency=VALUES(currency),gross_amount=VALUES(gross_amount),updated_at=VALUES(updated_at)';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Idempotent module-owned fact write; identifier and values are bound.
		$wpdb->query( $wpdb->prepare( $sql, $table, max( 0, $attempt_id ), $order_id, $source, substr( sanitize_key( $source_key ), 0, 64 ), strtoupper( substr( $currency, 0, 3 ) ), $gross, $now, $now ) );
	}

	/**
	 * Replace recovered refund amounts for an order.
	 *
	 * @param int   $order_id WooCommerce order ID.
	 * @param array $amounts  Refunded values keyed by recovery cart ID.
	 * @phpstan-param array<int, string> $amounts
	 */
	public function reconcile_refunds( int $order_id, array $amounts ): void {
		global $wpdb;

		$table = $this->tables->name( TableNames::ATTRIBUTIONS );
		$clear = 'UPDATE %i SET refunded_amount=0.0000,updated_at=%s WHERE order_id=%d AND source_type=%s';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Bounded order reconciliation; identifier and values are bound.
		$wpdb->query( $wpdb->prepare( $clear, $table, gmdate( 'Y-m-d H:i:s' ), $order_id, 'recovery' ) );

		foreach ( $amounts as $cart_id => $amount ) {
			$sql = 'UPDATE %i SET refunded_amount=LEAST(gross_amount,%s),updated_at=%s WHERE order_id=%d AND source_type=%s AND source_key=%s';

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Logical key lookup; identifier and values are bound.
			$wpdb->query( $wpdb->prepare( $sql, $table, $amount, gmdate( 'Y-m-d H:i:s' ), $order_id, 'recovery', 'cart-' . (int) $cart_id ) );
		}
	}

	/**
	 * Delete attribution facts belonging to expired attempts.
	 *
	 * @param array $attempt_ids Attempt IDs.
	 * @phpstan-param list<int> $attempt_ids
	 */
	public function delete_attempts( array $attempt_ids ): void {
		global $wpdb;

		$attempt_ids = array_values( array_filter( array_map( 'absint', $attempt_ids ) ) );

		if ( array() === $attempt_ids ) {
			return;
		}

		$table        = $this->tables->name( TableNames::ATTRIBUTIONS );
		$placeholders = implode( ',', array_fill( 0, count( $attempt_ids ), '%d' ) );
		$sql          = "DELETE FROM %i WHERE attempt_id IN ({$placeholders})";

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared -- Explicit bounded ID list; identifier and IDs are bound.
		$wpdb->query( $wpdb->prepare( $sql, $table, ...$attempt_ids ) );
	}
}
