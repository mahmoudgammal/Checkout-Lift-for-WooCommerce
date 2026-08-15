<?php
/**
 * Reads and writes queued recovery messages.
 *
 * @package SimpleCheckoutFieldsEditor
 */

declare( strict_types=1 );

namespace DesignTich\SimpleCheckoutFieldsEditor\Core\Database;

use DesignTich\SimpleCheckoutFieldsEditor\Core\Contracts\MessageStore;

/**
 * The only path to the messages table.
 *
 * Messages belong to a cart, so removing a cart must remove its messages too.
 * Nothing enforces that at the database level, because a foreign key would
 * fail on the MyISAM tables some hosts still default to, so it is enforced
 * here instead.
 */
final class MessageRepository implements MessageStore {

	/**
	 * Create the repository.
	 *
	 * @param TableNames $tables Table name resolver.
	 */
	public function __construct( private TableNames $tables ) {
	}

	/**
	 * Delete every message belonging to the given carts.
	 *
	 * @param array<int, int> $cart_ids Cart identifiers.
	 * @return int Rows removed.
	 */
	public function delete_by_cart_ids( array $cart_ids ): int {
		global $wpdb;

		$ids = array_values( array_filter( array_map( 'intval', $cart_ids ) ) );

		if ( array() === $ids ) {
			return 0;
		}

		$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
		$sql          = 'DELETE FROM %i WHERE cart_id IN (' . $placeholders . ')';
		$arguments    = array_merge( array( $this->table() ), $ids );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Placeholders are generated, not interpolated input; prepare() binds the identifier and every id.
		$deleted = $wpdb->query( $wpdb->prepare( $sql, $arguments ) );

		return is_numeric( $deleted ) ? (int) $deleted : 0;
	}

	/**
	 * Delete messages whose cart no longer exists.
	 *
	 * A safety net for rows orphaned by a direct database edit or an
	 * interrupted sweep, so the table cannot grow without bound.
	 *
	 * @param int $limit Maximum rows to remove in this pass.
	 * @return int Rows removed.
	 */
	public function delete_orphans( int $limit = 500 ): int {
		global $wpdb;

		// NOT EXISTS preserves LIMIT, which MySQL rejects on a multi-table DELETE.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Custom tables owned by this plugin; prepare() binds both identifiers and the limit.
		$deleted = $wpdb->query(
			$wpdb->prepare(
				'DELETE FROM %i WHERE NOT EXISTS (SELECT 1 FROM %i AS c WHERE c.id = cart_id) LIMIT %d',
				$this->table(),
				$this->tables->name( TableNames::CARTS ),
				max( 1, $limit )
			)
		);

		return is_numeric( $deleted ) ? (int) $deleted : 0;
	}

	/**
	 * Queue one message, unless the same one is already queued.
	 *
	 * The uniqueness check is what stops a scanner run, a retry, or an
	 * overlapping cron from queueing the same reminder twice and mailing a
	 * customer repeatedly.
	 *
	 * @param int    $cart_id      Cart the message belongs to.
	 * @param string $channel      Delivery channel.
	 * @param string $scheduled_at When it becomes due, UTC.
	 * @return int Inserted id, or 0 when it already existed or failed.
	 */
	public function schedule( int $cart_id, string $channel, string $scheduled_at ): int {
		global $wpdb;

		if ( $cart_id < 1 ) {
			return 0;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Custom table owned by this plugin; prepare() binds the identifier and every value.
		$existing = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT id FROM %i WHERE cart_id = %d AND channel = %s LIMIT 1',
				$this->table(),
				$cart_id,
				$channel
			)
		);

		if ( null !== $existing ) {
			return 0;
		}

		$now = gmdate( 'Y-m-d H:i:s' );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Custom table owned by this plugin; wpdb binds every value.
		$inserted = $wpdb->insert(
			$this->table(),
			array(
				'cart_id'      => $cart_id,
				'channel'      => $channel,
				'status'       => 'pending',
				'scheduled_at' => $scheduled_at,
				'created_at'   => $now,
			),
			array( '%d', '%s', '%s', '%s', '%s' )
		);

		return $inserted ? (int) $wpdb->insert_id : 0;
	}

	/**
	 * Queue a bounded recovery sequence in one database statement.
	 *
	 * The database uniqueness key makes overlapping scanners idempotent. The
	 * caller supplies at most four internal email channels, so query size does
	 * not grow with cart or catalog size.
	 *
	 * @param int                                          $cart_id Cart the messages belong to.
	 * @param list<array{channel: string, due_at: string}> $messages Recovery steps.
	 * @return int Rows inserted.
	 */
	public function schedule_many( int $cart_id, array $messages ): int {
		global $wpdb;

		if ( $cart_id < 1 || array() === $messages ) {
			return 0;
		}

		$rows      = array();
		$arguments = array( $this->table() );
		$now       = gmdate( 'Y-m-d H:i:s' );

		foreach ( array_slice( $messages, 0, 4 ) as $message ) {
			$channel = (string) ( $message['channel'] ?? '' );

			if ( ! preg_match( '/^email(?:_[1-4])?$/', $channel ) ) {
				continue;
			}

			$rows[]    = '(%d,%s,%s,%s,%s)';
			$arguments = array_merge(
				$arguments,
				array( $cart_id, $channel, 'pending', (string) ( $message['due_at'] ?? '' ), $now )
			);
		}

		if ( array() === $rows ) {
			return 0;
		}

		$sql = 'INSERT IGNORE INTO %i (cart_id,channel,status,scheduled_at,created_at) VALUES ' . implode( ',', $rows );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- The bounded tuple placeholders are generated internally and every value is bound by prepare().
		$inserted = $wpdb->query( $wpdb->prepare( $sql, $arguments ) );

		return is_numeric( $inserted ) ? (int) $inserted : 0;
	}

	/**
	 * Return messages that are due to be sent.
	 *
	 * @param int $limit Maximum rows.
	 * @return list<array<string, mixed>>
	 */
	public function due( int $limit = 25 ): array {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Custom table owned by this plugin; prepare() binds the identifier and every value.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT * FROM %i WHERE status = %s AND scheduled_at <= %s ORDER BY scheduled_at ASC LIMIT %d',
				$this->table(),
				'pending',
				gmdate( 'Y-m-d H:i:s' ),
				max( 1, $limit )
			),
			ARRAY_A
		);

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Atomically lease one due message to the current worker.
	 *
	 * Keeping the status pending with a future scheduled time makes the lease
	 * rollback-safe: an older plugin version also waits for the lease, and a
	 * crashed worker releases itself automatically when the time passes.
	 *
	 * @param int $message_id Message identifier.
	 */
	public function claim( int $message_id ): bool {
		global $wpdb;

		if ( $message_id < 1 ) {
			return false;
		}

		$now         = gmdate( 'Y-m-d H:i:s' );
		$lease_until = gmdate( 'Y-m-d H:i:s', time() + ( 15 * MINUTE_IN_SECONDS ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Custom table owned by this plugin; prepare() binds the identifier and every value.
		$claimed = $wpdb->query(
			$wpdb->prepare(
				'UPDATE %i SET scheduled_at = %s, attempts = attempts + 1 WHERE id = %d AND status = %s AND scheduled_at <= %s',
				$this->table(),
				$lease_until,
				$message_id,
				'pending',
				$now
			)
		);

		return 1 === $claimed;
	}

	/**
	 * Record the outcome of a delivery attempt.
	 *
	 * @param int    $message_id Message identifier.
	 * @param string $status     Resulting status.
	 * @param string $error      Failure detail, if any.
	 */
	public function record_attempt( int $message_id, string $status, string $error = '' ): bool {
		global $wpdb;

		$row = array(
			'status'     => $status,
			'last_error' => mb_substr( $error, 0, 191 ),
		);

		if ( 'sent' === $status ) {
			$row['sent_at'] = gmdate( 'Y-m-d H:i:s' );
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Custom table owned by this plugin; wpdb binds every value.
		return false !== $wpdb->update(
			$this->table(),
			$row,
			array( 'id' => $message_id ),
			array_fill( 0, count( $row ), '%s' ),
			array( '%d' )
		);
	}

	/**
	 * Count messages in a given state.
	 *
	 * @param string $status Message status.
	 */
	public function count_by_status( string $status ): int {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Custom table owned by this plugin; prepare() binds the identifier and the value.
		$count = $wpdb->get_var(
			$wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE status = %s', $this->table(), $status )
		);

		return is_numeric( $count ) ? (int) $count : 0;
	}

	/**
	 * Return the resolved table name.
	 */
	private function table(): string {
		return $this->tables->name( TableNames::MESSAGES );
	}
}
