/**
 * Lite checkout funnel and abandoned-value overview.
 *
 * @package SimpleCheckoutFieldsEditor
 */

import { useEffect, useState } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import { __ } from '@wordpress/i18n';
import { Notice, Spinner } from '@wordpress/components';

import { formatMoney } from './format';

const PATH = '/scfe/v1/revenue/overview';

function Metric( { label, value, help, lead = false } ) {
	return <div className={ lead ? 'scfe-stat is-lead' : 'scfe-stat' }><div className="scfe-stat__label">{ label }</div><div className="scfe-stat__value">{ value }</div><div className="scfe-stat__help">{ help }</div></div>;
}

function Funnel( { totals } ) {
	const rows = [
		[ __( 'Checkout attempts', 'checkout-lift-for-woocommerce' ), totals.attempts, 100 ],
		[ __( 'Orders placed', 'checkout-lift-for-woocommerce' ), totals.placed, totals.attempts ? ( totals.placed / totals.attempts ) * 100 : 0 ],
		[ __( 'Abandoned attempts', 'checkout-lift-for-woocommerce' ), totals.abandoned, totals.attempts ? ( totals.abandoned / totals.attempts ) * 100 : 0 ],
	];

	return <div className="scfe-card scfe-funnel"><div className="scfe-card__head"><span className="scfe-card__title">{ __( 'Checkout funnel', 'checkout-lift-for-woocommerce' ) }</span></div><div className="scfe-chart-list">{ rows.map( ( [ label, count, width ] ) => <div className="scfe-chart-row" key={ label }><div className="scfe-chart-row__label"><span>{ label }</span><b>{ count }</b></div><div className="scfe-bar"><span style={ { width: `${ Math.max( 2, Math.min( 100, width ) ) }%` } } /></div></div> ) }</div></div>;
}

export default function RevenueDashboard() {
	const AddonAnalytics = window.scfe_admin_extensions?.RevenueAnalytics;
	const [ filters, setFilters ] = useState( { period: '30', currency: '' } );
	const [ data, setData ] = useState( null );
	const [ error, setError ] = useState( '' );
	const [ loading, setLoading ] = useState( true );

	useEffect( () => {
		let active = true;
		setLoading( true );
		const query = new URLSearchParams( filters );
		apiFetch( { path: `${ PATH }?${ query.toString() }` } )
			.then( ( response ) => {
				if ( active ) {
					setData( response );
					setFilters( ( current ) => current.currency || ! response.filters.currency ? current : { ...current, currency: response.filters.currency } );
				}
			} )
			.catch( ( requestError ) => active && setError( requestError.message ) )
			.finally( () => active && setLoading( false ) );
		return () => { active = false; };
	}, [ filters.period, filters.currency ] );

	const set = ( key ) => ( event ) => setFilters( { ...filters, [ key ]: event.target.value } );
	const totals = data?.totals;
	const currencies = data?.filters?.currencies?.length ? data.filters.currencies : [ data?.filters?.currency || 'USD' ];

	return <div className="scfe-app scfe-revenue">
		<div className="scfe-revenue__heading"><div><h1>{ __( 'Revenue Analytics', 'checkout-lift-for-woocommerce' ) }</h1><p className="scfe-app__lede">{ __( 'Measure checkout outcomes and the potential value sitting in abandoned checkouts.', 'checkout-lift-for-woocommerce' ) }</p></div>{ loading && <Spinner /> }</div>
		<div className="scfe-revenue-filters">
			<label><span>{ __( 'Period', 'checkout-lift-for-woocommerce' ) }</span><select value={ filters.period } onChange={ set( 'period' ) }><option value="7">{ __( 'Last 7 days', 'checkout-lift-for-woocommerce' ) }</option><option value="30">{ __( 'Last 30 days', 'checkout-lift-for-woocommerce' ) }</option></select></label>
			<label><span>{ __( 'Currency', 'checkout-lift-for-woocommerce' ) }</span><select value={ filters.currency } onChange={ set( 'currency' ) }>{ currencies.map( ( currency ) => <option value={ currency } key={ currency }>{ currency }</option> ) }</select></label>
		</div>
		{ error && <Notice status="error" isDismissible={ false }>{ error }</Notice> }
		{ data && ! data.tracking && <Notice status="warning" isDismissible={ false }>{ __( 'Checkout measurement is off. Enable recording in Recovery settings to collect new activity.', 'checkout-lift-for-woocommerce' ) }</Notice> }
		{ totals && <>
			<div className="scfe-stats scfe-revenue-stats">
				<Metric label={ __( 'Checkout attempts', 'checkout-lift-for-woocommerce' ) } value={ totals.attempts } help={ __( 'Distinct measured sessions', 'checkout-lift-for-woocommerce' ) } />
				<Metric label={ __( 'Orders placed', 'checkout-lift-for-woocommerce' ) } value={ totals.placed } help={ __( 'Orders created', 'checkout-lift-for-woocommerce' ) } />
				<Metric label={ __( 'Placement rate', 'checkout-lift-for-woocommerce' ) } value={ null === totals.placement_rate ? '—' : `${ totals.placement_rate }%` } help={ __( 'Placed orders divided by closed attempts', 'checkout-lift-for-woocommerce' ) } />
				<Metric label={ __( 'Abandoned', 'checkout-lift-for-woocommerce' ) } value={ totals.abandoned } help={ __( 'Inactive beyond your threshold', 'checkout-lift-for-woocommerce' ) } />
				<Metric lead label={ __( 'Potential abandoned checkout value', 'checkout-lift-for-woocommerce' ) } value={ formatMoney( totals.abandoned_value, data.filters.currency ) } help={ __( 'Captured cart value; not guaranteed lost or recoverable revenue', 'checkout-lift-for-woocommerce' ) } />
			</div>
			<Funnel totals={ totals } />
			{ AddonAnalytics ? <AddonAnalytics currency={ data.filters.currency } /> : <div className="scfe-card scfe-revenue-upgrade"><div><strong>{ __( 'Turn abandoned checkout value into recovered orders', 'checkout-lift-for-woocommerce' ) }</strong><p>{ __( 'A separate add-on can automate recovery and add deeper reporting.', 'checkout-lift-for-woocommerce' ) }</p></div><a className="button button-primary" href="https://designtich.com/checkout-lift-for-woocommerce/" target="_blank" rel="noreferrer">{ __( 'Explore recovery add-ons', 'checkout-lift-for-woocommerce' ) }</a></div> }
		</> }
	</div>;
}
