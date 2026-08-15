<?php
/**
 * Sanitize checkout-field settings at the admin boundary.
 *
 * @package SimpleCheckoutFieldsEditor
 */

declare( strict_types=1 );

namespace DesignTich\SimpleCheckoutFieldsEditor\Modules\CheckoutFieldsAdmin;

use UnexpectedValueException;

/**
 * Preserves the current option shape while rejecting unknown top-level schema.
 */
final class FieldSettingsSanitizer {

	private const MAX_DEPTH = 8;

	private const SECTIONS = array( 'billing', 'shipping', 'order' );

	private const FIELD_KEYS = array(
		'enabled',
		'required',
		'label',
		'placeholder',
		'width',
		'class',
		'custom',
		'type',
		'default',
		'options',
		'conditions',
		'cart_conditions',
		'payment_conditions',
		'shipping_conditions',
		'file_types',
		'max_file_size',
		'fee_amount',
		'fee_type',
		'fee_taxable',
		'repeater_min',
		'repeater_max',
		'repeater_button',
		'repeater_fields',
		'field_dependencies',
		'datetime_min_date',
		'datetime_max_date',
	);

	private const MULTILINE_KEYS = array( 'options', 'repeater_fields' );

	/**
	 * Sanitize all checkout sections.
	 *
	 * @param array<mixed> $settings Decoded request settings.
	 * @return array<string, array<string, array<string, mixed>>>
	 *
	 * @throws UnexpectedValueException When the submitted schema is invalid.
	 */
	public function sanitize( array $settings ): array {
		$sanitized = array();

		foreach ( $settings as $section => $fields ) {
			$this->assert_section( $section, $fields );
			$sanitized[ $section ] = $this->sanitize_fields( $fields );
		}

		return $sanitized;
	}

	/**
	 * Validate a root checkout section.
	 *
	 * @param mixed $section Section key.
	 * @param mixed $fields Section value.
	 *
	 * @throws UnexpectedValueException When the section is invalid.
	 */
	private function assert_section( $section, $fields ): void {
		if ( ! is_string( $section ) || ! in_array( $section, self::SECTIONS, true ) || ! is_array( $fields ) ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Internal error code; SaveSettingsAction sanitizes it before output.
			throw $this->fail( 'invalid_section', $section );
		}
	}

	/**
	 * Abort sanitization with a code and the offending identifier.
	 *
	 * The identifier is what makes the failure fixable: without it a merchant
	 * only learns that some field somewhere is wrong.
	 *
	 * Returns the exception rather than throwing it, so call sites read
	 * `throw $this->fail( ... )`. That keeps control flow explicit without the
	 * PHP 8.1 `never` return type, which this plugin cannot use while it
	 * supports PHP 8.0.
	 *
	 * @param string $code Machine-readable error code.
	 * @param mixed  $context Offending key or value.
	 */
	private function fail( string $code, $context ): UnexpectedValueException {
		$readable = is_scalar( $context ) ? (string) $context : gettype( $context );

		if ( 64 < strlen( $readable ) ) {
			$readable = substr( $readable, 0, 64 ) . '...';
		}

		return new UnexpectedValueException( $code . '|' . $readable );
	}

	/**
	 * Sanitize fields in one section without changing their order.
	 *
	 * @param array<mixed> $fields Submitted section.
	 * @return array<string, array<string, mixed>>
	 */
	private function sanitize_fields( array $fields ): array {
		$sanitized = array();

		foreach ( $fields as $field_key => $field_settings ) {
			$this->assert_field( $field_key, $field_settings );
			$sanitized[ $field_key ] = $this->sanitize_field_settings( $field_settings );
		}

		return $sanitized;
	}

	/**
	 * Validate one field container and identifier.
	 *
	 * @param mixed $field_key Field identifier.
	 * @param mixed $settings Field settings.
	 *
	 * @throws UnexpectedValueException When the field is invalid.
	 */
	private function assert_field( $field_key, $settings ): void {
		if ( ! is_string( $field_key ) || 1 !== preg_match( '/^[a-z0-9_]{1,191}$/', $field_key ) || ! is_array( $settings ) ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Internal error code; SaveSettingsAction sanitizes it before output.
			throw $this->fail( 'invalid_field', $field_key );
		}
	}

	/**
	 * Sanitize the allowlisted properties for one field.
	 *
	 * @param array<mixed> $settings Submitted field settings.
	 * @return array<string, mixed>
	 *
	 * @throws UnexpectedValueException When a property is not allowlisted.
	 */
	private function sanitize_field_settings( array $settings ): array {
		$sanitized = array();

		foreach ( $settings as $key => $value ) {
			if ( ! is_string( $key ) || ! in_array( $key, self::FIELD_KEYS, true ) ) {
				// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Internal error code; SaveSettingsAction sanitizes it before output.
				throw $this->fail( 'unknown_field_setting', $key );
			}

			$sanitized[ $key ] = $this->sanitize_value( $value, $key, 0 );
		}

		return $this->normalize_repeater_limits( $sanitized );
	}

	/**
	 * Sanitize a bounded nested setting value.
	 *
	 * @param mixed  $value Submitted value.
	 * @param string $key Parent property key.
	 * @param int    $depth Current nested depth.
	 * @return bool|int|float|string|array<mixed>
	 *
	 * @throws UnexpectedValueException When a value is invalid or too deeply nested.
	 */
	private function sanitize_value( $value, string $key, int $depth ) {
		if ( self::MAX_DEPTH < $depth ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Internal error code; SaveSettingsAction sanitizes it before output.
			throw $this->fail( 'settings_too_deep', $key );
		}

		if ( 'max_file_size' === $key ) {
			return $this->sanitize_file_size( $value );
		}

		if ( 'repeater_min' === $key ) {
			return $this->sanitize_bounded_integer( $value, 1, 20, 'invalid_repeater_min' );
		}

		if ( 'repeater_max' === $key ) {
			return $this->sanitize_bounded_integer( $value, 1, 50, 'invalid_repeater_max' );
		}

		if ( is_array( $value ) ) {
			return $this->sanitize_nested_array( $value, $key, $depth + 1 );
		}

		if ( is_bool( $value ) || is_int( $value ) || is_float( $value ) ) {
			return $value;
		}

		if ( ! is_string( $value ) ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Internal error code; SaveSettingsAction sanitizes it before output.
			throw $this->fail( 'invalid_setting_value', $key );
		}

		return in_array( $key, self::MULTILINE_KEYS, true )
			? sanitize_textarea_field( $value )
			: sanitize_text_field( $value );
	}

	/**
	 * Enforce the approved one-to-five-megabyte upload setting.
	 *
	 * @param mixed $value Submitted maximum file size.
	 *
	 * @throws UnexpectedValueException When the value is not numeric.
	 */
	private function sanitize_file_size( $value ): int {
		return $this->sanitize_bounded_integer( $value, 1, 5, 'invalid_file_size' );
	}

	/**
	 * Sanitize an integer setting into an approved range.
	 *
	 * @param mixed  $value Submitted value.
	 * @param int    $minimum Minimum allowed value.
	 * @param int    $maximum Maximum allowed value.
	 * @param string $error_code Exception code.
	 *
	 * @throws UnexpectedValueException When the value is not numeric.
	 */
	private function sanitize_bounded_integer( $value, int $minimum, int $maximum, string $error_code ): int {
		if ( ! is_numeric( $value ) ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Internal error code; SaveSettingsAction sanitizes it before output.
			throw $this->fail( $error_code, $value );
		}

		return max( $minimum, min( $maximum, (int) $value ) );
	}

	/**
	 * Keep repeater maximum at least as large as the minimum.
	 *
	 * @param array<string, mixed> $settings Sanitized field settings.
	 * @return array<string, mixed>
	 */
	private function normalize_repeater_limits( array $settings ): array {
		if ( isset( $settings['repeater_min'], $settings['repeater_max'] ) && $settings['repeater_max'] < $settings['repeater_min'] ) {
			$settings['repeater_max'] = $settings['repeater_min'];
		}

		return $settings;
	}

	/**
	 * Sanitize a nested list or map while preserving list indexes.
	 *
	 * @param array<mixed> $values Submitted nested values.
	 * @param string       $parent_key Parent property key.
	 * @param int          $depth Current nested depth.
	 * @return array<mixed>
	 */
	private function sanitize_nested_array( array $values, string $parent_key, int $depth ): array {
		$sanitized = array();

		foreach ( $values as $key => $value ) {
			$sanitized_key               = $this->sanitize_nested_key( $key );
			$sanitized[ $sanitized_key ] = $this->sanitize_value( $value, $parent_key, $depth );
		}

		return $sanitized;
	}

	/**
	 * Validate a nested list index or map key without silently renaming it.
	 *
	 * @param mixed $key Submitted key.
	 * @return int|string
	 *
	 * @throws UnexpectedValueException When a nested key is invalid.
	 */
	private function sanitize_nested_key( $key ) {
		if ( is_int( $key ) && 0 <= $key ) {
			return $key;
		}

		if ( is_string( $key ) && 1 === preg_match( '/^[a-z0-9_]{1,64}$/', $key ) ) {
			return $key;
		}

		// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Internal error code; SaveSettingsAction sanitizes it before output.
		throw $this->fail( 'invalid_nested_key', $key );
	}
}
