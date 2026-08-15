<?php
/**
 * Recovery settings endpoint.
 *
 * @package SimpleCheckoutFieldsEditor
 */

declare( strict_types=1 );

namespace DesignTich\SimpleCheckoutFieldsEditor\Modules\RecoveryDashboard;

use DesignTich\SimpleCheckoutFieldsEditor\Core\Admin\AdminMenu;
use DesignTich\SimpleCheckoutFieldsEditor\Core\Privacy\PrivacySettings;
use DesignTich\SimpleCheckoutFieldsEditor\Core\Recovery\RecoverySettings;
use DesignTich\SimpleCheckoutFieldsEditor\Core\Recovery\RecoverySettingsExtension;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * Reads and writes everything the Settings tab controls.
 *
 * Privacy and recovery settings live in separate options because they answer
 * different questions, but a merchant thinks of them as one screen, so one
 * endpoint serves both rather than making the interface juggle two.
 */
final class SettingsController {

	/**
	 * REST namespace.
	 */
	public const NAMESPACE = 'scfe/v1';

	/**
	 * Route for reading and writing settings.
	 */
	public const ROUTE = '/recovery/settings';

	/**
	 * Create the controller.
	 *
	 * @param PrivacySettings                $privacy   Privacy settings.
	 * @param RecoverySettings               $recovery  Lite recovery settings.
	 * @param RecoverySettingsExtension|null $extension Optional add-on fields.
	 */
	public function __construct(
		private PrivacySettings $privacy,
		private RecoverySettings $recovery,
		private ?RecoverySettingsExtension $extension = null
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
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'write' ),
					'permission_callback' => array( $this, 'permitted' ),
					'args'                => $this->args(),
				),
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
	 * Return current settings.
	 */
	public function read(): WP_REST_Response {
		return new WP_REST_Response( $this->current(), 200 );
	}

	/**
	 * Persist submitted settings.
	 *
	 * @param WP_REST_Request $request Incoming request.
	 */
	public function write( WP_REST_Request $request ) {
		$privacy_saved = $this->privacy->replace(
			array(
				'tracking_enabled'    => $request->get_param( 'tracking_enabled' ),
				'require_consent'     => $request->get_param( 'require_consent' ),
				'consent_text'        => $request->get_param( 'consent_text' ),
				'retention_days'      => $request->get_param( 'retention_days' ),
				'delete_on_uninstall' => $request->get_param( 'delete_on_uninstall' ),
			)
		);

		$values          = $request->get_params();
		$recovery_values = array( 'idle_minutes' => $request->get_param( 'idle_minutes' ) );
		if ( null !== $this->extension ) {
			$recovery_values['reminder_minutes'] = $request->get_param( 'reminder_minutes' );
			$recovery_values['email_subject']    = $request->get_param( 'email_subject' );
			$recovery_values['email_body']       = $request->get_param( 'email_body' );
		}
		$recovery_saved  = $this->recovery->replace( $recovery_values );
		$extension_saved = null === $this->extension || $this->extension->replace( is_array( $values ) ? $values : array() );

		if ( ! $privacy_saved || ! $recovery_saved || ! $extension_saved ) {
			return new WP_Error(
				'scfe_save_failed',
				__( 'Recovery settings could not be saved completely. Review the current values and try again.', 'checkout-lift-for-woocommerce' ),
				array( 'status' => 500 )
			);
		}

		// Return stored values so the UI reflects any sanitization or clamping.
		return new WP_REST_Response( $this->current(), 200 );
	}

	/**
	 * Return the settings as stored.
	 *
	 * @return array<string, mixed>
	 */
	private function current(): array {
		$current = array(
			'tracking_enabled'    => $this->privacy->tracking_enabled(),
			'require_consent'     => $this->privacy->require_consent(),
			'consent_text'        => $this->privacy->consent_text(),
			'retention_days'      => $this->privacy->retention_days(),
			'delete_on_uninstall' => $this->privacy->delete_on_uninstall(),
			'idle_minutes'        => $this->recovery->idle_minutes(),
			'email_sequence'      => null !== $this->extension,
		);
		if ( null !== $this->extension ) {
			$current['reminder_minutes'] = $this->recovery->reminder_minutes();
			$current['email_subject']    = $this->recovery->custom_email_subject();
			$current['email_body']       = $this->recovery->custom_email_body();
		}

		return null === $this->extension ? $current : array_merge( $current, $this->extension->values() );
	}

	/**
	 * Describe accepted parameters.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	private function args(): array {
		$args = array(
			'tracking_enabled'    => array( 'type' => 'boolean' ),
			'require_consent'     => array( 'type' => 'boolean' ),
			'delete_on_uninstall' => array( 'type' => 'boolean' ),
			'consent_text'        => array(
				'type'      => 'string',
				'maxLength' => 500,
			),
			'retention_days'      => array(
				'type'    => 'integer',
				'minimum' => 1,
				'maximum' => 730,
			),
			'idle_minutes'        => array(
				'type'    => 'integer',
				'minimum' => 1,
				'maximum' => 10080,
			),
		);
		if ( null !== $this->extension ) {
			$args['reminder_minutes'] = $this->minutes_arg();
			$args['email_subject']    = array(
				'type'      => 'string',
				'maxLength' => 200,
			);
			$args['email_body']       = array(
				'type'      => 'string',
				'maxLength' => 5000,
			);
		}

		return null === $this->extension ? $args : array_merge( $args, $this->extension->args() );
	}

	/**
	 * Return the common REST schema for a sequence delay.
	 *
	 * @return array<string, mixed>
	 */
	private function minutes_arg(): array {
		return array(
			'type'    => 'integer',
			'minimum' => 1,
			'maximum' => 10080,
		);
	}
}
