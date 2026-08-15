<?php
/**
 * Per-field width styles for the block checkout.
 *
 * @package SimpleCheckoutFieldsEditor
 */

declare( strict_types=1 );

namespace DesignTich\SimpleCheckoutFieldsEditor\Modules\CheckoutFields;

use DesignTich\SimpleCheckoutFieldsEditor\Core\Settings\CheckoutFieldsRepository;

/**
 * Builds the CSS that gives each block checkout field its configured width.
 *
 * Width cannot travel with the rest of the field settings. The country locale
 * carries a `class`, which is where `form-row-first` lives on the classic
 * checkout, but `CartCheckoutUtils::get_country_data()` deletes that key before
 * the locale reaches the browser. Nothing about width survives that route.
 *
 * What the block checkout does is lay its address fields out with flexbox, one
 * wrapper per field carrying a class named after the field:
 * `wc-block-components-address-form__city`. So width is set the way the block
 * checkout sets its own, with CSS, and nothing else about the field is touched.
 *
 * The wrapper class does not say which address it belongs to, so one width
 * applies to the billing and shipping forms alike. Where the two sections
 * disagree the wider setting wins: a field wider than intended still reads,
 * while one narrower than intended can cut off what the customer typed.
 */
final class BlocksFieldWidthStyles {

	private const DEFAULT_WIDTH = 100;

	/**
	 * Fields the block checkout lays out this way.
	 *
	 * The same list the locale governs, and for the same reason: these are the
	 * fields WooCommerce renders inside the address form.
	 */
	private const ADDRESS_FIELDS = array(
		'first_name',
		'last_name',
		'company',
		'country',
		'address_1',
		'address_2',
		'city',
		'state',
		'postcode',
		'phone',
	);

	/**
	 * Create the width style builder.
	 *
	 * @param CheckoutFieldsRepository $checkout_fields Checkout field settings.
	 */
	public function __construct( private CheckoutFieldsRepository $checkout_fields ) {
	}

	/**
	 * Return CSS for every address field narrower than full width.
	 *
	 * Fields left at full width are skipped, that being what the block
	 * checkout already does with anything it has no rule for.
	 */
	public function css(): string {
		$rules = array();

		foreach ( $this->widths() as $field => $width ) {
			// Match WooCommerce's flex layout, outrank its three-class selector, and account for its 12px gap.
			$rules[] = sprintf(
				'.wc-block-checkout__form .wc-block-components-address-form-wrapper'
					. ' .wc-block-components-address-form .wc-block-components-address-form__%1$s'
					. '{flex-basis:calc(%2$d%% - 12px);max-width:calc(%2$d%% - 12px)}',
				$field,
				$width
			);
		}

		return implode( '', $rules );
	}

	/**
	 * Reduce the stored sections to one width per address field.
	 *
	 * @return array<string, int>
	 */
	private function widths(): array {
		$widths = array();

		foreach ( $this->checkout_fields->fields() as $section => $section_fields ) {
			if ( ! is_array( $section_fields ) ) {
				continue;
			}

			foreach ( $section_fields as $field_key => $settings ) {
				$field = $this->address_field( (string) $field_key, (string) $section );

				if ( '' === $field || ! is_array( $settings ) ) {
					continue;
				}

				// Full width participates so a narrower duplicate section cannot win.
				$widths[ $field ] = max( $widths[ $field ] ?? 0, $this->width( $settings['width'] ?? null ) );
			}
		}

		// Full width needs no generated rule.
		return array_filter(
			$widths,
			static function ( int $width ): bool {
				return self::DEFAULT_WIDTH !== $width;
			}
		);
	}

	/**
	 * Return the address field a stored key refers to.
	 *
	 * @param string $field_key Stored field key.
	 * @param string $section   Stored section.
	 */
	private function address_field( string $field_key, string $section ): string {
		$prefix = $section . '_';

		if ( ! in_array( $section, array( 'billing', 'shipping' ), true ) || ! str_starts_with( $field_key, $prefix ) ) {
			return '';
		}

		$field = substr( $field_key, strlen( $prefix ) );

		return in_array( $field, self::ADDRESS_FIELDS, true ) ? $field : '';
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
}
