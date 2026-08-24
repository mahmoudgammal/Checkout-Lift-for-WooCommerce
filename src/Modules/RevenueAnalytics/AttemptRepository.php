<?php
/**
 * Checkout-attempt persistence.
 *
 * @package SimpleCheckoutFieldsEditor
 */

declare( strict_types=1 );

namespace DesignTich\SimpleCheckoutFieldsEditor\Modules\RevenueAnalytics;

use DesignTich\SimpleCheckoutFieldsEditor\Core\Database\TableNames;

/**
 * Stores privacy-minimized checkout attempts.
 */
final class AttemptRepository {

	/**
	 * Create the repository.
	 *
	 * @param TableNames $tables Table resolver.
	 */
	public function __construct( private TableNames $tables ) {
	}

	/**
	 * Start an attempt idempotently.
	 *
	 * @param string $token_hash Hashed attempt token.
	 * @param string $runtime    Checkout runtime.
	 * @param string $currency   Order currency.
	 */
	public function start( string $token_hash, string $runtime, string $currency ): int {
		global $wpdb;

		if ( ! preg_match( '/^[a-f0-9]{64}$/', $token_hash ) ) {
			return 0;
		}

		$now   = gmdate( 'Y-m-d H:i:s' );
		$table = $this->tables->name( TableNames::ATTEMPTS );
		$sql   = 'INSERT INTO %i (token_hash,status,runtime,currency,first_seen_at,last_seen_at,created_at,updated_at) VALUES (%s,%s,%s,%s,%s,%s,%s,%s) ON DUPLICATE KEY UPDATE last_seen_at=VALUES(last_seen_at), updated_at=VALUES(updated_at)';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Module-owned indexed fact table; identifier and values are bound.
		$wpdb->query( $wpdb->prepare( $sql, $table, $token_hash, 'open', $runtime, $currency, $now, $now, $now, $now ) );

		return $this->id_for_hash( $token_hash );
	}

	/**
	 * Record meaningful activity for an open attempt.
	 *
	 * @param string $token_hash Hashed attempt token.
	 */
	public function touch( string $token_hash ): void {
		global $wpdb;

		if ( ! preg_match( '/^[a-f0-9]{64}$/', $token_hash ) ) {
			return;
		}

		$table = $this->tables->name( TableNames::ATTEMPTS );
		$now   = gmdate( 'Y-m-d H:i:s' );
		$sql   = 'UPDATE %i SET last_seen_at=%s, updated_at=%s WHERE token_hash=%s AND status=%s';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Module-owned indexed fact table; identifier and values are bound.
		$wpdb->query( $wpdb->prepare( $sql, $table, $now, $now, $token_hash, 'open' ) );
	}

	/**
	 * Link an attempt to its placed order.
	 *
	 * @param string $token_hash Hashed attempt token.
	 * @param int    $order_id   WooCommerce order ID.
	 * @param array  $facts      Order facts.
	 * @phpstan-param array{total:string,currency:string} $facts
	 */
	public function place( string $token_hash, int $order_id, array $facts ): int {
		global $wpdb;

		$attempt_id = $this->id_for_hash( $token_hash );

		if ( $attempt_id < 1 || $order_id < 1 ) {
			return 0;
		}

		$table = $this->tables->name( TableNames::ATTEMPTS );
		$now   = gmdate( 'Y-m-d H:i:s' );
		$sql   = 'UPDATE %i SET status=%s,order_id=%d,order_total=%s,currency=%s,placed_at=COALESCE(placed_at,%s),last_seen_at=%s,updated_at=%s WHERE id=%d';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Module-owned indexed fact table; identifier and values are bound.
		$wpdb->query( $wpdb->prepare( $sql, $table, 'placed', $order_id, $facts['total'], $facts['currency'], $now, $now, $now, $attempt_id ) );

		return $attempt_id;
	}

	/**
	 * Find an attempt without exposing its ID to the browser.
	 *
	 * @param string $token_hash Hashed attempt token.
	 */
	public function id_for_hash( string $token_hash ): int {
		global $wpdb;

		if ( ! preg_match( '/^[a-f0-9]{64}$/', $token_hash ) ) {
			return 0;
		}

		$table = $this->tables->name( TableNames::ATTEMPTS );
		$sql   = 'SELECT id FROM %i WHERE token_hash=%s LIMIT 1';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Indexed lookup in a module-owned table; identifier and value are bound.
		return (int) $wpdb->get_var( $wpdb->prepare( $sql, $table, $token_hash ) );
	}

	/**
	 * Classify a bounded batch of inactive attempts.
	 *
	 * @param string $cutoff UTC inactivity cutoff.
	 * @param int    $limit  Maximum rows.
	 */
	public function abandon_before( string $cutoff, int $limit = 100 ): int {
		global $wpdb;

		$table = $this->tables->name( TableNames::ATTEMPTS );
		$now   = gmdate( 'Y-m-d H:i:s' );
		$sql   = 'UPDATE %i SET status=%s,abandoned_at=COALESCE(abandoned_at,%s),updated_at=%s WHERE status=%s AND order_id=0 AND last_seen_at<%s ORDER BY last_seen_at ASC LIMIT %d';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Bounded indexed lifecycle transition; identifier and values are bound.
		$result = $wpdb->query( $wpdb->prepare( $sql, $table, 'abandoned', $now, $now, 'open', $cutoff, max( 1, min( 500, $limit ) ) ) );

		return false === $result ? 0 : (int) $result;
	}

	/**
	 * Find a bounded batch past the approved retention period.
	 *
	 * @param string $cutoff UTC retention cutoff.
	 * @param int    $limit  Maximum rows.
	 * @return list<int>
	 */
	public function expired_ids( string $cutoff, int $limit = 100 ): array {
		global $wpdb;

		$table = $this->tables->name( TableNames::ATTEMPTS );
		$sql   = 'SELECT id FROM %i WHERE first_seen_at<%s ORDER BY id ASC LIMIT %d';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Bounded indexed retention scan; identifier and values are bound.
		$ids = $wpdb->get_col( $wpdb->prepare( $sql, $table, $cutoff, max( 1, min( 500, $limit ) ) ) );

		return array_values( array_filter( array_map( 'absint', is_array( $ids ) ? $ids : array() ) ) );
	}

	/**
	 * Delete an explicit set of expired attempts.
	 *
	 * @param array $ids Attempt IDs.
	 * @phpstan-param list<int> $ids
	 */
	public function delete_ids( array $ids ): void {
		global $wpdb;

		$ids = array_values( array_filter( array_map( 'absint', $ids ) ) );

		if ( array() === $ids ) {
			return;
		}

		$table        = $this->tables->name( TableNames::ATTEMPTS );
		$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
		$sql          = "DELETE FROM %i WHERE id IN ({$placeholders})";

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared -- Explicit bounded ID list; identifier and IDs are bound.
		$wpdb->query( $wpdb->prepare( $sql, $table, ...$ids ) );
	}
}
