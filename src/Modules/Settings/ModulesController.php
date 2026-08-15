<?php
/**
 * Reads and writes which modules are switched on.
 *
 * @package SimpleCheckoutFieldsEditor
 */

declare( strict_types=1 );

namespace DesignTich\SimpleCheckoutFieldsEditor\Modules\Settings;

use DesignTich\SimpleCheckoutFieldsEditor\Core\Features\Features;
use DesignTich\SimpleCheckoutFieldsEditor\Core\Settings\ModuleSettings;
use DesignTich\SimpleCheckoutFieldsEditor\Core\Settings\ModuleShutdown;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * The module switches, over REST.
 */
final class ModulesController {

	/**
	 * REST namespace.
	 */
	public const NAMESPACE = 'scfe/v1';

	/**
	 * Route.
	 */
	public const ROUTE = '/modules';

	/**
	 * Create the controller.
	 *
	 * @param ModuleSettings $modules  Stored module choices.
	 * @param Features       $features What this build ships.
	 * @param ModuleShutdown $shutdown Tidying up after a module switched off.
	 */
	public function __construct(
		private ModuleSettings $modules,
		private Features $features,
		private ModuleShutdown $shutdown
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
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'read' ),
					'permission_callback' => array( $this, 'permitted' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'write' ),
					'permission_callback' => array( $this, 'permitted' ),
					'args'                => array(
						'modules' => array(
							'type'                 => 'object',
							'required'             => true,
							'additionalProperties' => false,
							'properties'           => $this->module_properties(),
						),
					),
				),
			)
		);
	}

	/**
	 * Allow a shop manager to read and change these.
	 *
	 * @return true|WP_Error
	 */
	public function permitted() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return new WP_Error(
				'scfe_forbidden',
				__( 'You are not allowed to change these settings.', 'checkout-lift-for-woocommerce' ),
				array( 'status' => 403 )
			);
		}

		return true;
	}

	/**
	 * Return every optional module, its label, and its state.
	 */
	public function read(): WP_REST_Response {
		$enabled = $this->modules->all();
		$rows    = array();

		foreach ( ModuleSettings::optional() as $name => $label ) {
			$available = $this->features->enabled( $name );
			if ( ! $available ) {
				continue;
			}

			$rows[] = array(
				'name'      => $name,
				'label'     => $label,
				'enabled'   => $enabled[ $name ] ?? true,
				'available' => $available,
			);
		}

		return new WP_REST_Response( array( 'modules' => $rows ), 200 );
	}

	/**
	 * Store the submitted choices.
	 *
	 * @param WP_REST_Request $request Incoming request.
	 */
	public function write( WP_REST_Request $request ) {
		$submitted = $request->get_param( 'modules' );
		$before    = $this->modules->all();

		$submitted = is_array( $submitted ) ? array_merge( $before, $submitted ) : $before;

		if ( ! $this->modules->replace( $submitted ) ) {
			return new WP_Error(
				'scfe_save_failed',
				__( 'Module settings could not be saved.', 'checkout-lift-for-woocommerce' ),
				array( 'status' => 500 )
			);
		}

		// Disabled modules stop registering, so their orphaned scheduled hooks must be cleared here.
		$this->shutdown->apply( $before, $this->modules->all() );

		return $this->read();
	}

	/**
	 * Describe every accepted merchant module choice.
	 *
	 * @return array<string, array{type: string}>
	 */
	private function module_properties(): array {
		$properties = array();

		foreach ( array_keys( ModuleSettings::optional() ) as $module ) {
			$properties[ $module ] = array( 'type' => 'boolean' );
		}

		return $properties;
	}
}
