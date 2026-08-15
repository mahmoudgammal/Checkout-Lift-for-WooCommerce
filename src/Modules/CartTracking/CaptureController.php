<?php
/**
 * Public REST endpoint that records checkout activity.
 *
 * @package SimpleCheckoutFieldsEditor
 */

declare( strict_types=1 );

namespace DesignTich\SimpleCheckoutFieldsEditor\Modules\CartTracking;

use DesignTich\SimpleCheckoutFieldsEditor\Core\Privacy\ConsentPolicy;
use DesignTich\SimpleCheckoutFieldsEditor\Core\Recovery\CartCapture;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * Receives capture requests from the checkout page.
 *
 * A dedicated route rather than `admin-ajax.php`, because this fires
 * repeatedly while a customer types and admin-ajax loads far more of
 * WordPress than a write of this size needs.
 */
final class CaptureController {

	/**
	 * REST namespace.
	 */
	public const NAMESPACE = 'scfe/v1';

	/**
	 * Route recording checkout activity.
	 */
	public const ROUTE = '/capture';

	/**
	 * Create the controller.
	 *
	 * @param CartCapture        $capture Capture service.
	 * @param ConsentPolicy      $consent Consent gate.
	 * @param CaptureRateLimiter $limiter Throttling.
	 */
	public function __construct(
		private CartCapture $capture,
		private ConsentPolicy $consent,
		private CaptureRateLimiter $limiter
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
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'handle' ),
				'permission_callback' => array( $this, 'permitted' ),
				'args'                => $this->args(),
			)
		);
	}

	/**
	 * Allow the request when it carries a valid REST nonce.
	 *
	 * The route is open to logged-out customers by design, since most
	 * checkouts are guest checkouts. The nonce is what keeps it from being
	 * driven from anywhere by a script.
	 *
	 * @param WP_REST_Request $request Incoming request.
	 * @return true|WP_Error
	 */
	public function permitted( WP_REST_Request $request ) {
		$nonce = $request->get_header( 'X-WP-Nonce' );

		if ( ! is_string( $nonce ) || ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
			return new WP_Error(
				'scfe_invalid_nonce',
				__( 'Session expired.', 'checkout-lift-for-woocommerce' ),
				array( 'status' => 403 )
			);
		}

		return true;
	}

	/**
	 * Record checkout activity for the current session.
	 *
	 * @param WP_REST_Request $request Incoming request.
	 */
	public function handle( WP_REST_Request $request ): WP_REST_Response {
		// Reject disabled tracking before starting session or cart work.
		if ( ! $this->consent->may_store( (bool) $request->get_param( 'consent' ) ) ) {
			return new WP_REST_Response(
				array(
					'stored' => false,
					'reason' => 'consent',
				),
				200
			);
		}

		$session_key = $this->session_key();

		if ( ! $this->limiter->allow( $session_key ) ) {
			return new WP_REST_Response(
				array(
					'stored' => false,
					'reason' => 'rate_limited',
				),
				429
			);
		}

		$result = $this->capture->capture(
			$this->payload( $request ),
			$session_key,
			(bool) $request->get_param( 'consent' )
		);

		if ( $result['stored'] ) {
			do_action( 'scfe_checkout_activity_captured' );
		}

		// Do not expose the internal cart identifier to the browser.
		return new WP_REST_Response(
			array(
				'stored' => $result['stored'],
				'reason' => $result['reason'],
			),
			200
		);
	}

	/**
	 * Return the checkout session identifier.
	 *
	 * WooCommerce does not start a session on a custom REST route, so the
	 * cart has to be loaded before one exists.
	 */
	private function session_key(): string {
		if ( ! function_exists( 'WC' ) ) {
			return '';
		}

		if ( null === WC()->session && function_exists( 'wc_load_cart' ) ) {
			wc_load_cart();
		}

		if ( null === WC()->session ) {
			return '';
		}

		return (string) WC()->session->get_customer_id();
	}

	/**
	 * Collect the allowlisted parameters into a payload.
	 *
	 * @param WP_REST_Request $request Incoming request.
	 * @return array<string, mixed>
	 */
	private function payload( WP_REST_Request $request ): array {
		$payload = array();

		foreach ( array_keys( $this->args() ) as $name ) {
			if ( 'consent' === $name ) {
				continue;
			}

			$payload[ $name ] = $request->get_param( $name );
		}

		return $payload;
	}

	/**
	 * Describe accepted parameters.
	 *
	 * Every field is a string with a bounded length. Totals are absent on
	 * purpose: they are read from WooCommerce, never accepted from a browser.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	private function args(): array {
		$text = array(
			'type'              => 'string',
			'required'          => false,
			'default'           => '',
			'maxLength'         => 191,
			'sanitize_callback' => 'sanitize_text_field',
		);

		return array(
			'consent'         => array(
				'type'     => 'boolean',
				'required' => false,
				'default'  => false,
			),
			'email'           => $text,
			'phone'           => $text,
			'first_name'      => $text,
			'last_name'       => $text,
			'country'         => $text,
			'checkout_step'   => $text,
			'payment_method'  => $text,
			'shipping_method' => $text,
		);
	}
}
