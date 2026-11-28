/**
 * Checkout fields editor.
 *
 * @package SimpleCheckoutFieldsEditor
 */

import { useEffect, useState } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import { __ } from '@wordpress/i18n';
import {
	Button,
	CheckboxControl,
	Notice,
	Spinner,
	TabPanel,
	TextControl,
} from '@wordpress/components';

import FieldModal from './FieldModal';
import { moveField, upsertField, removeField, updateField } from './fieldOperations';
import Tools from './Tools';
import CheckoutNotice, { widthNote } from './CheckoutNotice';
import { typeLabel } from './fieldTypes';

const PATH = '/scfe/v1/fields';

/**
 * The checkout field editor.
 *
 * Every field is edited in place. The modal, reordering, and condition
 * builders are still on the older screen; anything this editor does not
 * manage travels back to the server untouched, so saving here cannot discard
 * settings made there.
 *
 * @return {JSX.Element} Editor.
 */
function FieldList() {
	const [ data, setData ] = useState( null );
	const [ saving, setSaving ] = useState( false );
	const [ notice, setNotice ] = useState( null );
	const [ dirty, setDirty ] = useState( false );
	const [ dragging, setDragging ] = useState( null );
	const [ editing, setEditing ] = useState( null );
	const [ selected, setSelected ] = useState( null );

	useEffect( () => {
		apiFetch( { path: PATH } )
			.then( ( loaded ) => {
				setData( loaded );

				// The settings panel opens on something rather than on a blank
				// space, so the screen explains itself without a first click.
				const first = shownSections( loaded ).find( ( s ) => s.fields.length > 0 );

				if ( first ) {
					setSelected( { section: first.key, key: first.fields[ 0 ].key } );
				}
			} )
			.catch( ( e ) => setNotice( { status: 'error', text: e.message } ) );
	}, [] );

	// Leaving with unsaved edits loses them silently, which is the one thing
	// an editor must never do quietly.
	useEffect( () => {
		if ( ! dirty ) {
			return undefined;
		}

		const warn = ( event ) => {
			event.preventDefault();
			event.returnValue = '';
		};

		window.addEventListener( 'beforeunload', warn );
		return () => window.removeEventListener( 'beforeunload', warn );
	}, [ dirty ] );

	if ( ! data ) {
		return notice ? (
			<Notice status="error" isDismissible={ false }>
				{ notice.text }
			</Notice>
		) : (
			<Spinner />
		);
	}

	const update = ( sectionKey, fieldKey, changes ) => {
		setDirty( true );
		setData( { ...data, sections: updateField( data.sections, sectionKey, fieldKey, changes ) } );
	};

	const move = ( sectionKey, from, to ) => {
		setDirty( true );
		setData( { ...data, sections: moveField( data.sections, sectionKey, from, to ) } );
	};

	const applyField = ( field, target, original ) => {
		setDirty( true );
		setData( { ...data, sections: upsertField( data.sections, field, target, original ) } );
		setEditing( null );
	};

	/**
	 * Remove a custom field.
	 *
	 * Only custom fields can be removed. A default WooCommerce field that
	 * disappeared from this list would simply return on the next load, so
	 * offering to delete one would be a lie; disabling is what hides it.
	 *
	 * @param {string} sectionKey Section the field is in.
	 * @param {Object} field      Field to remove.
	 */
	const remove = ( sectionKey, field ) => {
		// eslint-disable-next-line no-alert
		if ( ! window.confirm( __( 'Remove this field?', 'checkout-lift-for-woocommerce' ) ) ) {
			return;
		}

		setDirty( true );
		setData( { ...data, sections: removeField( data.sections, sectionKey, field.key ) } );
	};

	const save = () => {
		setSaving( true );
		setNotice( null );

		apiFetch( { path: PATH, method: 'POST', data } )
			.then( ( saved ) => {
				setData( saved );
				setDirty( false );
				setNotice( {
					status: 'success',
					text: __( 'Fields saved.', 'checkout-lift-for-woocommerce' ),
				} );
			} )
			.catch( ( e ) =>
				setNotice( {
					status: 'error',
					text: e.message,
					// A conflict is the one error a retry cannot fix, so the
					// way out is offered rather than described.
					reload: 'scfe_conflict' === e.code,
				} )
			)
			.finally( () => setSaving( false ) );
	};

	/**
	 * The field the settings panel is editing, looked up fresh each render.
	 *
	 * Holding the field itself in state would leave the panel showing a stale
	 * copy after an edit, or after a reorder moved it.
	 */
	const current = ( () => {
		if ( ! selected ) {
			return null;
		}

		const section = data.sections.find( ( s ) => s.key === selected.section );
		const field = section && section.fields.find( ( f ) => f.key === selected.key );

		return field
			? { section: selected.section, sectionLabel: section.label, field }
			: null;
	} )();

	return (
		<>
			{ notice && (
				<Notice status={ notice.status } onRemove={ () => setNotice( null ) }>
					{ notice.text }
					{ notice.reload && (
						<Button
							variant="link"
							style={ { marginInlineStart: 8 } }
							onClick={ () => window.location.reload() }
						>
							{ __( 'Reload now', 'checkout-lift-for-woocommerce' ) }
						</Button>
					) }
				</Notice>
			) }

			<div className="scfe-editor">
				<div>
					{ shownSections( data ).map( ( section ) => (
						<div className="scfe-card" key={ section.key }>
							<div className="scfe-card__head">
								<span className="scfe-card__title">{ section.label }</span>
								<span className="scfe-card__count">{ section.fields.length }</span>
							</div>

							<div className="scfe-rows">
								{ section.fields.map( ( field, index ) => {
									const isDragging =
										dragging &&
										dragging.section === section.key &&
										dragging.index === index;
									const isSelected =
										selected &&
										selected.section === section.key &&
										selected.key === field.key;

									return (
										<div
											key={ field.key }
											className={ [
												'scfe-row',
												isSelected ? 'is-selected' : '',
												isDragging ? 'is-dragging' : '',
												field.enabled ? '' : 'is-off',
											]
												.filter( Boolean )
												.join( ' ' ) }
											draggable
											onClick={ () =>
												setSelected( { section: section.key, key: field.key } )
											}
											onDragStart={ () =>
												setDragging( { section: section.key, index } )
											}
											onDragOver={ ( e ) => {
												if ( dragging && dragging.section === section.key ) {
													e.preventDefault();
												}
											} }
											onDrop={ () => {
												if ( dragging && dragging.section === section.key ) {
													move( section.key, dragging.index, index );
												}
												setDragging( null );
											} }
											onDragEnd={ () => setDragging( null ) }
										>
											<span className="scfe-row__grip" aria-hidden="true">
												&#10247;
											</span>

											<span className="scfe-row__name">
												<div className="scfe-row__label">
													{ field.label || field.key }
												</div>
												<div className="scfe-row__tags">
													<span className="scfe-row__key">{ field.key }</span>
													{ field.custom && (
														<span className="scfe-tag is-brand">{ typeLabel( field.type ) }</span>
													) }
													{ field.required && (
														<span className="scfe-tag">
															{ __( 'required', 'checkout-lift-for-woocommerce' ) }
														</span>
													) }
													{ ! field.enabled && (
														<span className="scfe-tag">
															{ __( 'hidden', 'checkout-lift-for-woocommerce' ) }
														</span>
													) }
													{ Object.keys( field.conditions || {} ).length > 0 && (
														<span className="scfe-tag is-accent">
															{ __( 'conditional', 'checkout-lift-for-woocommerce' ) }
														</span>
													) }
												</div>
											</span>

											<span className="scfe-row__actions">
												{ /* Dragging is not reachable by keyboard, so the
												     same move is offered as buttons. */ }
												<Button
													size="small"
													icon="arrow-up-alt2"
													disabled={ 0 === index }
													label={ __( 'Move up', 'checkout-lift-for-woocommerce' ) }
													onClick={ () => move( section.key, index, index - 1 ) }
												/>
												<Button
													size="small"
													icon="arrow-down-alt2"
													disabled={ index === section.fields.length - 1 }
													label={ __( 'Move down', 'checkout-lift-for-woocommerce' ) }
													onClick={ () => move( section.key, index, index + 1 ) }
												/>
												<Button
													size="small"
													icon={ field.enabled ? 'visibility' : 'hidden' }
													label={
														field.enabled
															? __( 'Hide', 'checkout-lift-for-woocommerce' )
															: __( 'Show', 'checkout-lift-for-woocommerce' )
													}
													onClick={ () =>
														update( section.key, field.key, {
															enabled: ! field.enabled,
														} )
													}
												/>
											</span>
										</div>
									);
								} ) }
							</div>
						</div>
					) ) }
				</div>

				<div className="scfe-card scfe-panel">
					{ ! current ? (
						<div className="scfe-panel__empty">
							{ __( 'Choose a field to edit it.', 'checkout-lift-for-woocommerce' ) }
						</div>
					) : (
						<>
							<div className="scfe-card__head">
								<span className="scfe-card__title">
									{ current.field.label || current.field.key }
								</span>
								{ /* The section, not the key: the row already shows the
								     key, and which section a field sits in is the thing
								     that is otherwise easy to lose track of. */ }
								<span className="scfe-card__count">{ current.sectionLabel }</span>
							</div>

							<div className="scfe-panel__body">
								<TextControl
									__nextHasNoMarginBottom
									__next40pxDefaultSize
									label={ __( 'Label', 'checkout-lift-for-woocommerce' ) }
									value={ current.field.label }
									onChange={ ( label ) =>
										update( current.section, current.field.key, { label } )
									}
								/>

								<TextControl
									__nextHasNoMarginBottom
									__next40pxDefaultSize
									label={ __( 'Placeholder', 'checkout-lift-for-woocommerce' ) }
									value={ current.field.placeholder }
									onChange={ ( placeholder ) =>
										update( current.section, current.field.key, { placeholder } )
									}
								/>

								<div>
									<TextControl
										__nextHasNoMarginBottom
										__next40pxDefaultSize
										type="number"
										min="10"
										max="100"
										label={ __( 'Width (%)', 'checkout-lift-for-woocommerce' ) }
										value={ current.field.width }
										onChange={ ( width ) =>
											update( current.section, current.field.key, {
												width: parseInt( width, 10 ) || 100,
											} )
										}
									/>
									{ '' !== widthNote( current.field ) && (
										<div className="scfe-panel__note">
											{ widthNote( current.field ) }
										</div>
									) }
								</div>

								<CheckboxControl
									__nextHasNoMarginBottom
									label={ __( 'Required', 'checkout-lift-for-woocommerce' ) }
									checked={ current.field.required }
									onChange={ ( required ) =>
										update( current.section, current.field.key, { required } )
									}
								/>

								<CheckboxControl
									__nextHasNoMarginBottom
									label={ __(
										'Show on the checkout',
										'checkout-lift-for-woocommerce'
									) }
									checked={ current.field.enabled }
									onChange={ ( enabled ) =>
										update( current.section, current.field.key, { enabled } )
									}
								/>

								<Button
									variant="secondary"
									onClick={ () =>
										setEditing( {
											field: current.field,
											section: current.section,
										} )
									}
								>
									{ __(
										'Type, rules and conditions',
										'checkout-lift-for-woocommerce'
									) }
								</Button>

								{ current.field.custom && (
									<Button
										variant="link"
										isDestructive
										onClick={ () => remove( current.section, current.field ) }
									>
										{ __( 'Remove this field', 'checkout-lift-for-woocommerce' ) }
									</Button>
								) }
							</div>
						</>
					) }
				</div>
			</div>

			{ editing && (
				<FieldModal
					field={ editing.field }
					section={ editing.section }
					sections={ shownSections( data ) }
					allFields={ data.sections.flatMap( ( s ) =>
						s.fields.map( ( f ) => ( { value: f.key, label: `${ f.label || f.key }` } ) )
					) }
					paymentMethods={ data.payment_methods || [] }
					shippingMethods={ data.shipping_methods || [] }
					onSave={ applyField }
					onClose={ () => setEditing( null ) }
				/>
			) }

			<div className="scfe-savebar">
				<Button
					variant="secondary"
					onClick={ () => setEditing( { field: null, section: data.sections[ 0 ].key } ) }
				>
					{ __( 'Add field', 'checkout-lift-for-woocommerce' ) }
				</Button>
				<Button variant="primary" onClick={ save } isBusy={ saving } disabled={ saving }>
					{ __( 'Save fields', 'checkout-lift-for-woocommerce' ) }
				</Button>
				{ dirty ? (
					<span className="scfe-savebar__dirty">
						{ __( 'Unsaved changes', 'checkout-lift-for-woocommerce' ) }
					</span>
				) : (
					<span className="scfe-savebar__saved">
						{ __( 'All changes saved', 'checkout-lift-for-woocommerce' ) }
					</span>
				) }
			</div>
		</>
	);
}

/**
 * Sections the editor lists.
 *
 * With the shipping section removed from the checkout, its fields are hidden
 * here too, so the editor never shows fields the checkout does not. They are
 * still saved unchanged, so switching shipping back on restores them.
 *
 * @param {Object} data Loaded editor data.
 * @return {Array} Sections to list.
 */
export function shownSections( data ) {
	const sections = data.sections || [];

	return data.disable_shipping ? sections.filter( ( section ) => 'shipping' !== section.key ) : sections;
}

/**
 * Screen shell: the field list plus everything the classic editor owned.
 *
 * @return {JSX.Element} Screen.
 */
export default function FieldsEditor() {
	return (
		<div className="scfe-app">
			<h1>{ __( 'Checkout Fields', 'checkout-lift-for-woocommerce' ) }</h1>
			<p className="scfe-app__lede">
				{ __(
					'Decide what your checkout asks for, and when it asks for it.',
					'checkout-lift-for-woocommerce'
				) }
			</p>
			{ /* Above the tabs, because it applies to every one of them. */ }
			<CheckoutNotice />
			<TabPanel
				className="scfe-fields-tabs"
				tabs={ [
					{ name: 'fields', title: __( 'Fields', 'checkout-lift-for-woocommerce' ) },
					{ name: 'tools', title: __( 'Tools', 'checkout-lift-for-woocommerce' ) },
				] }
			>
				{ /* What used to be the Advanced tab is on the settings screen
				     now, where the rest of the plugin's settings are. */ }
				{ ( tab ) => ( 'tools' === tab.name ? <Tools /> : <FieldList /> ) }
			</TabPanel>
		</div>
	);
}
