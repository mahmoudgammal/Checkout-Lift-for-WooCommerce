/**
 * Add and edit a checkout field.
 *
 * @package SimpleCheckoutFieldsEditor
 */

import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import {
	Button,
	CheckboxControl,
	Modal,
	SelectControl,
	TextControl,
	TextareaControl,
} from '@wordpress/components';

import Conditions from './Conditions';
import TypePicker from './TypePicker';


/**
 * Generate a key for a new custom field.
 *
 * The server only accepts lowercase letters, numbers, and underscores, and
 * base-36 of a random number produces exactly that. Deriving the key from the
 * label would look friendlier but would break on any non-Latin label, which
 * is most of them for this plugin's audience.
 *
 * @param {string} section Section the field belongs to.
 * @return {string} Field key.
 */
function generateKey( section ) {
	return `${ section }_custom_${ Math.random().toString( 36 ).slice( 2, 11 ) }`;
}

/**
 * Field editor dialog.
 *
 * @param {Object}   props          Component props.
 * @param {Object?}  props.field    Field being edited, or null when adding.
 * @param {string}   props.section  Section the field belongs to.
 * @param {Array}    props.sections Available sections.
 * @param {Function} props.onSave   Called with the field and its section.
 * @param {Function} props.onClose  Called to dismiss.
 * @return {JSX.Element} Modal.
 */
export default function FieldModal( {
	field,
	section,
	sections,
	allFields,
	paymentMethods,
	shippingMethods,
	onSave,
	onClose,
} ) {
	const adding = ! field;

	const [ target, setTarget ] = useState( section );
	const [ draft, setDraft ] = useState(
		field || {
			key: '',
			label: '',
			placeholder: '',
			width: 100,
			enabled: true,
			required: false,
			custom: true,
			type: 'text',
			default: '',
			options: '',
			settings: {},
			conditions: {},
			carried: {},
		}
	);

	const set = ( key ) => ( value ) => setDraft( { ...draft, [ key ]: value } );
	const setSetting = ( key ) => ( value ) =>
		setDraft( { ...draft, settings: { ...draft.settings, [ key ]: value } } );
	const setting = ( key, fallback = '' ) => draft.settings?.[ key ] ?? fallback;

	const submit = () => {
		if ( ! draft.label.trim() ) {
			return;
		}

		onSave(
			{ ...draft, key: draft.key || generateKey( target ) },
			target,
			adding ? null : section
		);
	};

	// A default WooCommerce field has a fixed type and key, so only its
	// presentation is editable here.
	const isCustom = !! draft.custom;
	const AddonFieldSettings = window.scfe_admin_extensions?.FieldSettings;

	return (
		<Modal
			title={
				adding
					? __( 'Add field', 'checkout-lift-for-woocommerce' )
					: __( 'Edit field', 'checkout-lift-for-woocommerce' )
			}
			onRequestClose={ onClose }
			className="scfe-modal"
			style={ { maxWidth: 720 } }
		>
			{ /* The plain settings sit on a card of their own, so they read as
			     one group rather than floating above the condition blocks. */ }
			<div className="scfe-modal__section">
			<SelectControl
				__nextHasNoMarginBottom
				__next40pxDefaultSize
				label={ __( 'Section', 'checkout-lift-for-woocommerce' ) }
				value={ target }
				options={ sections.map( ( s ) => ( { value: s.key, label: s.label } ) ) }
				onChange={ setTarget }
			/>

			{ isCustom && (
				<>
					<TypePicker value={ draft.type } onChange={ set( 'type' ) } />
					{ ! adding && (
						<p className="scfe-panel__note">
							{ __(
								'Changing the type discards settings that belonged to the old type.',
								'checkout-lift-for-woocommerce'
							) }
						</p>
					) }
				</>
			) }

			<TextControl
				__nextHasNoMarginBottom
				__next40pxDefaultSize
				label={ __( 'Label', 'checkout-lift-for-woocommerce' ) }
				value={ draft.label }
				onChange={ set( 'label' ) }
			/>

			<TextControl
				__nextHasNoMarginBottom
				__next40pxDefaultSize
				label={ __( 'Placeholder', 'checkout-lift-for-woocommerce' ) }
				value={ draft.placeholder }
				onChange={ set( 'placeholder' ) }
			/>

			{ isCustom && (
				<TextControl
					__nextHasNoMarginBottom
					__next40pxDefaultSize
					label={ __( 'Default value', 'checkout-lift-for-woocommerce' ) }
					value={ draft.default }
					onChange={ set( 'default' ) }
				/>
			) }

			{ isCustom && 'select' === draft.type && (
				<TextareaControl
					__nextHasNoMarginBottom
					rows={ 4 }
					label={ __( 'Options', 'checkout-lift-for-woocommerce' ) }
					help={ __( 'One per line, as value|Label.', 'checkout-lift-for-woocommerce' ) }
					value={ draft.options }
					onChange={ set( 'options' ) }
				/>
			) }

			{ isCustom && AddonFieldSettings && (
				<AddonFieldSettings type={ draft.type } setting={ setting } setSetting={ setSetting } />
			) }

			</div>

			<Conditions
				value={ draft.conditions }
				onChange={ set( 'conditions' ) }
				fields={ allFields.filter( ( f ) => f.key !== draft.key ) }
				payment={ paymentMethods }
				shipping={ shippingMethods }
			/>

			<div className="scfe-modal__section">
				<CheckboxControl
					__nextHasNoMarginBottom
					label={ __( 'Required', 'checkout-lift-for-woocommerce' ) }
					checked={ !! draft.required }
					onChange={ set( 'required' ) }
				/>

				<CheckboxControl
					__nextHasNoMarginBottom
					label={ __( 'Enabled', 'checkout-lift-for-woocommerce' ) }
					checked={ !! draft.enabled }
					onChange={ set( 'enabled' ) }
				/>
			</div>

			<p style={ { marginTop: 24 } }>
				<Button variant="primary" onClick={ submit } disabled={ ! draft.label.trim() }>
					{ adding
						? __( 'Add field', 'checkout-lift-for-woocommerce' )
						: __( 'Update field', 'checkout-lift-for-woocommerce' ) }
				</Button>
				<Button variant="tertiary" onClick={ onClose } style={ { marginInlineStart: 8 } }>
					{ __( 'Cancel', 'checkout-lift-for-woocommerce' ) }
				</Button>
			</p>
		</Modal>
	);
}
