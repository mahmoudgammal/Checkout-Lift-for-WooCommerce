/**
 * The field type chooser.
 *
 * @package SimpleCheckoutFieldsEditor
 */

import { __ } from '@wordpress/i18n';

import { availableTypes } from './fieldTypes';
import { typeNote } from './CheckoutNotice';

/**
 * A glyph per type.
 *
 * Characters rather than icon components, so the picker costs nothing to load
 * and cannot drift out of step with an icon set's naming.
 */
const GLYPHS = {
	text: 'T',
	number: '#',
	email: '@',
	url: '↗',
	select: '⌄',
	textarea: '¶',
};

/**
 * Let the merchant pick what kind of field this is.
 *
 * Twelve types in a list is a scroll and a squint. As tiles they are one
 * glance, which is the whole reason this is not a dropdown.
 *
 * @param {Object}   props          Component props.
 * @param {string}   props.value    Chosen type.
 * @param {Function} props.onChange Called with the new type.
 * @return {JSX.Element} Picker.
 */
export default function TypePicker( { value, onChange } ) {
	return (
		<div>
			<div className="components-base-control__label" style={ { marginBottom: 8 } }>
				{ __( 'Field type', 'checkout-lift-for-woocommerce' ) }
			</div>

			<div className="scfe-types" role="radiogroup">
				{ availableTypes( value ).map( ( type ) => {
					const note = typeNote( type.value );

					return (
						<button
							type="button"
							key={ type.value }
							role="radio"
							aria-checked={ value === type.value }
							className={ [
								'scfe-type',
								value === type.value ? 'is-selected' : '',
								note ? 'is-limited' : '',
							]
								.filter( Boolean )
								.join( ' ' ) }
							onClick={ () => onChange( type.value ) }
						>
							<span className="scfe-type__icon" aria-hidden="true">
								{ type.icon || GLYPHS[ type.value ] || '•' }
							</span>
							<span>{ type.label }</span>
							{ note && <span className="scfe-type__note">{ note }</span> }
						</button>
					);
				} ) }
			</div>
		</div>
	);
}
