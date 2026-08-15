/**
 * Advanced checkout settings and tools.
 *
 * @package SimpleCheckoutFieldsEditor
 */

import { useEffect, useState } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import { __ } from '@wordpress/i18n';
import {
	Button,
	Card,
	CardBody,
	CardHeader,
	CheckboxControl,
	Notice,
	Spinner,
	TextControl,
} from '@wordpress/components';

/**
 * Multi-step, autocomplete, and the shipping toggle.
 *
 * @return {JSX.Element} Settings.
 */
export default function Advanced() {
	const [ values, setValues ] = useState( null );
	const [ saving, setSaving ] = useState( false );
	const [ notice, setNotice ] = useState( null );

	useEffect( () => {
		apiFetch( { path: '/scfe/v1/advanced' } )
			.then( setValues )
			.catch( ( e ) => setNotice( { status: 'error', text: e.message } ) );
	}, [] );

	if ( ! values ) {
		return <Spinner />;
	}

	const setStep = ( key ) => ( value ) =>
		setValues( { ...values, multistep: { ...values.multistep, [ key ]: value } } );
	const setAuto = ( key ) => ( value ) =>
		setValues( { ...values, autocomplete: { ...values.autocomplete, [ key ]: value } } );

	const save = () => {
		setSaving( true );
		setNotice( null );

		apiFetch( { path: '/scfe/v1/advanced', method: 'POST', data: values } )
			.then( ( saved ) => {
				setValues( saved );
				setNotice( {
					status: 'success',
					text: __( 'Settings saved.', 'checkout-lift-for-woocommerce' ),
				} );
			} )
			.catch( ( e ) => setNotice( { status: 'error', text: e.message } ) )
			.finally( () => setSaving( false ) );
	};

	const step = values.multistep || {};
	const auto = values.autocomplete || {};

	return (
		<>
			{ notice && (
				<Notice status={ notice.status } onRemove={ () => setNotice( null ) }>
					{ notice.text }
				</Notice>
			) }

			<Card style={ { marginTop: 16 } }>
				<CardHeader>
					<strong>{ __( 'Multi-step checkout', 'checkout-lift-for-woocommerce' ) }</strong>
				</CardHeader>
				<CardBody>
					<CheckboxControl
						__nextHasNoMarginBottom
						label={ __( 'Split the checkout into steps', 'checkout-lift-for-woocommerce' ) }
						checked={ !! step.enabled }
						onChange={ setStep( 'enabled' ) }
					/>

					{ step.enabled && (
						<div style={ { marginTop: 12 } }>
							{ [
								[ 'step1_title', __( 'Step 1 title', 'checkout-lift-for-woocommerce' ) ],
								[ 'step2_title', __( 'Step 2 title', 'checkout-lift-for-woocommerce' ) ],
								[ 'step3_title', __( 'Step 3 title', 'checkout-lift-for-woocommerce' ) ],
								[ 'next_label', __( 'Next button text', 'checkout-lift-for-woocommerce' ) ],
								[ 'prev_label', __( 'Back button text', 'checkout-lift-for-woocommerce' ) ],
							].map( ( [ key, label ] ) => (
								<TextControl
									key={ key }
									__nextHasNoMarginBottom
									__next40pxDefaultSize
									label={ label }
									value={ step[ key ] || '' }
									onChange={ setStep( key ) }
								/>
							) ) }
							<CheckboxControl
								__nextHasNoMarginBottom
								label={ __(
									'Check required fields before moving on',
									'checkout-lift-for-woocommerce'
								) }
								checked={ !! step.validate_steps }
								onChange={ setStep( 'validate_steps' ) }
							/>
						</div>
					) }
				</CardBody>
			</Card>

			<Card style={ { marginTop: 16 } }>
				<CardHeader>
					<strong>{ __( 'Address autocomplete', 'checkout-lift-for-woocommerce' ) }</strong>
				</CardHeader>
				<CardBody>
					<CheckboxControl
						__nextHasNoMarginBottom
						label={ __( 'Suggest addresses using Google Places', 'checkout-lift-for-woocommerce' ) }
						checked={ !! auto.enabled }
						onChange={ setAuto( 'enabled' ) }
					/>

					{ auto.enabled && (
						<div style={ { marginTop: 12 } }>
							<TextControl
								__nextHasNoMarginBottom
								__next40pxDefaultSize
								label={ __( 'Google API key', 'checkout-lift-for-woocommerce' ) }
								value={ auto.api_key || '' }
								onChange={ setAuto( 'api_key' ) }
							/>
							<TextControl
								__nextHasNoMarginBottom
								__next40pxDefaultSize
								label={ __( 'Limit to countries', 'checkout-lift-for-woocommerce' ) }
								help={ __(
									'Comma separated country codes, such as EG,SA. Leave empty for all.',
									'checkout-lift-for-woocommerce'
								) }
								value={ auto.countries || '' }
								onChange={ setAuto( 'countries' ) }
							/>
							<CheckboxControl
								__nextHasNoMarginBottom
								label={ __( 'On billing address', 'checkout-lift-for-woocommerce' ) }
								checked={ !! auto.billing_enabled }
								onChange={ setAuto( 'billing_enabled' ) }
							/>
							<CheckboxControl
								__nextHasNoMarginBottom
								label={ __( 'On shipping address', 'checkout-lift-for-woocommerce' ) }
								checked={ !! auto.shipping_enabled }
								onChange={ setAuto( 'shipping_enabled' ) }
							/>
							<CheckboxControl
								__nextHasNoMarginBottom
								label={ __( 'Offer a "use my location" button', 'checkout-lift-for-woocommerce' ) }
								checked={ !! auto.locate_me }
								onChange={ setAuto( 'locate_me' ) }
							/>
						</div>
					) }
				</CardBody>
			</Card>

			<Card style={ { marginTop: 16 } }>
				<CardHeader>
					<strong>{ __( 'Shipping', 'checkout-lift-for-woocommerce' ) }</strong>
				</CardHeader>
				<CardBody>
					<CheckboxControl
						__nextHasNoMarginBottom
						label={ __( 'Remove the shipping section entirely', 'checkout-lift-for-woocommerce' ) }
						help={ __(
							'For stores that never ship anything. Shipping fields disappear from the checkout.',
							'checkout-lift-for-woocommerce'
						) }
						checked={ !! values.disable_shipping }
						onChange={ ( disable_shipping ) => setValues( { ...values, disable_shipping } ) }
					/>
				</CardBody>
			</Card>

			<p style={ { marginTop: 16 } }>
				<Button variant="primary" onClick={ save } isBusy={ saving } disabled={ saving }>
					{ __( 'Save settings', 'checkout-lift-for-woocommerce' ) }
				</Button>
			</p>
		</>
	);
}
