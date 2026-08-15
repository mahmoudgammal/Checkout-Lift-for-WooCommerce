<?php
/**
 * Admin action for checkout-field settings.
 *
 * @package SimpleCheckoutFieldsEditor
 */

declare( strict_types=1 );

namespace DesignTich\SimpleCheckoutFieldsEditor\Modules\CheckoutFieldsAdmin;

use DesignTich\SimpleCheckoutFieldsEditor\Core\Settings\CheckoutFieldsRepository;
use UnexpectedValueException;

/**
 * Owns the authenticated settings mutation endpoint.
 */
final class SaveSettingsAction {

	/**
	 * Create the settings action.
	 *
	 * @param CheckoutFieldsRepository $checkout_fields Checkout settings storage.
	 * @param SettingsPayloadDecoder   $decoder Request JSON decoder.
	 * @param FieldSettingsSanitizer   $sanitizer Field schema sanitizer.
	 */
	public function __construct(
		private CheckoutFieldsRepository $checkout_fields,
		private SettingsPayloadDecoder $decoder,
		private FieldSettingsSanitizer $sanitizer
	) {
	}

	/**
	 * Register the AJAX mutation.
	 */
	public function register(): void {
		add_action( 'wp_ajax_scfe_save_settings', array( $this, 'handle' ) );
	}

	/**
	 * Authenticate, validate, and persist one settings request.
	 */
	public function handle(): void {
		check_ajax_referer( 'scfe_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( __( 'Permission denied.', 'checkout-lift-for-woocommerce' ) );
		}

		try {
			$settings = $this->sanitized_request_settings();
		} catch ( UnexpectedValueException $exception ) {
			wp_send_json_error( $this->error_message( $exception->getMessage() ) );
		}

		if ( ! $this->checkout_fields->replace( $settings ) ) {
			wp_send_json_error( __( 'Checkout field settings could not be saved.', 'checkout-lift-for-woocommerce' ) );
		}

		update_option( 'scfe_disable_shipping', $this->shipping_is_disabled() );
		do_action( 'scfe_save_settings' );
		wp_send_json_success( __( 'Settings saved.', 'checkout-lift-for-woocommerce' ) );
	}

	/**
	 * Read and sanitize the submitted field settings.
	 *
	 * @return array<string, array<string, array<string, mixed>>>
	 */
	private function sanitized_request_settings(): array {
		$raw_settings = '';

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- handle() verifies the checkout-fields admin nonce.
		if ( isset( $_POST['settings'] ) && is_string( $_POST['settings'] ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- raw JSON is decoded and schema-sanitized below.
			$raw_settings = wp_unslash( $_POST['settings'] );
		}

		return $this->sanitizer->sanitize( $this->decoder->decode( $raw_settings ) );
	}

	/**
	 * Return the submitted shipping-address setting.
	 */
	private function shipping_is_disabled(): bool {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- handle() verifies the checkout-fields admin nonce.
		if ( ! isset( $_POST['disable_shipping'] ) || ! is_string( $_POST['disable_shipping'] ) ) {
			return false;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- handle() verifies the checkout-fields admin nonce.
		$value = sanitize_text_field( wp_unslash( $_POST['disable_shipping'] ) );

		return in_array( $value, array( '1', 'true', 'on' ), true );
	}

	/**
	 * Translate a safe validation error code.
	 *
	 * @param string $code Internal validation error code.
	 */
	private function error_message( string $code ): string {
		$parts   = explode( '|', $code, 2 );
		$context = isset( $parts[1] ) ? sanitize_text_field( $parts[1] ) : '';

		$message = match ( $parts[0] ) {
			'too_large' => __( 'Settings data is larger than 1 MB.', 'checkout-lift-for-woocommerce' ),
			'invalid_json' => __( 'Settings data is not valid JSON.', 'checkout-lift-for-woocommerce' ),
			'invalid_section' => __( 'A checkout section is not one of billing, shipping, or order.', 'checkout-lift-for-woocommerce' ),
			'invalid_field' => __( 'A field key may only contain lowercase letters, numbers, and underscores.', 'checkout-lift-for-woocommerce' ),
			'unknown_field_setting' => __( 'A field carries a setting this version does not recognise.', 'checkout-lift-for-woocommerce' ),
			'invalid_setting_value' => __( 'A field setting has a value of an unsupported type.', 'checkout-lift-for-woocommerce' ),
			'invalid_nested_key' => __( 'A nested setting key contains unsupported characters.', 'checkout-lift-for-woocommerce' ),
			'settings_too_deep' => __( 'A field setting is nested more deeply than allowed.', 'checkout-lift-for-woocommerce' ),
			'invalid_file_size' => __( 'Maximum file size must be a number.', 'checkout-lift-for-woocommerce' ),
			'invalid_repeater_min', 'invalid_repeater_max' => __( 'Repeater limits must be numbers.', 'checkout-lift-for-woocommerce' ),
			default => __( 'Settings data has an invalid structure.', 'checkout-lift-for-woocommerce' ),
		};

		if ( '' === $context ) {
			return $message;
		}

		/* translators: 1: error description, 2: the offending key or value. */
		return sprintf( __( '%1$s (offending value: "%2$s")', 'checkout-lift-for-woocommerce' ), $message, $context );
	}
}
