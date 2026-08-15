<?php
/**
 * Aggregate figures for the recovery dashboard.
 *
 * @package SimpleCheckoutFieldsEditor
 */

declare( strict_types=1 );

namespace DesignTich\SimpleCheckoutFieldsEditor\Core\Database;

/**
 * Reads the numbers a merchant judges recovery by.
 *
 * One grouped query per table rather than a count per status, because this
 * runs on an admin screen someone refreshes, and a store with a busy checkout
 * accumulates rows quickly.
 */
final class RecoveryStats {

	/**
	 * Create the reader.
	 *
	 * @param TableNames $tables Table name resolver.
	 */
	public function __construct( private TableNames $tables ) {
	}

	/**
	 * Return the dashboard summary.
	 *
	 * @return array<string, mixed>
	 */
	public function summary(): array {
		$carts     = $this->carts_by_status();
		$abandoned = $carts['abandoned']['count'] ?? 0;
		$recovered = $carts['recovered']['count'] ?? 0;
		$closed    = $abandoned + $recovered;

		return array(
			'carts'         => array(
				'open'      => $carts['open']['count'] ?? 0,
				'abandoned' => $abandoned,
				'recovered' => $recovered,
				'completed' => $carts['completed']['count'] ?? 0,
			),
			'value'         => array(
				// Recovery value uses the captured cart total, not the final order total.
				'abandoned' => $carts['abandoned']['value'] ?? '0.0000',
				'recovered' => $carts['recovered']['value'] ?? '0.0000',
			),
			// Exclude open carts because they have not yet had a chance to recover.
			'recovery_rate' => $closed > 0 ? round( ( $recovered / $closed ) * 100, 1 ) : 0.0,
			'messages'      => $this->messages_by_status(),
			'currency'      => get_woocommerce_currency(),
		);
	}

	/**
	 * Return recent carts for the dashboard table.
	 *
	 * Contact details are included. Seeing who abandoned a checkout, and what
	 * they had entered, is the whole point of the feature: a merchant may want
	 * to call them, or to notice that a bad address is why nothing was ever
	 * delivered. WooCommerce already shows the same details on its orders
	 * screen, and this route requires the same capability.
	 *
	 * @param int $limit Maximum rows.
	 * @return list<array<string, mixed>>
	 */
	public function recent( int $limit = 20 ): array {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Custom table owned by this plugin; prepare() binds the identifier and the limit.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT id, status, email, phone, first_name, last_name, total, recovered_total, currency, item_count, country, checkout_step, consent, created_at, updated_at, recovered_order_id
				FROM %i ORDER BY updated_at DESC LIMIT %d',
				$this->tables->name( TableNames::CARTS ),
				max( 1, min( 100, $limit ) )
			),
			ARRAY_A
		);

		if ( ! is_array( $rows ) ) {
			return array();
		}

		return array_map(
			static function ( array $row ): array {
				return array(
					'id'         => (int) $row['id'],
					'status'     => (string) $row['status'],
					'name'       => trim( $row['first_name'] . ' ' . $row['last_name'] ),
					'email'      => (string) $row['email'],
					'phone'      => (string) $row['phone'],
					'total'      => 'recovered' === (string) $row['status'] ? (string) $row['recovered_total'] : (string) $row['total'],
					'currency'   => (string) $row['currency'],
					'items'      => (int) $row['item_count'],
					'country'    => (string) $row['country'],
					'step'       => (string) $row['checkout_step'],
					'consent'    => (bool) (int) $row['consent'],
					'created_at' => (string) $row['created_at'],
					'updated_at' => (string) $row['updated_at'],
					'order_id'   => (int) $row['recovered_order_id'],
				);
			},
			$rows
		);
	}

	/**
	 * Return cart counts and captured value grouped by status.
	 *
	 * @return array<string, array{count: int, value: string}>
	 */
	private function carts_by_status(): array {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Custom table owned by this plugin; prepare() binds the identifier.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT status, COUNT(*) AS total_count, SUM(CASE WHEN status = %s THEN recovered_total ELSE total END) AS total_value FROM %i GROUP BY status',
				'recovered',
				$this->tables->name( TableNames::CARTS )
			),
			ARRAY_A
		);

		$grouped = array();

		foreach ( is_array( $rows ) ? $rows : array() as $row ) {
			$grouped[ (string) $row['status'] ] = array(
				'count' => (int) $row['total_count'],
				'value' => wc_format_decimal( $row['total_value'], 4 ),
			);
		}

		return $grouped;
	}

	/**
	 * Return message counts grouped by status.
	 *
	 * @return array<string, int>
	 */
	private function messages_by_status(): array {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Custom table owned by this plugin; prepare() binds the identifier.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT status, COUNT(*) AS total_count FROM %i GROUP BY status',
				$this->tables->name( TableNames::MESSAGES )
			),
			ARRAY_A
		);

		$grouped = array(
			'pending'   => 0,
			'sent'      => 0,
			'failed'    => 0,
			'cancelled' => 0,
		);

		foreach ( is_array( $rows ) ? $rows : array() as $row ) {
			$grouped[ (string) $row['status'] ] = (int) $row['total_count'];
		}

		return $grouped;
	}
}
