<?php
/**
 * Checkout fields endpoint for the React editor.
 *
 * @package SimpleCheckoutFieldsEditor
 */

declare( strict_types=1 );

namespace DesignTich\SimpleCheckoutFieldsEditor\Modules\FieldsAdmin;

use DesignTich\SimpleCheckoutFieldsEditor\Core\Admin\AdminMenu;
use DesignTich\SimpleCheckoutFieldsEditor\Core\Settings\CheckoutFieldsRepository;
use DesignTich\SimpleCheckoutFieldsEditor\Modules\Admin\AdminCheckoutFields;
use DesignTich\SimpleCheckoutFieldsEditor\Modules\CheckoutFieldsAdmin\FieldSettingsSanitizer;
use UnexpectedValueException;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * Reads and writes checkout field settings.
 *
 * The same provider and the same sanitizer the existing screen uses, so the
 * React editor cannot accept anything the old one would reject, and the two
 * cannot drift into disagreeing about what a valid field is while both exist.
 */
final class FieldsController {

	/**
	 * REST namespace.
	 */
	public const NAMESPACE = 'scfe/v1';

	/**
	 * Route for reading and writing fields.
	 */
	public const ROUTE = '/fields';

	/**
	 * Create the controller.
	 *
	 * @param AdminCheckoutFields      $provider  Merged field data.
	 * @param CheckoutFieldsRepository $storage   Field storage.
	 * @param FieldSettingsSanitizer   $sanitizer Field schema sanitizer.
	 * @param FieldsPayload            $payload   Editor shape translation.
	 */
	public function __construct(
		private AdminCheckoutFields $provider,
		private CheckoutFieldsRepository $storage,
		private FieldSettingsSanitizer $sanitizer,
		private FieldsPayload $payload
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
					'args'                => array(
						'sections'         => array(
							'type'     => 'array',
							'required' => true,
						),
						'fingerprint'      => array( 'type' => 'string' ),
						'disable_shipping' => array( 'type' => 'boolean' ),
					),
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
	 * Return the sections and their fields, in display order.
	 */
	public function read(): WP_REST_Response {
		$sections = array();

		foreach ( $this->provider->fields() as $section => $fields ) {
			$rows = array();

			foreach ( $fields as $key => $settings ) {
				$rows[] = $this->payload->to_editor_field( $key, is_array( $settings ) ? $settings : array() );
			}

			$sections[] = array(
				'key'    => $section,
				'label'  => $this->section_label( $section ),
				'fields' => $rows,
			);
		}

		return new WP_REST_Response(
			array(
				'sections'         => $sections,
				// The client returns this revision so stale writes can be rejected.
				'fingerprint'      => $this->fingerprint(),
				'disable_shipping' => (bool) get_option( 'scfe_disable_shipping', false ),
				// Limit conditions to installed methods so saved rules can match.
				'payment_methods'  => $this->payment_methods(),
				'shipping_methods' => $this->shipping_methods(),
			),
			200
		);
	}

	/**
	 * Return a fingerprint of the stored fields.
	 *
	 * Any change to the option produces a different value, whichever screen
	 * made it, which is what lets a stale write be detected.
	 */
	private function fingerprint(): string {
		return md5( (string) wp_json_encode( $this->storage->fields() ) );
	}

	/**
	 * Return the payment gateways a rule may reference.
	 *
	 * @return list<array{value: string, label: string}>
	 */
	private function payment_methods(): array {
		if ( ! function_exists( 'WC' ) || null === WC()->payment_gateways() ) {
			return array();
		}

		$methods = array();

		foreach ( WC()->payment_gateways()->payment_gateways() as $gateway ) {
			$methods[] = array(
				'value' => (string) $gateway->id,
				'label' => (string) $gateway->get_method_title(),
			);
		}

		return $methods;
	}

	/**
	 * Return the shipping methods a rule may reference.
	 *
	 * @return list<array{value: string, label: string}>
	 */
	private function shipping_methods(): array {
		if ( ! function_exists( 'WC' ) || null === WC()->shipping() ) {
			return array();
		}

		$methods = array();

		foreach ( WC()->shipping()->get_shipping_methods() as $method ) {
			$methods[] = array(
				'value' => (string) $method->id,
				'label' => (string) $method->get_method_title(),
			);
		}

		return $methods;
	}

	/**
	 * Persist submitted fields.
	 *
	 * @param WP_REST_Request $request Incoming request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function write( WP_REST_Request $request ) {
		$submitted = $request->get_param( 'sections' );

		if ( ! is_array( $submitted ) ) {
			return new WP_Error(
				'scfe_invalid_payload',
				__( 'No fields were submitted.', 'checkout-lift-for-woocommerce' ),
				array( 'status' => 400 )
			);
		}

		$seen = $request->get_param( 'fingerprint' );

		if ( is_string( $seen ) && '' !== $seen && $seen !== $this->fingerprint() ) {
			// Reject stale writes instead of silently overwriting another editor.
			return new WP_Error(
				'scfe_conflict',
				__( 'These fields changed somewhere else since this screen loaded. Reload to see the current values, then make your changes again.', 'checkout-lift-for-woocommerce' ),
				array( 'status' => 409 )
			);
		}

		try {
			$settings = $this->sanitizer->sanitize( $this->payload->to_storage( $submitted ) );
		} catch ( UnexpectedValueException $exception ) {
			return new WP_Error(
				'scfe_invalid_fields',
				$this->error_message( $exception->getMessage() ),
				array( 'status' => 400 )
			);
		}

		if ( ! $this->storage->replace( $settings ) ) {
			return new WP_Error(
				'scfe_save_failed',
				__( 'Checkout field settings could not be saved.', 'checkout-lift-for-woocommerce' ),
				array( 'status' => 500 )
			);
		}

		if ( null !== $request->get_param( 'disable_shipping' ) ) {
			$disable_shipping = (bool) $request->get_param( 'disable_shipping' );

			if ( get_option( 'scfe_disable_shipping', null ) !== $disable_shipping && ! update_option( 'scfe_disable_shipping', $disable_shipping ) ) {
				return new WP_Error(
					'scfe_save_failed',
					__( 'The shipping setting could not be saved.', 'checkout-lift-for-woocommerce' ),
					array( 'status' => 500 )
				);
			}
		}

		do_action( 'scfe_save_settings' );

		return $this->read();
	}

	/**
	 * Return the display label for a section.
	 *
	 * @param string $section Section key.
	 */
	private function section_label( string $section ): string {
		$labels = array(
			'billing'  => __( 'Billing Fields', 'checkout-lift-for-woocommerce' ),
			'shipping' => __( 'Shipping Fields', 'checkout-lift-for-woocommerce' ),
			'order'    => __( 'Additional Fields', 'checkout-lift-for-woocommerce' ),
		);

		return $labels[ $section ] ?? $section;
	}

	/**
	 * Translate a validation code into something a merchant can act on.
	 *
	 * @param string $code Internal error code, possibly carrying context.
	 */
	private function error_message( string $code ): string {
		$parts   = explode( '|', $code, 2 );
		$context = isset( $parts[1] ) ? sanitize_text_field( $parts[1] ) : '';

		$message = match ( $parts[0] ) {
			'invalid_section' => __( 'A section is not one of billing, shipping, or order.', 'checkout-lift-for-woocommerce' ),
			'invalid_field' => __( 'A field key may only contain lowercase letters, numbers, and underscores.', 'checkout-lift-for-woocommerce' ),
			'unknown_field_setting' => __( 'A field carries a setting this version does not recognise.', 'checkout-lift-for-woocommerce' ),
			default => __( 'The submitted fields have an invalid structure.', 'checkout-lift-for-woocommerce' ),
		};

		return '' === $context ? $message : $message . ' (' . $context . ')';
	}
}
