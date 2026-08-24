/**
 * Tells the merchant which checkout this screen can actually change.
 *
 * @package SimpleCheckoutFieldsEditor
 */

import { __ } from '@wordpress/i18n';
import { Notice } from '@wordpress/components';

/**
 * Report whether the store runs the WooCommerce Checkout block.
 *
 * @return {boolean} Whether the block checkout is configured.
 */
export function usesBlockCheckout() {
	return !! ( window.scfe_admin && window.scfe_admin.blockCheckout );
}

/**
 * How each custom field type fares on the block checkout.
 *
 * WooCommerce accepts three field types there and the editor offers twelve.
 * Four more survive as plain text, losing the input type but keeping the
 * question. The rest cannot be expressed at all, and are skipped rather than
 * rendered as something they are not.
 */
const AS_TEXT = [ 'number', 'email', 'url', 'textarea' ];
const UNSUPPORTED = [];

/**
 * Say what one field type will actually do on the block checkout.
 *
 * Only on a block checkout. On a classic one every type works, and saying so
 * against each of twelve tiles would be noise.
 *
 * @param {string} type Field type.
 * @return {string} A note, or an empty string when nothing is lost.
 */
export function typeNote( type ) {
	if ( ! usesBlockCheckout() ) {
		return '';
	}

	if ( UNSUPPORTED.includes( type ) ) {
		return __( 'not shown on the Checkout block', 'checkout-lift-for-woocommerce' );
	}

	if ( AS_TEXT.includes( type ) ) {
		return __( 'plain text on the Checkout block', 'checkout-lift-for-woocommerce' );
	}

	return '';
}

/**
 * Label a list of type options with the same notes.
 *
 * Kept for anywhere the types are shown as a list rather than as tiles.
 *
 * @param {Array} types Type options.
 * @return {Array} Type options, annotated where it matters.
 */
export function annotateTypes( types ) {
	if ( ! usesBlockCheckout() ) {
		return types;
	}

	return types.map( ( type ) => {
		const note = typeNote( type.value );

		return note ? { ...type, label: `${ type.label } — ${ note }` } : type;
	} );
}

/**
 * Which parts of a condition survive on the block checkout.
 *
 * WooCommerce evaluates conditions itself there, from rules describing the
 * cart, the checkout and the customer. Comparisons and subjects it has no way
 * to express are not carried, and because dropping one rule would widen what
 * the merchant asked for, an uncarriable rule takes its whole group with it.
 * That is the part worth saying out loud.
 */
const CARRIED_OPERATORS = [ 'is', 'is_not' ];

/**
 * Mark the entries that will not be carried.
 *
 * @param {Array}  entries Options with a `value` and a `label`.
 * @param {Array}  carried Values that survive.
 * @return {Array} The same options, annotated where it matters.
 */
function markUncarried( entries, carried ) {
	if ( ! usesBlockCheckout() ) {
		return entries;
	}

	return entries.map( ( entry ) =>
		carried.includes( entry.value )
			? entry
			: {
					...entry,
					label: `${ entry.label } — ${ __(
						'not on the Checkout block',
						'checkout-lift-for-woocommerce'
					) }`,
			  }
	);
}

/**
 * Label the comparisons a field rule may use.
 *
 * @param {Array} operators Operator options.
 * @return {Array} Operator options, annotated on a block checkout.
 */
export function annotateFieldOperators( operators ) {
	return markUncarried( operators, CARRIED_OPERATORS );
}

/**
 * Report whether a field's width will reach the block checkout.
 *
 * Only the built-in address fields are laid out in a row there, so only they
 * can be given a share of it. A custom field sits in a container that stacks
 * its children, and widening one would mean restyling a WooCommerce container
 * rather than setting a width on a field.
 *
 * @param {Object} field Field being edited.
 * @return {string} A note, or an empty string when the width applies.
 */
export function widthNote( field ) {
	if ( ! usesBlockCheckout() || ! field.custom ) {
		return '';
	}

	return __( 'not on the Checkout block', 'checkout-lift-for-woocommerce' );
}

/**
 * Explain what an unmarked rule does to the group it sits in.
 *
 * @return {JSX.Element|null} Note, or nothing on a classic checkout.
 */
export function ConditionScopeNote() {
	if ( ! usesBlockCheckout() ) {
		return null;
	}

	return (
		<p style={ { color: '#757575', fontSize: 12, marginTop: 4 } }>
			{ __(
				'Your checkout uses the Checkout block. A rule marked above cannot be expressed there, and one such rule stops its whole group from applying — the group is kept as a whole rather than quietly applying only part of what you asked for. The classic checkout is unaffected.',
				'checkout-lift-for-woocommerce'
			) }
		</p>
	);
}

/**
 * Say which checkout this screen is configuring, and where its limits are.
 *
 * Most of what this screen does reaches the block checkout. What does not is
 * marked on the setting itself rather than listed here, so the notice stays
 * short enough to read. Its job is to tell a merchant which checkout they are
 * configuring and that some settings carry a mark.
 *
 * @return {JSX.Element|null} Notice, or nothing on a classic checkout.
 */
export default function CheckoutNotice() {
	if ( ! usesBlockCheckout() ) {
		return null;
	}

	return (
		<Notice status="info" isDismissible={ false }>
			<strong>
				{ __( 'Your checkout page uses the Checkout block.', 'checkout-lift-for-woocommerce' ) }
			</strong>{ ' ' }
			{ __(
				'Most settings here apply to it, including field order, visibility, labels, widths and conditions. The few that WooCommerce cannot express on that checkout are marked where you choose them. Checkout Recovery works on both.',
				'checkout-lift-for-woocommerce'
			) }
		</Notice>
	);
}
