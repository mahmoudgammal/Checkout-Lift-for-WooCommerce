<?php
/**
 * Per-field width styles for classic checkout.
 *
 * @package SimpleCheckoutFieldsEditor
 */

declare( strict_types=1 );

namespace DesignTich\SimpleCheckoutFieldsEditor\Modules\CheckoutFields;

use DesignTich\SimpleCheckoutFieldsEditor\Core\Settings\CheckoutFieldsRepository;

/**
 * Builds the inline CSS that gives each field wrapper its configured width.
 *
 * WooCommerce renders `custom_attributes` on the input, never on the wrapping
 * `<p class="form-row">`. A custom property set on the input therefore cannot
 * be read by a rule targeting the wrapper, because custom properties inherit
 * downwards. Emitting one rule per wrapper id is the only placement that both
 * reaches the wrapper and supports arbitrary percentages.
 */
final class FieldWidthStyles {

	private const DEFAULT_WIDTH = 100;

	/**
	 * Create the width style builder.
	 *
	 * @param CheckoutFieldsRepository $checkout_fields Checkout field settings.
	 */
	public function __construct( private CheckoutFieldsRepository $checkout_fields ) {
	}

	/**
	 * Return inline CSS for every field narrower than full width.
	 *
	 * Fields left at 100 percent are skipped because the stylesheet already
	 * defaults to full width.
	 */
	public function css(): string {
		$rules = array();

		foreach ( $this->checkout_fields->fields() as $section_fields ) {
			foreach ( $section_fields as $field_key => $settings ) {
				$width = $this->width( is_array( $settings ) ? ( $settings['width'] ?? null ) : null );

				if ( self::DEFAULT_WIDTH === $width || ! $this->is_safe_key( $field_key ) ) {
					continue;
				}

				// The gap subtraction is baked in here so the stylesheet can
				// default to a clean 100% for every unconfigured field.
				$rules[] = sprintf( '#%s_field{--scfe-basis:calc(%d%% - 15px)}', $field_key, $width );
			}
		}

		return implode( '', $rules );
	}

	/**
	 * Normalize the configured percentage to the existing 1-100 range.
	 *
	 * @param mixed $stored_width Stored width.
	 */
	private function width( $stored_width ): int {
		if ( ! is_numeric( $stored_width ) ) {
			return self::DEFAULT_WIDTH;
		}

		return max( 1, min( 100, (int) $stored_width ) );
	}

	/**
	 * Check that a key is safe to interpolate into a CSS selector.
	 *
	 * @param mixed $field_key Stored field key.
	 */
	private function is_safe_key( $field_key ): bool {
		return is_string( $field_key ) && 1 === preg_match( '/^[a-z0-9_]{1,191}$/', $field_key );
	}
}
