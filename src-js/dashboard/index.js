/**
 * Checkout activity dashboard.
 *
 * @package SimpleCheckoutFieldsEditor
 */

import './style.scss';
import { createRoot, useEffect, useState } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import { __ } from '@wordpress/i18n';
import { Button, Notice, Spinner } from '@wordpress/components';

import StatTile from './StatTile';
import RecentCarts from './RecentCarts';
import FieldsEditor from '../fields/FieldsEditor';
import SettingsScreen from '../settings';
import { formatMoney } from './format';
import RevenueDashboard from './RevenueDashboard';

function Overview() {
	const [ data, setData ] = useState( null );
	const [ error, setError ] = useState( '' );

	useEffect( () => {
		apiFetch( { path: '/scfe/v1/recovery/stats' } )
			.then( setData )
			.catch( ( requestError ) => setError( requestError.message || __( 'The dashboard could not load its figures.', 'checkout-lift-for-woocommerce' ) ) );
	}, [] );

	if ( error ) {
		return <Notice status="error" isDismissible={ false }>{ error }</Notice>;
	}

	if ( ! data ) {
		return <Spinner />;
	}

	const { summary, recent, tracking } = data;
	const { carts, value, currency } = summary;

	return (
		<>
			{ ! tracking && <Notice status="warning" isDismissible={ false }>{ __( 'Recording checkout activity is switched off, so nothing new is being captured. Turn it on in Settings.', 'checkout-lift-for-woocommerce' ) }</Notice> }
			<div className="scfe-stats">
				<StatTile label={ __( 'Abandoned checkouts', 'checkout-lift-for-woocommerce' ) } value={ carts.abandoned } help={ formatMoney( value.abandoned, currency ) } emphasis />
				<StatTile label={ __( 'In progress', 'checkout-lift-for-woocommerce' ) } value={ carts.open } help={ __( 'Still being filled in', 'checkout-lift-for-woocommerce' ) } />
				<StatTile label={ __( 'Orders placed', 'checkout-lift-for-woocommerce' ) } value={ carts.completed } help={ __( 'Tracked checkouts that became orders', 'checkout-lift-for-woocommerce' ) } />
			</div>
			<div className="scfe-card scfe-upgrade-card">
				<div className="scfe-card__head"><span className="scfe-card__title">{ __( 'You can see the lost value. The add-on helps win it back.', 'checkout-lift-for-woocommerce' ) }</span></div>
				<div className="scfe-fields">
					<p>{ __( 'Automated recovery emails, secure cart-resume links, and recovered-revenue reporting are provided by a separate add-on.', 'checkout-lift-for-woocommerce' ) }</p>
					<Button variant="primary" href="https://designtich.com/checkout-lift-for-woocommerce/" target="_blank" rel="noreferrer">{ __( 'Explore recovery add-ons', 'checkout-lift-for-woocommerce' ) }</Button>
				</div>
			</div>
			<RecentCarts carts={ recent } />
		</>
	);
}

function Screen() {
	return (
		<div className="scfe-app">
			<h1>{ __( 'Checkout Activity', 'checkout-lift-for-woocommerce' ) }</h1>
			<p className="scfe-app__lede">{ __( 'See unfinished checkouts and the value currently being left behind.', 'checkout-lift-for-woocommerce' ) }</p>
			<Overview />
		</div>
	);
}

const roots = [
	[ 'scfe-checkout-recovery', <Screen key="recovery" /> ],
	[ 'scfe-checkout-fields', <FieldsEditor key="fields" /> ],
	[ 'scfe-settings', <SettingsScreen key="settings" enabled={ window.scfe_admin?.modules || {} } /> ],
	[ 'scfe-revenue-analytics', <RevenueDashboard key="revenue" /> ],
];

roots.forEach( ( [ id, element ] ) => {
	const container = document.getElementById( id );

	if ( container ) {
		container.textContent = '';
		createRoot( container ).render( element );
	}
} );
