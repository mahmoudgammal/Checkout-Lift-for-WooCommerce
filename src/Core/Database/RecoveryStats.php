<?php
/**
 * Aggregate figures for the Lite checkout-activity dashboard.
 *
 * @package SimpleCheckoutFieldsEditor
 */

declare( strict_types=1 );

namespace DesignTich\SimpleCheckoutFieldsEditor\Core\Database;

/** Reads Lite checkout activity aggregates. */
final class RecoveryStats {

	/**
	 * Create the aggregate reader.
	 *
	 * @param TableNames $tables Plugin table names.
	 */
	public function __construct( private TableNames $tables ) {
	}

	/**
	 * Return checkout activity totals.
	 *
	 * @return array<string, mixed>
	 */
	public function summary(): array {
		$carts = $this->carts_by_status();

		return array(
			'carts'    => array(
				'open'      => $carts['open']['count'] ?? 0,
				'abandoned' => $carts['abandoned']['count'] ?? 0,
				'completed' => ( $carts['completed']['count'] ?? 0 ) + ( $carts['recovered']['count'] ?? 0 ),
			),
			'value'    => array(
				'abandoned' => $carts['abandoned']['value'] ?? '0.0000',
			),
			'currency' => get_woocommerce_currency(),
		);
	}

	/**
	 * Return recent checkout activity.
	 *
	 * @param int $limit Maximum rows.
	 * @return list<array<string, mixed>>
	 */
	public function recent( int $limit = 20 ): array {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Custom table owned by this plugin; prepare() binds the identifier and limit.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT id, status, email, phone, first_name, last_name, total, currency, item_count, items, country, checkout_step, consent, created_at, updated_at, recovered_order_id
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
				$status = (string) $row['status'];

				return array(
					'id'         => (int) $row['id'],
					'status'     => 'recovered' === $status ? 'completed' : $status,
					'name'       => trim( $row['first_name'] . ' ' . $row['last_name'] ),
					'email'      => (string) $row['email'],
					'phone'      => (string) $row['phone'],
					'total'      => (string) $row['total'],
					'currency'   => (string) $row['currency'],
					'items'      => (int) $row['item_count'],
					'products'   => self::products( (string) $row['items'] ),
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
	 * Summarise the stored cart lines as product names and quantities.
	 *
	 * The name is the one captured with the cart, so a product renamed or
	 * deleted since still shows what the customer actually had.
	 *
	 * @param string $items Stored cart lines, JSON.
	 * @return list<array{name: string, quantity: int}>
	 */
	private static function products( string $items ): array {
		$lines    = json_decode( $items, true );
		$products = array();

		foreach ( is_array( $lines ) ? $lines : array() as $line ) {
			if ( ! is_array( $line ) || ! is_string( $line['name'] ?? null ) || '' === $line['name'] ) {
				continue;
			}

			$products[] = array(
				'name'     => $line['name'],
				'quantity' => max( 1, (int) ( $line['quantity'] ?? 1 ) ),
			);
		}

		return $products;
	}

	/**
	 * Group captured carts by status.
	 *
	 * @return array<string, array{count: int, value: string}>
	 */
	private function carts_by_status(): array {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Custom table owned by this plugin; prepare() binds the identifier.
		$rows    = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT status, COUNT(*) AS total_count, SUM(total) AS total_value FROM %i GROUP BY status',
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
}
