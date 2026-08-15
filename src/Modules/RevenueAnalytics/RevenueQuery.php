<?php
/**
 * Bounded revenue overview queries.
 *
 * @package SimpleCheckoutFieldsEditor
 */

declare( strict_types=1 );

namespace DesignTich\SimpleCheckoutFieldsEditor\Modules\RevenueAnalytics;

use DesignTich\SimpleCheckoutFieldsEditor\Core\Database\TableNames;

/**
 * Reads bounded aggregate facts without loading WooCommerce orders.
 */
final class RevenueQuery {

	/**
	 * Create the query service.
	 *
	 * @param TableNames $tables Table resolver.
	 */
	public function __construct( private TableNames $tables ) {
	}

	/**
	 * Return lifecycle and revenue totals.
	 *
	 * @param array $filters Query filters.
	 * @phpstan-param array{from:string,to:string,currency:string,runtime:string,status:string,timezone:string} $filters
	 * @return array<string, mixed>
	 */
	public function overview( array $filters ): array {
		global $wpdb;

		$table              = $this->tables->name( TableNames::ATTEMPTS );
		[ $where, $values ] = $this->attempt_where( $filters );
		$sql                = "SELECT COUNT(*) AS attempts,SUM(status='open') AS open_count,SUM(status='abandoned') AS abandoned_count,SUM(status='placed') AS placed_count,COALESCE(SUM(CASE WHEN status='abandoned' THEN order_total ELSE 0 END),0) AS abandoned_value,COALESCE(SUM(order_total),0) AS order_value,COALESCE(SUM(recognized_total),0) AS recognized_revenue,COALESCE(SUM(refunded_total),0) AS refunded_value FROM %i WHERE {$where}";

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Bounded aggregate on indexed module-owned facts; fragments are internal allowlists and values are bound.
		$row = $wpdb->get_row( $wpdb->prepare( $sql, $table, ...$values ), ARRAY_A );

		return $this->normalize_totals( is_array( $row ) ? $row : array() );
	}

	/**
	 * Return daily trend rows.
	 *
	 * @param array $filters Query filters.
	 * @phpstan-param array{from:string,to:string,currency:string,runtime:string,status:string,timezone:string} $filters
	 * @return list<array<string, mixed>>
	 */
	public function series( array $filters ): array {
		global $wpdb;

		$table              = $this->tables->name( TableNames::ATTEMPTS );
		[ $where, $values ] = $this->attempt_where( $filters );
		$sql                = "SELECT DATE_FORMAT(first_seen_at,'%%Y-%%m-%%d %%H:00:00') AS utc_hour,COUNT(*) AS attempts,SUM(status='placed') AS placed,COALESCE(SUM(order_total),0) AS order_value,COALESCE(SUM(recognized_total),0) AS recognized FROM %i WHERE {$where} GROUP BY utc_hour ORDER BY utc_hour ASC";
		$args               = array_merge( array( $table ), $values );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- At most 2,160 hourly aggregates; fragments are internal allowlists and values are bound.
		$rows = $wpdb->get_results( $wpdb->prepare( $sql, ...$args ), ARRAY_A );

		return $this->daily_series( is_array( $rows ) ? $rows : array(), $filters['timezone'] );
	}

	/**
	 * Rebucket bounded UTC-hour aggregates in the merchant timezone.
	 *
	 * @param list<array<string, mixed>> $rows     Hourly rows.
	 * @param string                     $timezone WordPress timezone name.
	 * @return list<array<string, mixed>>
	 */
	private function daily_series( array $rows, string $timezone ): array {
		$zone = new \DateTimeZone( $timezone );
		$days = array();

		foreach ( $rows as $row ) {
			$hour = \DateTimeImmutable::createFromFormat( 'Y-m-d H:i:s', (string) ( $row['utc_hour'] ?? '' ), new \DateTimeZone( 'UTC' ) );
			if ( false === $hour ) {
				continue;
			}

			$day = $hour->setTimezone( $zone )->format( 'Y-m-d' );
			if ( ! isset( $days[ $day ] ) ) {
				$days[ $day ] = array(
					'day'         => $day,
					'attempts'    => 0,
					'placed'      => 0,
					'order_value' => 0.0,
					'recognized'  => 0.0,
				);
			}

			$days[ $day ]['attempts']    += (int) ( $row['attempts'] ?? 0 );
			$days[ $day ]['placed']      += (int) ( $row['placed'] ?? 0 );
			$days[ $day ]['order_value'] += (float) ( $row['order_value'] ?? 0 );
			$days[ $day ]['recognized']  += (float) ( $row['recognized'] ?? 0 );
		}

		foreach ( $days as &$day ) {
			$day['order_value'] = self::decimal( $day['order_value'] );
			$day['recognized']  = self::decimal( $day['recognized'] );
		}
		unset( $day );

		return array_values( $days );
	}

	/**
	 * Return recovery attribution totals.
	 *
	 * @param array $filters Query filters.
	 * @phpstan-param array{from:string,to:string,currency:string,runtime:string,status:string,timezone:string} $filters
	 * @return array{orders:int,gross:string,refunded:string,net:string}
	 */
	public function recovery( array $filters ): array {
		global $wpdb;

		$attempts           = $this->tables->name( TableNames::ATTEMPTS );
		$attributions       = $this->tables->name( TableNames::ATTRIBUTIONS );
		[ $where, $values ] = $this->attempt_where( $filters, 'attempt' );
		$sql                = "SELECT COUNT(DISTINCT attribution.order_id) AS orders,COALESCE(SUM(attribution.gross_amount),0) AS gross,COALESCE(SUM(attribution.refunded_amount),0) AS refunded FROM %i AS attribution INNER JOIN %i AS attempt ON attempt.id=attribution.attempt_id WHERE attribution.source_type=%s AND {$where}";
		$args               = array_merge( array( $attributions, $attempts, 'recovery' ), $values );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Bounded aggregate over indexed fact tables; fragments are internal allowlists and values are bound.
		$row      = $wpdb->get_row( $wpdb->prepare( $sql, ...$args ), ARRAY_A );
		$row      = is_array( $row ) ? $row : array();
		$gross    = self::decimal( $row['gross'] ?? 0 );
		$refunded = self::decimal( $row['refunded'] ?? 0 );

		return array(
			'orders'   => (int) ( $row['orders'] ?? 0 ),
			'gross'    => $gross,
			'refunded' => $refunded,
			'net'      => self::decimal( max( 0, (float) $gross - (float) $refunded ) ),
		);
	}

	/**
	 * Return observed currencies.
	 *
	 * @return list<string>
	 */
	public function currencies(): array {
		global $wpdb;

		$table = $this->tables->name( TableNames::ATTEMPTS );
		$sql   = "SELECT DISTINCT currency FROM %i WHERE currency<>'' ORDER BY currency ASC LIMIT 100";

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Bounded observed-value lookup; identifier is bound.
		$values = $wpdb->get_col( $wpdb->prepare( $sql, $table ) );

		return array_values( array_filter( array_map( 'strval', is_array( $values ) ? $values : array() ) ) );
	}

	/**
	 * Build the allowlisted attempt predicate.
	 *
	 * @param array  $filters Query filters.
	 * @param string $alias   Optional SQL alias.
	 * @phpstan-param array{from:string,to:string,currency:string,runtime:string,status:string,timezone:string} $filters
	 * @return array{0:string,1:list<mixed>}
	 */
	private function attempt_where( array $filters, string $alias = '' ): array {
		$prefix = '' === $alias ? '' : $alias . '.';
		$parts  = array( "{$prefix}first_seen_at>=%s", "{$prefix}first_seen_at<%s", "{$prefix}currency=%s" );
		$values = array( $filters['from'], $filters['to'], $filters['currency'] );

		if ( 'all' !== $filters['runtime'] ) {
			$parts[]  = "{$prefix}runtime=%s";
			$values[] = $filters['runtime'];
		}

		if ( 'all' !== $filters['status'] ) {
			$parts[]  = "{$prefix}status=%s";
			$values[] = $filters['status'];
		}

		return array( implode( ' AND ', $parts ), $values );
	}

	/**
	 * Normalize database aggregate types for REST.
	 *
	 * @param array<string, mixed> $row Aggregate row.
	 * @return array<string, mixed>
	 */
	private function normalize_totals( array $row ): array {
		$attempts  = (int) ( $row['attempts'] ?? 0 );
		$abandoned = (int) ( $row['abandoned_count'] ?? 0 );
		$placed    = (int) ( $row['placed_count'] ?? 0 );
		$closed    = $abandoned + $placed;

		return array(
			'attempts'           => $attempts,
			'open'               => (int) ( $row['open_count'] ?? 0 ),
			'abandoned'          => $abandoned,
			'placed'             => $placed,
			'placement_rate'     => $closed > 0 ? round( ( $placed / $closed ) * 100, 1 ) : null,
			'abandoned_value'    => self::decimal( $row['abandoned_value'] ?? 0 ),
			'order_value'        => self::decimal( $row['order_value'] ?? 0 ),
			'recognized_revenue' => self::decimal( $row['recognized_revenue'] ?? 0 ),
			'refunded_value'     => self::decimal( $row['refunded_value'] ?? 0 ),
		);
	}

	/**
	 * Format a non-negative fixed-point amount.
	 *
	 * @param mixed $value Database value.
	 */
	private static function decimal( mixed $value ): string {
		return number_format( max( 0, (float) $value ), 4, '.', '' );
	}
}
