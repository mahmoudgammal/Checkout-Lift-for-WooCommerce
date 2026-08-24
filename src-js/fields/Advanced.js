/**
 * Free checkout settings with an add-on extension slot.
 *
 * @package SimpleCheckoutFieldsEditor
 */

import { useEffect, useState } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import { __ } from '@wordpress/i18n';
import { Button, Card, CardBody, CardHeader, CheckboxControl, Notice, Spinner } from '@wordpress/components';

export default function Advanced() {
	const [ values, setValues ] = useState( null );
	const [ saving, setSaving ] = useState( false );
	const [ notice, setNotice ] = useState( null );
	const AddonSettings = window.scfe_admin_extensions?.CheckoutSettings;

	useEffect( () => {
		apiFetch( { path: '/scfe/v1/advanced' } )
			.then( setValues )
			.catch( ( error ) => setNotice( { status: 'error', text: error.message } ) );
	}, [] );

	if ( ! values ) {
		return <Spinner />;
	}

	const save = () => {
		setSaving( true );
		setNotice( null );
		apiFetch( { path: '/scfe/v1/advanced', method: 'POST', data: values } )
			.then( ( saved ) => {
				setValues( saved );
				setNotice( { status: 'success', text: __( 'Settings saved.', 'checkout-lift-for-woocommerce' ) } );
			} )
			.catch( ( error ) => setNotice( { status: 'error', text: error.message } ) )
			.finally( () => setSaving( false ) );
	};

	return <>
		{ notice && <Notice status={ notice.status } onRemove={ () => setNotice( null ) }>{ notice.text }</Notice> }
		<Card style={ { marginTop: 16 } }>
			<CardHeader><strong>{ __( 'Shipping', 'checkout-lift-for-woocommerce' ) }</strong></CardHeader>
			<CardBody>
				<CheckboxControl
					__nextHasNoMarginBottom
					label={ __( 'Remove the shipping section entirely', 'checkout-lift-for-woocommerce' ) }
					help={ __( 'For stores that never ship anything. Shipping fields disappear from the checkout.', 'checkout-lift-for-woocommerce' ) }
					checked={ !! values.disable_shipping }
					onChange={ ( disable_shipping ) => setValues( { ...values, disable_shipping } ) }
				/>
			</CardBody>
		</Card>
		{ AddonSettings && <AddonSettings values={ values } setValues={ setValues } /> }
		<p style={ { marginTop: 16 } }>
			<Button variant="primary" onClick={ save } isBusy={ saving } disabled={ saving }>
				{ __( 'Save settings', 'checkout-lift-for-woocommerce' ) }
			</Button>
		</p>
	</>;
}
