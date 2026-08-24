<?php
/**
 * Advanced settings and tools endpoints.
 *
 * @package SimpleCheckoutFieldsEditor
 */

declare( strict_types=1 );

namespace DesignTich\SimpleCheckoutFieldsEditor\Modules\FieldsAdmin;

use DesignTich\SimpleCheckoutFieldsEditor\Core\Admin\AdminMenu;
use DesignTich\SimpleCheckoutFieldsEditor\Core\Settings\CheckoutFieldsRepository;
use DesignTich\SimpleCheckoutFieldsEditor\Modules\CheckoutFieldsAdmin\FieldSettingsSanitizer;
use UnexpectedValueException;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * Serves everything the classic screen owned besides the field list.
 *
 * Serves the free shipping toggle and checkout-field transfer tools.
 */
final class AdvancedController {

	/**
	 * REST namespace.
	 */
	public const NAMESPACE = 'scfe/v1';

	/**
	 * Create the controller.
	 *
	 * @param CheckoutFieldsRepository $fields    Field storage.
	 * @param FieldSettingsSanitizer   $sanitizer Field schema sanitizer.
	 */
	public function __construct(
		private CheckoutFieldsRepository $fields,
		private FieldSettingsSanitizer $sanitizer
	) {
	}

	/**
	 * Register the routes.
	 */
	public function register_routes(): void {
		register_rest_route(
			self::NAMESPACE,
			'/advanced',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'read' ),
					'permission_callback' => array( $this, 'permitted' ),
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'write' ),
					'permission_callback' => array( $this, 'permitted' ),
					'args'                => array(
						'disable_shipping' => array( 'type' => 'boolean' ),
					),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/export',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'export' ),
				'permission_callback' => array( $this, 'permitted' ),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/import',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'import' ),
				'permission_callback' => array( $this, 'permitted' ),
				'args'                => array(
					'payload' => array(
						'type'     => 'object',
						'required' => true,
					),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/reset',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'reset' ),
				'permission_callback' => array( $this, 'permitted' ),
			)
		);
	}

	/**
	 * Allow only users who may manage the store.
	 */
	public function permitted(): bool {
		return current_user_can( AdminMenu::CAPABILITY );
	}

	/**
	 * Return the advanced settings.
	 */
	public function read(): WP_REST_Response {
		return new WP_REST_Response( $this->current(), 200 );
	}

	/**
	 * Persist the advanced settings.
	 *
	 * @param WP_REST_Request $request Incoming request.
	 */
	public function write( WP_REST_Request $request ) {
		if ( null !== $request->get_param( 'disable_shipping' ) && ! $this->replace_option( 'scfe_disable_shipping', (bool) $request->get_param( 'disable_shipping' ) ) ) {
			return $this->save_error();
		}

		$extension_error = apply_filters( 'scfe_save_advanced_settings', null, $request );

		if ( $extension_error instanceof WP_Error ) {
			return $extension_error;
		}

		return new WP_REST_Response( $this->current(), 200 );
	}

	/**
	 * Return everything a merchant would want to carry to another store.
	 */
	public function export(): WP_REST_Response {
		$data = array(
			'plugin_version'   => defined( 'SCFE_VERSION' ) ? SCFE_VERSION : '',
			'export_date'      => gmdate( 'Y-m-d H:i:s' ),
			'checkout_fields'  => $this->fields->fields(),
			'disable_shipping' => (bool) get_option( 'scfe_disable_shipping', false ),
		);
		$data = apply_filters( 'scfe_export_checkout_settings', $data );

		return new WP_REST_Response( is_array( $data ) ? $data : array(), 200 );
	}

	/**
	 * Restore settings from an exported file.
	 *
	 * @param WP_REST_Request $request Incoming request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function import( WP_REST_Request $request ) {
		$payload = $request->get_param( 'payload' );

		if ( ! is_array( $payload ) || ! is_array( $payload['checkout_fields'] ?? null ) ) {
			return new WP_Error(
				'scfe_invalid_import',
				__( 'That file does not look like an export from this plugin.', 'checkout-lift-for-woocommerce' ),
				array( 'status' => 400 )
			);
		}

		try {
			$fields = $this->sanitizer->sanitize( $payload['checkout_fields'] );
		} catch ( UnexpectedValueException $exception ) {
			return new WP_Error(
				'scfe_invalid_import',
				__( 'The file contains checkout fields this version cannot read.', 'checkout-lift-for-woocommerce' ),
				array( 'status' => 400 )
			);
		}

		if ( ! $this->fields->replace( $fields ) ) {
			return $this->save_error();
		}

		if ( array_key_exists( 'disable_shipping', $payload ) && ! $this->replace_option( 'scfe_disable_shipping', (bool) $payload['disable_shipping'] ) ) {
			return $this->save_error();
		}

		$extension_error = apply_filters( 'scfe_import_checkout_settings', null, $payload );

		if ( $extension_error instanceof WP_Error ) {
			return $extension_error;
		}

		do_action( 'scfe_save_settings' );

		return new WP_REST_Response( array( 'imported' => true ), 200 );
	}

	/**
	 * Clear stored field settings so WooCommerce defaults apply again.
	 *
	 * Only field settings are cleared; recovery data is independent.
	 */
	public function reset() {
		if ( ! $this->fields->replace( array() ) ) {
			return $this->save_error();
		}
		delete_option( 'scfe_disable_shipping' );

		do_action( 'scfe_save_settings' );

		return new WP_REST_Response( array( 'reset' => true ), 200 );
	}

	/**
	 * Return the advanced settings as stored.
	 *
	 * @return array<string, mixed>
	 */
	private function current(): array {
		$current = array( 'disable_shipping' => (bool) get_option( 'scfe_disable_shipping', false ) );
		$current = apply_filters( 'scfe_advanced_settings', $current );

		return is_array( $current ) ? $current : array();
	}

	/**
	 * Replace a scalar option without mistaking an unchanged value for failure.
	 *
	 * @param string $name  Option name.
	 * @param mixed  $value Sanitized value.
	 */
	private function replace_option( string $name, $value ): bool {
		return get_option( $name, null ) === $value || update_option( $name, $value );
	}

	/**
	 * Return the common persistence failure response.
	 */
	private function save_error(): WP_Error {
		return new WP_Error(
			'scfe_save_failed',
			__( 'The settings could not be saved completely. Review the current values and try again.', 'checkout-lift-for-woocommerce' ),
			array( 'status' => 500 )
		);
	}
}
