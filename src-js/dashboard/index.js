/**
 * Checkout Recovery dashboard.
 *
 * @package SimpleCheckoutFieldsEditor
 */

import './style.scss';
import { createRoot, useEffect, useState } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import { __ } from '@wordpress/i18n';
import { Notice, Spinner } from '@wordpress/components';

import StatTile from './StatTile';
import RecentCarts from './RecentCarts';
import FieldsEditor from '../fields/FieldsEditor';
import SettingsScreen from '../settings';
import { formatMoney } from './format';
import RevenueDashboard from './RevenueDashboard';

/**
 * Root dashboard view.
 *
 * @return {JSX.Element} Dashboard.
 */
function Overview() {
	const [ data, setData ] = useState( null );
	const [ error, setError ] = useState( '' );

	useEffect( () => {
		apiFetch( { path: '/scfe/v1/recovery/stats' } )
			.then( setData )
			.catch( ( e ) =>
				setError(
					e.message ||
						__( 'The dashboard could not load its figures.', 'checkout-lift-for-woocommerce' )
				)
			);
	}, [] );

	if ( error ) {
		return (
			<Notice status="error" isDismissible={ false }>
				{ error }
			</Notice>
		);
	}

	if ( ! data ) {
		return <Spinner />;
	}

	const { summary, recent, tracking } = data;
	const { carts, value, recovery_rate: rate, messages, currency } = summary;

	return (
		<>
			{ ! tracking && (
				<Notice status="warning" isDismissible={ false }>
					{ __(
						'Recording checkout activity is switched off, so nothing new is being captured. Turn it on in the Settings tab.',
						'checkout-lift-for-woocommerce'
					) }
				</Notice>
			) }

			<div className="scfe-stats">
				<StatTile
					label={ __( 'Recovered', 'checkout-lift-for-woocommerce' ) }
					value={ carts.recovered }
					help={ formatMoney( value.recovered, currency ) }
					emphasis
				/>
				<StatTile
					label={ __( 'Recovery rate', 'checkout-lift-for-woocommerce' ) }
					value={ `${ rate }%` }
					help={ __( 'Of checkouts that were abandoned', 'checkout-lift-for-woocommerce' ) }
				/>
				<StatTile
					label={ __( 'Abandoned', 'checkout-lift-for-woocommerce' ) }
					value={ carts.abandoned }
					help={ formatMoney( value.abandoned, currency ) }
				/>
				<StatTile
					label={ __( 'In progress', 'checkout-lift-for-woocommerce' ) }
					value={ carts.open }
					help={ __( 'Still being filled in', 'checkout-lift-for-woocommerce' ) }
				/>
				<StatTile
					label={ __( 'Completed', 'checkout-lift-for-woocommerce' ) }
					value={ carts.completed }
					help={ __( 'Ordered without a reminder', 'checkout-lift-for-woocommerce' ) }
				/>
			</div>

			<div className="scfe-card">
				<div className="scfe-card__head">
					<span className="scfe-card__title">
						{ __( 'Reminders', 'checkout-lift-for-woocommerce' ) }
					</span>
				</div>
				<div className="scfe-counts">
					<span className="scfe-count">
						{ __( 'Sent', 'checkout-lift-for-woocommerce' ) } <b>{ messages.sent }</b>
					</span>
					<span className="scfe-count">
						{ __( 'Queued', 'checkout-lift-for-woocommerce' ) } <b>{ messages.pending }</b>
					</span>
					{ /* Failures carry the only colour here, because they are the
					     only one of the four that asks anything of the reader. */ }
					<span className={ messages.failed > 0 ? 'scfe-count is-bad' : 'scfe-count' }>
						{ __( 'Failed', 'checkout-lift-for-woocommerce' ) } <b>{ messages.failed }</b>
					</span>
					<span className="scfe-count">
						{ __( 'Cancelled', 'checkout-lift-for-woocommerce' ) } <b>{ messages.cancelled }</b>
					</span>
				</div>
				<p className="scfe-panel__note" style={ { padding: '0 18px 14px' } }>
					{ __(
						'Cancelled reminders are ones the customer made unnecessary by ordering first.',
						'checkout-lift-for-woocommerce'
					) }
				</p>
			</div>

			<RecentCarts carts={ recent } />

			<p className="scfe-panel__note">
				{ __(
					'Abandoned value is the captured cart total. Recovered value is the final product-line revenue attributable to restored quantities after discounts and tax; shipping and fees are excluded.',
					'checkout-lift-for-woocommerce'
				) }
			</p>
		</>
	);
}

/**
 * Screen shell: one heading, and a tab for each part of it.
 *
 * @return {JSX.Element} Screen.
 */
function Screen() {
	return (
		<div className="scfe-app">
			<h1>{ __( 'Checkout Recovery', 'checkout-lift-for-woocommerce' ) }</h1>
			<p className="scfe-app__lede">
				{ __(
					'The customers who reached your checkout and did not finish, and what became of them.',
					'checkout-lift-for-woocommerce'
				) }
			</p>

			{ /* One tab is not a tab. The settings that used to sit beside this
			     now live on the settings screen with everything else. */ }
			<Overview />
		</div>
	);
}

/**
 * One bundle serves every React screen, so it mounts whichever root the
 * current page provides and does nothing on the pages that have neither.
 */
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
