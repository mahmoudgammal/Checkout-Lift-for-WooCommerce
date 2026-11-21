<?php
/**
 * Reads and writes stored checkout activity.
 *
 * @package SimpleCheckoutFieldsEditor
 */

declare( strict_types=1 );

namespace DesignTich\SimpleCheckoutFieldsEditor\Core\Database;

use DesignTich\SimpleCheckoutFieldsEditor\Core\Contracts\CartStore;
use DesignTich\SimpleCheckoutFieldsEditor\Core\Privacy\PrivacySettings;

/**
 * The only path to the carts table.
 *
 * Values are bound through wpdb's format specifiers rather than interpolated,
 * and the table name is the one fixed identifier, resolved from the site
 * prefix. Retention is applied on write, so every row carries its own expiry
 * from the moment it exists rather than depending on a later backfill.
 */
final class CartRepository implements CartStore {

	/**
	 * Create the repository.
	 *
	 * @param TableNames      $tables  Table name resolver.
	 * @param CartColumns     $columns Column filter and formats.
	 * @param PrivacySettings $privacy Retention settings.
	 */
	public function __construct(
		private TableNames $tables,
		private CartColumns $columns,
		private PrivacySettings $privacy
	) {
	}

	/**
	 * Insert a cart and return its id.
	 *
	 * @param array<string, mixed> $data       Row data.
	 * @param string               $token_hash Hashed resume token.
	 * @return int Inserted id, or 0 on failure.
	 */
	public function insert( array $data, string $token_hash ): int {
		global $wpdb;

		$now = $this->now();
		$row = $this->columns->filter( $data );

		$row['token_hash'] = $token_hash;
		$row['created_at'] = $now;
		$row['updated_at'] = $row['updated_at'] ?? $now;
		$row['expires_at'] = $row['expires_at'] ?? $this->expiry_from( $now );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Custom table owned by this plugin; wpdb binds every value.
		$inserted = $wpdb->insert( $this->table(), $row, $this->columns->formats( $row ) );

		return $inserted ? (int) $wpdb->insert_id : 0;
	}

	/**
	 * Update one cart by id.
	 *
	 * @param int                  $cart_id Cart id.
	 * @param array<string, mixed> $data    Row data.
	 * @return bool Whether the write was accepted.
	 */
	public function update( int $cart_id, array $data ): bool {
		global $wpdb;

		$row = $this->columns->filter( $data );

		if ( array() === $row ) {
			return false;
		}

		$row['updated_at'] = $row['updated_at'] ?? $this->now();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Custom table owned by this plugin; wpdb binds every value.
		return false !== $wpdb->update(
			$this->table(),
			$row,
			array( 'id' => $cart_id ),
			$this->columns->formats( $row ),
			array( '%d' )
		);
	}

	/**
	 * Replace a cart's resume token.
	 *
	 * Deliberately a named operation rather than a writable column. Only the
	 * hash is ever stored, so a recovery link cannot be rebuilt later from the
	 * database; a fresh token is minted when a message is sent and the raw
	 * value exists only inside that message. Rotating also retires the link in
	 * any earlier email, so the most recent one is the only one that works.
	 *
	 * @param int    $cart_id    Cart identifier.
	 * @param string $token_hash Hashed resume token.
	 */
	public function rotate_token( int $cart_id, string $token_hash ): bool {
		global $wpdb;

		if ( '' === $token_hash ) {
			return false;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Custom table owned by this plugin; wpdb binds every value.
		return false !== $wpdb->update(
			$this->table(),
			array(
				'token_hash' => $token_hash,
				'updated_at' => $this->now(),
			),
			array( 'id' => $cart_id ),
			array( '%s', '%s' ),
			array( '%d' )
		);
	}

	/**
	 * Return carts that have gone quiet and should be treated as abandoned.
	 *
	 * @param int $idle_minutes Minutes of inactivity before a cart counts.
	 * @param int $limit        Maximum rows.
	 * @return list<array<string, mixed>>
	 */
	public function find_newly_abandoned( int $idle_minutes, int $limit = 100 ): array {
		global $wpdb;

		$cutoff = gmdate( 'Y-m-d H:i:s', time() - ( max( 1, $idle_minutes ) * MINUTE_IN_SECONDS ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Custom table owned by this plugin; prepare() binds the identifier and every value.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT * FROM %i WHERE status = %s AND updated_at <= %s ORDER BY updated_at ASC LIMIT %d',
				$this->table(),
				'open',
				$cutoff,
				max( 1, $limit )
			),
			ARRAY_A
		);

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Return one cart by its hashed resume token.
	 *
	 * @param string $token_hash Hashed resume token.
	 * @return array<string, mixed>|null
	 */
	public function find_by_token_hash( string $token_hash ): ?array {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Custom table owned by this plugin; prepare() binds the identifier and every value.
		$row = $wpdb->get_row(
			$wpdb->prepare( 'SELECT * FROM %i WHERE token_hash = %s LIMIT 1', $this->table(), $token_hash ),
			ARRAY_A
		);

		return is_array( $row ) ? $row : null;
	}

	/**
	 * Return one cart by its identifier.
	 *
	 * @param int $cart_id Cart identifier.
	 * @return array<string, mixed>|null
	 */
	public function find_by_id( int $cart_id ): ?array {
		global $wpdb;

		if ( $cart_id < 1 ) {
			return null;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Custom table owned by this plugin; prepare() binds the identifier and the value.
		$row = $wpdb->get_row(
			$wpdb->prepare( 'SELECT * FROM %i WHERE id = %d LIMIT 1', $this->table(), $cart_id ),
			ARRAY_A
		);

		return is_array( $row ) ? $row : null;
	}

	/**
	 * Return one cart by the checkout session it belongs to.
	 *
	 * @param string $session_key WooCommerce session key.
	 * @return array<string, mixed>|null
	 */
	public function find_by_session( string $session_key ): ?array {
		global $wpdb;

		if ( '' === $session_key ) {
			return null;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Custom table owned by this plugin; prepare() binds the identifier and every value.
		$row = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM %i WHERE session_key = %s ORDER BY id DESC LIMIT 1',
				$this->table(),
				$session_key
			),
			ARRAY_A
		);

		return is_array( $row ) ? $row : null;
	}

	/**
	 * Return every cart recorded against one email address.
	 *
	 * Used by the WordPress personal-data exporter, which works by email.
	 *
	 * @param string $email  Customer email.
	 * @param int    $limit  Maximum rows.
	 * @param int    $offset Rows to skip.
	 * @return list<array<string, mixed>>
	 */
	public function find_by_email( string $email, int $limit = 50, int $offset = 0 ): array {
		global $wpdb;

		if ( '' === $email ) {
			return array();
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Custom table owned by this plugin; prepare() binds the identifier and every value.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT * FROM %i WHERE email = %s ORDER BY id ASC LIMIT %d OFFSET %d',
				$this->table(),
				$email,
				max( 1, $limit ),
				max( 0, $offset )
			),
			ARRAY_A
		);

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Delete one cart.
	 *
	 * @param int $cart_id Cart identifier.
	 * @return bool Whether a row was removed.
	 */
	public function delete( int $cart_id ): bool {
		global $wpdb;

		if ( $cart_id < 1 ) {
			return false;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Custom table owned by this plugin; wpdb binds the value.
		$deleted = $wpdb->delete( $this->table(), array( 'id' => $cart_id ), array( '%d' ) );

		return is_numeric( $deleted ) && (int) $deleted > 0;
	}

	/**
	 * Delete every cart recorded against one email address.
	 *
	 * Used by the WordPress personal-data eraser.
	 *
	 * @param string $email Customer email.
	 * @return int Rows removed.
	 */
	public function delete_by_email( string $email ): int {
		global $wpdb;

		if ( '' === $email ) {
			return 0;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Custom table owned by this plugin; wpdb binds the value.
		$deleted = $wpdb->delete( $this->table(), array( 'email' => $email ), array( '%s' ) );

		return is_numeric( $deleted ) ? (int) $deleted : 0;
	}

	/**
	 * Delete carts whose retention window has passed.
	 *
	 * Bounded by a limit so a large backlog is cleared over several runs
	 * instead of one long-running query.
	 *
	 * @param int $limit Maximum rows to remove in this pass.
	 * @return int Rows removed.
	 */
	public function delete_expired( int $limit = 200 ): int {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Custom table owned by this plugin; prepare() binds the identifier and every value.
		$deleted = $wpdb->query(
			$wpdb->prepare(
				'DELETE FROM %i WHERE expires_at <= %s ORDER BY expires_at ASC LIMIT %d',
				$this->table(),
				$this->now(),
				max( 1, $limit )
			)
		);

		return is_numeric( $deleted ) ? (int) $deleted : 0;
	}

	/**
	 * Count carts by status.
	 *
	 * @param string $status Cart status.
	 */
	public function count_by_status( string $status ): int {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Custom table owned by this plugin; prepare() binds the identifier and every value.
		$count = $wpdb->get_var(
			$wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE status = %s', $this->table(), $status )
		);

		return is_numeric( $count ) ? (int) $count : 0;
	}

	/**
	 * Return the expiry timestamp for a row created at the given time.
	 *
	 * @param string $created_at MySQL datetime in UTC.
	 */
	public function expiry_from( string $created_at ): string {
		// Parse stored UTC values as UTC to avoid shifting retention deadlines.
		$utc = new \DateTimeZone( 'UTC' );

		try {
			$created = new \DateTimeImmutable( $created_at, $utc );
		} catch ( \Exception $e ) {
			$created = new \DateTimeImmutable( 'now', $utc );
		}

		$expires = $created->getTimestamp() + ( $this->privacy->retention_days() * DAY_IN_SECONDS );

		return gmdate( 'Y-m-d H:i:s', $expires );
	}

	/**
	 * Return the resolved table name.
	 */
	private function table(): string {
		return $this->tables->name( TableNames::CARTS );
	}

	/**
	 * Return the current UTC time as a MySQL datetime.
	 */
	private function now(): string {
		return gmdate( 'Y-m-d H:i:s' );
	}
}
