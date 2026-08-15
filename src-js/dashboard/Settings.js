/**
 * Recovery settings form.
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
	TextControl,
	TextareaControl,
} from '@wordpress/components';

const PATH = '/scfe/v1/recovery/settings';

/**
 * Settings tab.
 *
 * @return {JSX.Element} Settings form.
 */
export default function Settings() {
	const [ values, setValues ] = useState( null );
	const [ saving, setSaving ] = useState( false );
	const [ notice, setNotice ] = useState( null );

	useEffect( () => {
		apiFetch( { path: PATH } )
			.then( setValues )
			.catch( ( error ) => setNotice( { status: 'error', text: error.message } ) );
	}, [] );

	if ( ! values ) {
		return <Spinner />;
	}

	const set = ( key ) => ( value ) => setValues( { ...values, [ key ]: value } );
	const setNumber = ( key ) => ( value ) =>
		setValues( { ...values, [ key ]: parseInt( value, 10 ) || 1 } );
	const sequence = values.email_sequence ? window.scfe_admin_pro?.RecoverySequenceSettings : null;

	const save = () => {
		setSaving( true );
		setNotice( null );

		apiFetch( { path: PATH, method: 'POST', data: values } )
			.then( ( saved ) => {
				setValues( saved );
				setNotice( {
					status: 'success',
					text: __( 'Settings saved.', 'checkout-lift-for-woocommerce' ),
				} );
			} )
			.catch( ( error ) => setNotice( { status: 'error', text: error.message } ) )
			.finally( () => setSaving( false ) );
	};

	return (
		<div className="scfe-settings">
			{ notice && (
				<Notice status={ notice.status } onRemove={ () => setNotice( null ) }>
					{ notice.text }
				</Notice>
			) }

			<div className="scfe-card">
				<div className="scfe-card__head">
					<span className="scfe-card__title">{ __( 'Recording', 'checkout-lift-for-woocommerce' ) }</span>
				</div>
				<div className="scfe-fields">
					<CheckboxControl
						__nextHasNoMarginBottom
						label={ __( 'Record checkout activity', 'checkout-lift-for-woocommerce' ) }
						help={ __(
							'Stores what customers enter at checkout so unfinished orders can be recovered.',
							'checkout-lift-for-woocommerce'
						) }
						checked={ !! values.tracking_enabled }
						onChange={ set( 'tracking_enabled' ) }
					/>
					<CheckboxControl
						__nextHasNoMarginBottom
						label={ __( 'Ask the customer for permission first', 'checkout-lift-for-woocommerce' ) }
						help={ __(
							'Nothing is stored unless the customer agrees. Required in the EU and UK. Only turn this off if you are certain your local law allows it.',
							'checkout-lift-for-woocommerce'
						) }
						checked={ !! values.require_consent }
						onChange={ set( 'require_consent' ) }
					/>
					<TextControl
						__nextHasNoMarginBottom
						__next40pxDefaultSize
						label={ __( 'Permission wording shown at checkout', 'checkout-lift-for-woocommerce' ) }
						value={ values.consent_text || '' }
						onChange={ set( 'consent_text' ) }
					/>
				</div>
			</div>

			<div className="scfe-card">
				<div className="scfe-card__head">
					<span className="scfe-card__title">{ __( 'Timing', 'checkout-lift-for-woocommerce' ) }</span>
				</div>
				<div className="scfe-fields">
					<TextControl
						__nextHasNoMarginBottom
						__next40pxDefaultSize
						type="number"
						min={ 1 }
						label={ __( 'Treat a checkout as abandoned after', 'checkout-lift-for-woocommerce' ) }
						help={ __( 'Minutes of no activity.', 'checkout-lift-for-woocommerce' ) }
						value={ values.idle_minutes }
						onChange={ setNumber( 'idle_minutes' ) }
					/>
					{ sequence && (
						<sequence.Timing values={ values } set={ set } setNumber={ setNumber } />
					) }
				</div>
			</div>

			{ sequence ? <div className="scfe-card">
				<div className="scfe-card__head">
					<span className="scfe-card__title">{ __( 'Recovery email', 'checkout-lift-for-woocommerce' ) }</span>
				</div>
				<div className="scfe-fields">
					{ sequence && <sequence.Design values={ values } set={ set } /> }
					<TextControl
						__nextHasNoMarginBottom
						__next40pxDefaultSize
						label={ sequence
							? __( 'Optional subject override for email 1', 'checkout-lift-for-woocommerce' )
							: __( 'Optional email subject', 'checkout-lift-for-woocommerce' ) }
						value={ values.email_subject || '' }
						onChange={ set( 'email_subject' ) }
					/>
					<TextareaControl
						__nextHasNoMarginBottom
						rows={ 8 }
						label={ sequence
							? __( 'Optional message added to email 1', 'checkout-lift-for-woocommerce' )
							: __( 'Email message', 'checkout-lift-for-woocommerce' ) }
						help={ __(
							'Use {name} for the customer, {link} for the button back to their checkout, and {site} for your store name.',
							'checkout-lift-for-woocommerce'
						) }
						value={ values.email_body || '' }
						onChange={ set( 'email_body' ) }
					/>
				</div>
			</div> : <div className="scfe-card scfe-upgrade-card">
				<div className="scfe-card__head"><span className="scfe-card__title">{ __( 'Recover abandoned checkout value automatically', 'checkout-lift-for-woocommerce' ) }</span></div>
				<div className="scfe-fields">
					<p>{ __( 'Lite records abandoned checkouts and their potential value. Pro sends a four-email sequence and restores the saved cart through a secure link.', 'checkout-lift-for-woocommerce' ) }</p>
					<Button variant="primary" href="https://designtich.com/checkout-lift-for-woocommerce/" target="_blank">{ __( 'Explore Pro recovery', 'checkout-lift-for-woocommerce' ) }</Button>
				</div>
			</div> }

			<div className="scfe-card">
				<div className="scfe-card__head">
					<span className="scfe-card__title">{ __( 'Data', 'checkout-lift-for-woocommerce' ) }</span>
				</div>
				<div className="scfe-fields">
					<TextControl
						__nextHasNoMarginBottom
						__next40pxDefaultSize
						type="number"
						min={ 1 }
						max={ 730 }
						label={ __( 'Keep checkout activity for', 'checkout-lift-for-woocommerce' ) }
						help={ __( 'Days, then delete it automatically.', 'checkout-lift-for-woocommerce' ) }
						value={ values.retention_days }
						onChange={ setNumber( 'retention_days' ) }
					/>
					<CheckboxControl
						__nextHasNoMarginBottom
						label={ __( 'Delete all plugin data when the plugin is deleted', 'checkout-lift-for-woocommerce' ) }
						help={ __(
							'Turn this off to keep your settings and stored activity if you remove the plugin.',
							'checkout-lift-for-woocommerce'
						) }
						checked={ !! values.delete_on_uninstall }
						onChange={ set( 'delete_on_uninstall' ) }
					/>
				</div>
			</div>

			<div className="scfe-savebar">
				<Button variant="primary" onClick={ save } isBusy={ saving } disabled={ saving }>
					{ __( 'Save settings', 'checkout-lift-for-woocommerce' ) }
				</Button>
			</div>
		</div>
	);
}
