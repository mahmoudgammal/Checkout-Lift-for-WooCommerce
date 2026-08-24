<?php
/**
 * Authenticated Lite checkout-value endpoint.
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
 * Serves free checkout funnel and abandoned-value aggregates.
 */
final class RevenueController {

	public const NAMESPACE = 'scfe/v1';
	public const ROUTE     = '/revenue/overview';

	/**
	 * Create the Lite revenue controller.
	 *
	 * @param RevenueQuery    $query   Lite aggregate reader.
	 * @param PrivacySettings $privacy Tracking settings.
	 */
	public function __construct(
		private RevenueQuery $query,
		private PrivacySettings $privacy
	) {
	}

	/** Register the read-only route. */
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

	/** Require WooCommerce report access. */
	public function permitted(): bool {
		return current_user_can( 'manage_woocommerce' );
	}

	/**
	 * Return the filtered Lite overview.
	 *
	 * @param WP_REST_Request $request Validated request.
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

		[ $from, $to ] = $this->range( $period );
		$filters       = array(
			'from'     => $from,
			'to'       => $to,
			'currency' => $currency,
		);

		return new WP_REST_Response(
			array(
				'tracking' => $this->privacy->tracking_enabled(),
				'period'   => array(
					'days' => $period,
					'from' => $from,
					'to'   => $to,
				),
				'filters'  => array(
					'currency'   => $currency,
					'currencies' => $currencies,
				),
				'totals'   => $this->query->overview( $filters ),
			),
			200
		);
	}

	/**
	 * Define route arguments.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	private function args(): array {
		return array(
			'period'   => array(
				'type'    => 'integer',
				'default' => 30,
				'enum'    => array( 7, 30 ),
			),
			'currency' => array(
				'type'              => 'string',
				'default'           => '',
				'pattern'           => '^[A-Za-z]{3}$|^$',
				'sanitize_callback' => static fn( string $value ): string => strtoupper( $value ),
			),
		);
	}

	/**
	 * Build the requested UTC range.
	 *
	 * @param int $days Period length.
	 * @return array{0:string,1:string}
	 */
	private function range( int $days ): array {
		$zone = wp_timezone();
		$now  = new \DateTimeImmutable( 'now', $zone );
		$from = $now->setTime( 0, 0 )->modify( '-' . ( $days - 1 ) . ' days' );
		$utc  = new \DateTimeZone( 'UTC' );

		return array( $from->setTimezone( $utc )->format( 'Y-m-d H:i:s' ), $now->setTimezone( $utc )->format( 'Y-m-d H:i:s' ) );
	}
}
