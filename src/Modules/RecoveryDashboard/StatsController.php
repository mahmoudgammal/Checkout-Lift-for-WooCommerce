<?php
/**
 * Admin REST endpoint backing the recovery dashboard.
 *
 * @package SimpleCheckoutFieldsEditor
 */

declare( strict_types=1 );

namespace DesignTich\SimpleCheckoutFieldsEditor\Modules\RecoveryDashboard;

use DesignTich\SimpleCheckoutFieldsEditor\Core\Database\RecoveryStats;
use DesignTich\SimpleCheckoutFieldsEditor\Core\Privacy\PrivacySettings;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * Serves the dashboard its figures.
 *
 * Unlike the capture route this one is closed: it reads aggregate data about
 * a store's customers, so it requires the same capability WooCommerce uses
 * for its own reports.
 */
final class StatsController {

	/**
	 * REST namespace.
	 */
	public const NAMESPACE = 'scfe/v1';

	/**
	 * Route returning dashboard figures.
	 */
	public const ROUTE = '/recovery/stats';

	/**
	 * Create the controller.
	 *
	 * @param RecoveryStats   $stats   Aggregate reader.
	 * @param PrivacySettings $privacy Privacy settings.
	 */
	public function __construct(
		private RecoveryStats $stats,
		private PrivacySettings $privacy
	) {
	}

	/**
	 * Register the route.
	 */
	public function register_routes(): void {
		register_rest_route(
			self::NAMESPACE,
			self::ROUTE,
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'handle' ),
				'permission_callback' => array( $this, 'permitted' ),
				'args'                => array(
					'limit' => array(
						'type'    => 'integer',
						'default' => 20,
						'minimum' => 1,
						'maximum' => 100,
					),
				),
			)
		);
	}

	/**
	 * Allow only users who may already see WooCommerce reports.
	 */
	public function permitted(): bool {
		return current_user_can( 'manage_woocommerce' );
	}

	/**
	 * Return the dashboard payload.
	 *
	 * @param WP_REST_Request $request Incoming request.
	 */
	public function handle( WP_REST_Request $request ): WP_REST_Response {
		$recent = array_map(
			array( $this, 'add_order_url' ),
			$this->stats->recent( (int) $request->get_param( 'limit' ) )
		);

		return new WP_REST_Response(
			array(
				'tracking' => $this->privacy->tracking_enabled(),
				'summary'  => $this->stats->summary(),
				'recent'   => $recent,
			),
			200
		);
	}

	/**
	 * Add WooCommerce's storage-aware admin URL to a recent cart.
	 *
	 * @param array<string, mixed> $cart Recent cart row.
	 * @return array<string, mixed>
	 */
	private function add_order_url( array $cart ): array {
		$order_id          = (int) ( $cart['order_id'] ?? 0 );
		$cart['order_url'] = '';

		if ( $order_id < 1 || ! function_exists( 'wc_get_order' ) ) {
			return $cart;
		}

		$order = wc_get_order( $order_id );

		if ( $order && method_exists( $order, 'get_edit_order_url' ) ) {
			$cart['order_url'] = esc_url_raw( $order->get_edit_order_url() );
		}

		return $cart;
	}
}
