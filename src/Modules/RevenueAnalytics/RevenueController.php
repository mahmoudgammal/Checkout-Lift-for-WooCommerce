<?php
/**
 * Authenticated revenue overview endpoint.
 *
 * @package SimpleCheckoutFieldsEditor
 */

declare( strict_types=1 );

namespace DesignTich\SimpleCheckoutFieldsEditor\Modules\RevenueAnalytics;

use DesignTich\SimpleCheckoutFieldsEditor\Core\Privacy\PrivacySettings;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * Serves privacy-safe aggregates to authorized store managers.
 */
final class RevenueController {

	public const NAMESPACE = 'scfe/v1';
	public const ROUTE     = '/revenue/overview';

	/**
	 * Create the controller.
	 *
	 * @param RevenueQuery    $query   Aggregate reader.
	 * @param PrivacySettings $privacy Tracking preference.
	 */
	public function __construct(
		private RevenueQuery $query,
		private PrivacySettings $privacy
	) {
	}

	/**
	 * Register the read-only route.
	 */
	public function register_routes(): void {
		register_rest_route(
			self::NAMESPACE,
			self::ROUTE,
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'handle' ),
				'permission_callback' => array( $this, 'permitted' ),
				'args'                => $this->args(),
			)
		);
	}

	/**
	 * Require WooCommerce report access.
	 */
	public function permitted(): bool {
		return current_user_can( 'manage_woocommerce' );
	}

	/**
	 * Return the filtered overview.
	 *
	 * @param WP_REST_Request $request REST request.
	 */
	public function handle( WP_REST_Request $request ): WP_REST_Response {
		$period     = (int) $request->get_param( 'period' );
		$currency   = strtoupper( (string) $request->get_param( 'currency' ) );
		$currencies = $this->query->currencies();

		if ( '' === $currency ) {
			$currency = function_exists( 'get_woocommerce_currency' ) ? (string) get_woocommerce_currency() : 'USD';
		}

		if ( array() !== $currencies && ! in_array( $currency, $currencies, true ) ) {
			$currency = $currencies[0];
		}

		[ $from, $to, $timezone ] = $this->range( $period );
		$filters                  = array(
			'from'     => $from,
			'to'       => $to,
			'currency' => $currency,
			'runtime'  => (string) $request->get_param( 'runtime' ),
			'status'   => (string) $request->get_param( 'status' ),
			'timezone' => $timezone,
		);

		$response = array(
			'tracking'       => $this->privacy->tracking_enabled(),
			'metric_version' => 1,
			'period'         => array(
				'days'       => $period,
				'from'       => $from,
				'to'         => $to,
				'incomplete' => true,
			),
			'filters'        => array(
				'currency'   => $currency,
				'currencies' => $currencies,
				'runtime'    => $filters['runtime'],
				'status'     => $filters['status'],
			),
			'totals'         => $this->query->overview( $filters ),
			'recovery'       => $this->query->recovery( $filters ),
			'series'         => $this->query->series( $filters ),
			'comparison'     => null,
		);

		if ( (bool) $request->get_param( 'compare' ) ) {
			$seconds                = strtotime( $to . ' UTC' ) - strtotime( $from . ' UTC' );
			$comparison             = $filters;
			$comparison['to']       = $from;
			$comparison['from']     = gmdate( 'Y-m-d H:i:s', strtotime( $from . ' UTC' ) - $seconds );
			$response['comparison'] = $this->query->overview( $comparison );
		}

		return new WP_REST_Response( $response, 200 );
	}

	/**
	 * Describe and constrain request parameters.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	private function args(): array {
		return array(
			'period'   => array(
				'type'    => 'integer',
				'default' => 30,
				'enum'    => array( 7, 30, 90 ),
			),
			'currency' => array(
				'type'              => 'string',
				'default'           => '',
				'pattern'           => '^[A-Za-z]{3}$|^$',
				'sanitize_callback' => static fn( string $value ): string => strtoupper( $value ),
			),
			'runtime'  => array(
				'type'    => 'string',
				'default' => 'all',
				'enum'    => array( 'all', 'classic', 'blocks' ),
			),
			'status'   => array(
				'type'    => 'string',
				'default' => 'all',
				'enum'    => array( 'all', 'open', 'abandoned', 'placed' ),
			),
			'compare'  => array(
				'type'    => 'boolean',
				'default' => true,
			),
		);
	}

	/**
	 * Build merchant-timezone boundaries stored as UTC.
	 *
	 * @param int $days Period length.
	 * @return array{0:string,1:string,2:string}
	 */
	private function range( int $days ): array {
		$zone     = wp_timezone();
		$now      = new \DateTimeImmutable( 'now', $zone );
		$from     = $now->setTime( 0, 0 )->modify( '-' . ( $days - 1 ) . ' days' );
		$from_utc = $from->setTimezone( new \DateTimeZone( 'UTC' ) );
		$now_utc  = $now->setTimezone( new \DateTimeZone( 'UTC' ) );

		return array( $from_utc->format( 'Y-m-d H:i:s' ), $now_utc->format( 'Y-m-d H:i:s' ), $zone->getName() );
	}
}
