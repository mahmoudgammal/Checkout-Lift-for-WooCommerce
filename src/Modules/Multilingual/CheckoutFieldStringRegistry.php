<?php
/**
 * Checkout field string registration.
 *
 * @package SimpleCheckoutFieldsEditor
 */

declare( strict_types=1 );

namespace DesignTich\SimpleCheckoutFieldsEditor\Modules\Multilingual;

/**
 * Registers field labels, placeholders, defaults, descriptions, and options.
 */
final class CheckoutFieldStringRegistry {

	/**
	 * Create the registry.
	 *
	 * @param TranslatableStringService $strings Translation string service.
	 * @param OptionsStringParser       $options Select-option parser.
	 */
	public function __construct(
		private TranslatableStringService $strings,
		private OptionsStringParser $options
	) {
	}

	/**
	 * Register strings after option replacement.
	 *
	 * @param mixed $old_value Old option value.
	 * @param mixed $new_value New option value.
	 */
	public function register_on_save( $old_value, $new_value ): void {
		if ( is_array( $new_value ) ) {
			$this->register_settings( $new_value );
		}
	}

	/**
	 * Register strings from the saved checkout settings.
	 */
	public function register_saved_settings(): void {
		$settings = get_option( 'scfe_checkout_fields', array() );

		if ( is_array( $settings ) ) {
			$this->register_settings( $settings );
		}
	}

	/**
	 * Register all checkout-field strings.
	 *
	 * @param array<string, mixed> $settings Field settings.
	 */
	public function register_settings( array $settings ): void {
		foreach ( $settings as $fields ) {
			$this->register_fields( is_array( $fields ) ? $fields : array() );
		}

		do_action( 'scfe_register_multilingual_strings', $this, $settings );
	}

	/**
	 * Register one field section.
	 *
	 * @param array<string, mixed> $fields Field section.
	 */
	private function register_fields( array $fields ): void {
		foreach ( $fields as $field_key => $field_data ) {
			if ( is_array( $field_data ) ) {
				$this->register_field( (string) $field_key, $field_data );
			}
		}
	}

	/**
	 * Register one field.
	 *
	 * @param string               $field_key Field key.
	 * @param array<string, mixed> $field_data Field settings.
	 */
	private function register_field( string $field_key, array $field_data ): void {
		$this->register_scalar( $field_key . '_label', $field_data['label'] ?? null );
		$this->register_scalar( $field_key . '_placeholder', $field_data['placeholder'] ?? null );
		$this->register_scalar( $field_key . '_default', $field_data['default'] ?? null );
		$this->register_scalar( $field_key . '_description', $field_data['description'] ?? null );
		$this->register_options( $field_key, $field_data['options'] ?? null );
	}

	/**
	 * Register one scalar string.
	 *
	 * @param string $name Translation name.
	 * @param mixed  $text Stored value.
	 */
	public function register_scalar( string $name, $text ): void {
		if ( is_string( $text ) && '' !== $text ) {
			$this->strings->register( $name, $text );
		}
	}

	/**
	 * Register select options.
	 *
	 * @param string $field_key Field key.
	 * @param mixed  $options Stored option shape.
	 */
	public function register_options( string $field_key, $options ): void {
		$labels = is_string( $options ) ? $this->options->parse( $options ) : $options;

		if ( ! is_array( $labels ) ) {
			return;
		}

		foreach ( $labels as $option_value => $option_label ) {
			$name = $field_key . '_option_' . sanitize_key( (string) $option_value );
			$this->register_scalar( $name, $option_label );
		}
	}

	/**
	 * Legacy-compatible alias used by external callbacks.
	 *
	 * @param string $name Translation name.
	 * @param string $text Text to register.
	 * @param string $title Historical translator-facing title.
	 */
	public function register_string( string $name, string $text, string $title = '' ): void {
		unset( $title );
		$this->strings->register( $name, $text );
	}

	/**
	 * Legacy-compatible translation helper used by external callbacks.
	 *
	 * @param string $name Translation name.
	 * @param string $text Text to translate.
	 */
	public function translate_string( string $name, string $text ): string {
		return $this->strings->translate( $name, $text );
	}

	/**
	 * Legacy-compatible context getter.
	 */
	public function get_context(): string {
		return $this->strings->context();
	}
}
