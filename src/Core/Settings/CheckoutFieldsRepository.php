<?php
/**
 * Access to the existing checkout-fields option.
 *
 * @package SimpleCheckoutFieldsEditor
 */

declare( strict_types=1 );

namespace DesignTich\SimpleCheckoutFieldsEditor\Core\Settings;

/**
 * Reads the current checkout-fields shape once per request.
 */
final class CheckoutFieldsRepository {

	private const OPTION_NAME = 'scfe_checkout_fields';

	/**
	 * Validated fields cached for this request.
	 *
	 * @var array<string, array<string, array<string, mixed>>>|null
	 */
	private ?array $cached_fields = null;

	/**
	 * Return the stored checkout fields or an empty array for malformed data.
	 *
	 * @return array<string, array<string, array<string, mixed>>>
	 */
	public function fields(): array {
		if ( null !== $this->cached_fields ) {
			return $this->cached_fields;
		}

		$stored_fields       = get_option( self::OPTION_NAME, array() );
		$this->cached_fields = $this->is_valid_fields( $stored_fields ) ? $stored_fields : array();

		return $this->cached_fields;
	}

	/**
	 * Replace the checkout-fields option after validating its container shape.
	 *
	 * @param array<mixed> $checkout_fields Sanitized checkout fields.
	 */
	public function replace( array $checkout_fields ): bool {
		if ( ! $this->is_valid_fields( $checkout_fields ) ) {
			return false;
		}

		if ( $checkout_fields === $this->fields() ) {
			return true;
		}

		if ( ! update_option( self::OPTION_NAME, $checkout_fields ) ) {
			return false;
		}

		$this->cached_fields = $checkout_fields;

		return true;
	}

	/**
	 * Check the minimum existing shape required by every field consumer.
	 *
	 * @param mixed $stored_fields Value returned by WordPress.
	 */
	private function is_valid_fields( $stored_fields ): bool {
		if ( ! is_array( $stored_fields ) ) {
			return false;
		}

		foreach ( $stored_fields as $section_key => $section_fields ) {
			if ( ! is_string( $section_key ) || ! is_array( $section_fields ) ) {
				return false;
			}

			if ( ! $this->is_valid_section( $section_fields ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Check field keys and configuration containers without changing leaf data.
	 *
	 * @param array<mixed> $section_fields Fields stored for one checkout section.
	 */
	private function is_valid_section( array $section_fields ): bool {
		foreach ( $section_fields as $field_key => $field_config ) {
			if ( ! is_string( $field_key ) || '' === $field_key || ! is_array( $field_config ) ) {
				return false;
			}
		}

		return true;
	}
}
