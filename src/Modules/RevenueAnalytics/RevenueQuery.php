<?php
/**
 * Bounded Lite checkout-value queries.
 *
 * @package SimpleCheckoutFieldsEditor
 */

declare( strict_types=1 );

namespace DesignTich\SimpleCheckoutFieldsEditor\Modules\RevenueAnalytics;

use DesignTich\SimpleCheckoutFieldsEditor\Core\Database\TableNames;

/**
 * Reads the free checkout funnel and abandoned-value aggregates.
 */
final class RevenueQuery {

	/**
	 * Create the aggregate query.
	 *
	 * @param TableNames $tables Plugin table names.
	 */
	public function __construct( private TableNames $tables ) {
	}

	/**
	 * Return Lite checkout totals.
	 *
	 * @param array{from:string,to:string,currency:string} $filters Query filters.
	 * @return array<string, mixed>
	 */
	public function overview( array $filters ): array {
		global $wpdb;

		$table = $this->tables->name( TableNames::ATTEMPTS );
		$sql   = "SELECT COUNT(*) AS attempts,SUM(status='abandoned') AS abandoned_count,SUM(status='placed') AS placed_count,COALESCE(SUM(CASE WHEN status='abandoned' THEN order_total ELSE 0 END),0) AS abandoned_value FROM %i WHERE first_seen_at>=%s AND first_seen_at<%s AND currency=%s";

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Bounded aggregate on a module-owned table; identifier and values are bound.
		$row = $wpdb->get_row( $wpdb->prepare( $sql, $table, $filters['from'], $filters['to'], $filters['currency'] ), ARRAY_A );

		return $this->normalize( is_array( $row ) ? $row : array() );
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
	 * Normalize an aggregate row.
	 *
	 * @param array<string, mixed> $row Aggregate row.
	 * @return array<string, mixed>
	 */
	private function normalize( array $row ): array {
		$abandoned = (int) ( $row['abandoned_count'] ?? 0 );
		$placed    = (int) ( $row['placed_count'] ?? 0 );
		$closed    = $abandoned + $placed;

		return array(
			'attempts'        => (int) ( $row['attempts'] ?? 0 ),
			'abandoned'       => $abandoned,
			'placed'          => $placed,
			'placement_rate'  => 0 < $closed ? round( ( $placed / $closed ) * 100, 1 ) : null,
			'abandoned_value' => wc_format_decimal( max( 0, (float) ( $row['abandoned_value'] ?? 0 ) ), 4 ),
		);
	}
}
