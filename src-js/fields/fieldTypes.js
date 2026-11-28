/**
 * The kinds of field a merchant can add.
 *
 * Kept apart from the modal so the picker, the annotations and the tests all
 * read the same list rather than three copies of it.
 *
 * @package SimpleCheckoutFieldsEditor
 */

import { __ } from '@wordpress/i18n';

/**
 * Types a custom field may be, in the order the old editor offered them.
 */
export const TYPES = [
	{ value: 'text', label: __( 'Text', 'checkout-lift-for-woocommerce' ) },
	{ value: 'number', label: __( 'Number', 'checkout-lift-for-woocommerce' ) },
	{ value: 'email', label: __( 'Email', 'checkout-lift-for-woocommerce' ) },
	{ value: 'url', label: __( 'URL', 'checkout-lift-for-woocommerce' ) },
	{ value: 'select', label: __( 'Select', 'checkout-lift-for-woocommerce' ) },
	{ value: 'textarea', label: __( 'Textarea', 'checkout-lift-for-woocommerce' ) },
];

/**
 * Return field types whose runtime module is available.
 *
 * A currently stored type remains available while editing so switching a
 * module off never makes an existing field impossible to inspect or change.
 *
 * @param {string} currentType Existing field type, when editing.
 * @return {Array} Available types.
 */
export function availableTypes( currentType = '' ) {
	const modules = window.scfe_admin?.modules || {};
	const addOnTypes = Array.isArray( window.scfe_admin_extensions?.FieldTypes )
		? window.scfe_admin_extensions.FieldTypes
		: [];
	const available = [ ...TYPES, ...addOnTypes ].filter( ( type ) => {
		return ! type.module || false !== modules[ type.module ] || type.value === currentType;
	} );

	if ( currentType && ! available.some( ( type ) => type.value === currentType ) ) {
		available.push( { value: currentType, label: currentType } );
	}

	return available;
}

/**
 * The human label for a stored field type.
 *
 * @param {string} value Stored type.
 * @return {string} Label, or the stored value when the type is unknown.
 */
export function typeLabel( value ) {
	const type = availableTypes( value ).find( ( candidate ) => candidate.value === value );

	return type ? type.label : value;
}
