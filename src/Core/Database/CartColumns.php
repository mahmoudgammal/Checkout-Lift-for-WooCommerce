<?php
/**
 * Writable columns of the carts table.
 *
 * @package SimpleCheckoutFieldsEditor
 */

declare( strict_types=1 );

namespace DesignTich\SimpleCheckoutFieldsEditor\Core\Database;

/**
 * Filters and casts data on its way into the carts table.
 *
 * Every write goes through here so a caller cannot invent a column name or
 * hand `$wpdb` a value of the wrong type. The format list it returns is what
 * lets `$wpdb->insert()` and `$wpdb->update()` bind values instead of
 * interpolating them.
 */
final class CartColumns {

	/**
	 * Writable columns mapped to their wpdb format specifier.
	 *
	 * `id`, `created_at`, and `token_hash` are deliberately absent: they are
	 * set once by the repository and must never be overwritten by a caller.
	 *
	 * @var array<string, string>
	 */
	private const COLUMNS = array(
		'session_key'        => '%s',
		'user_id'            => '%d',
		'email'              => '%s',
		'phone'              => '%s',
		'first_name'         => '%s',
		'last_name'          => '%s',
		'country'            => '%s',
		'currency'           => '%s',
		'total'              => '%s',
		'item_count'         => '%d',
		'items'              => '%s',
		'status'             => '%s',
		'checkout_step'      => '%s',
		'payment_method'     => '%s',
		'shipping_method'    => '%s',
		'recovered_order_id' => '%d',
		'recovered_total'    => '%s',
		'consent'            => '%d',
		'consent_source'     => '%s',
		'consent_at'         => '%s',
		'updated_at'         => '%s',
		'expires_at'         => '%s',
	);

	/**
	 * Statuses a cart may hold.
	 *
	 * @var list<string>
	 */
	public const STATUSES = array( 'open', 'abandoned', 'recovered', 'completed' );

	/**
	 * Statuses of a cart that has not yet ended in an order.
	 *
	 * Only these rows may absorb new checkout activity or be closed by an
	 * order; a finished row keeps its outcome.
	 *
	 * @var list<string>
	 */
	public const UNFINISHED_STATUSES = array( 'open', 'abandoned' );

	/**
	 * Whether a stored row has not yet ended in an order.
	 *
	 * @param array<string, mixed> $row Stored cart row.
	 */
	public static function is_unfinished( array $row ): bool {
		return in_array( (string) ( $row['status'] ?? '' ), self::UNFINISHED_STATUSES, true );
	}

	/**
	 * Reduce arbitrary input to writable columns with correct types.
	 *
	 * Unknown keys are dropped rather than rejected, so a caller passing extra
	 * context does not fail a write. Anything that would corrupt a row, such
	 * as an unknown status, is normalised instead.
	 *
	 * @param array<string, mixed> $data Candidate row data.
	 * @return array<string, mixed> Column values ready for wpdb.
	 */
	public function filter( array $data ): array {
		$row = array();

		foreach ( self::COLUMNS as $column => $format ) {
			if ( ! array_key_exists( $column, $data ) ) {
				continue;
			}

			$row[ $column ] = $this->cast( $column, $data[ $column ], $format );
		}

		return $row;
	}

	/**
	 * Return wpdb format specifiers matching the given row, in key order.
	 *
	 * @param array<string, mixed> $row Filtered row data.
	 * @return list<string>
	 */
	public function formats( array $row ): array {
		$formats = array();

		foreach ( array_keys( $row ) as $column ) {
			$formats[] = self::COLUMNS[ $column ] ?? '%s';
		}

		return $formats;
	}

	/**
	 * Cast one value to the type its column expects.
	 *
	 * @param string $column Column name.
	 * @param mixed  $value  Candidate value.
	 * @param string $format wpdb format specifier.
	 * @return mixed
	 */
	private function cast( string $column, $value, string $format ) {
		if ( 'status' === $column ) {
			return in_array( $value, self::STATUSES, true ) ? $value : 'open';
		}

		if ( 'consent_at' === $column && ( null === $value || '' === $value ) ) {
			return null;
		}

		if ( 'total' === $column || 'recovered_total' === $column ) {
			return is_numeric( $value ) ? wc_format_decimal( $value, 4 ) : '0.0000';
		}

		if ( '%d' === $format ) {
			return is_numeric( $value ) ? (int) $value : 0;
		}

		return is_scalar( $value ) ? (string) $value : '';
	}
}
