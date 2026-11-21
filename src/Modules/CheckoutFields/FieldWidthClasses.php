<?php
/**
 * Wrapper classes for a field whose width the editor controls.
 *
 * @package SimpleCheckoutFieldsEditor
 */

declare( strict_types=1 );

namespace DesignTich\SimpleCheckoutFieldsEditor\Modules\CheckoutFields;

/**
 * Swaps WooCommerce's layout classes for the editor's width class.
 *
 * Only the three layout classes are removed, because they set their own float
 * and width. Every other class stays: WooCommerce's checkout script refreshes
 * totals from `address-field` and `update_totals_on_change`, and its address
 * script relabels `address-field` rows when the country changes.
 */
final class FieldWidthClasses {

	/**
	 * WooCommerce classes that size a row and would fight the editor's width.
	 */
	private const LAYOUT_CLASSES = array( 'form-row-first', 'form-row-last', 'form-row-wide' );

	/**
	 * Width class styled by the plugin's checkout stylesheet.
	 */
	private const WIDTH_CLASS = 'scfe-field-width';

	/**
	 * Return the field's classes with the layout classes replaced.
	 *
	 * @param mixed $classes Current `class` argument of the field.
	 * @return list<string>
	 */
	public static function replace_layout( $classes ): array {
		$kept = array();

		foreach ( is_array( $classes ) ? $classes : array() as $class_name ) {
			if ( is_string( $class_name ) && ! in_array( $class_name, self::LAYOUT_CLASSES, true ) ) {
				$kept[] = $class_name;
			}
		}

		$kept[] = self::WIDTH_CLASS;

		return array_values( array_unique( $kept ) );
	}
}
