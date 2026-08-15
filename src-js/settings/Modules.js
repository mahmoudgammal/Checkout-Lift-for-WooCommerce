/**
 * The General tab: what the plugin is allowed to do.
 *
 * @package SimpleCheckoutFieldsEditor
 */

import { useEffect, useState } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import { __ } from '@wordpress/i18n';
import { Button, CheckboxControl, Notice, Spinner } from '@wordpress/components';

const PATH = '/scfe/v1/modules';

/**
 * Switch the plugin's optional parts on and off.
 *
 * A shop that does not take file uploads should not pay for the code that
 * handles them, and a merchant looking for a setting should not have to wonder
 * whether the feature exists at all.
 *
 * @return {JSX.Element} Tab.
 */
export default function Modules() {
	const [ modules, setModules ] = useState( null );
	const [ saving, setSaving ] = useState( false );
	const [ notice, setNotice ] = useState( null );
	const [ dirty, setDirty ] = useState( false );

	useEffect( () => {
		apiFetch( { path: PATH } )
			.then( ( data ) => setModules( data.modules ) )
			.catch( ( e ) => setNotice( { status: 'error', text: e.message } ) );
	}, [] );

	if ( ! modules ) {
		return notice ? (
			<Notice status="error" isDismissible={ false }>
				{ notice.text }
			</Notice>
		) : (
			<Spinner />
		);
	}

	const toggle = ( name, enabled ) => {
		setDirty( true );
		setModules( modules.map( ( m ) => ( m.name === name ? { ...m, enabled } : m ) ) );
	};

	const save = () => {
		setSaving( true );
		setNotice( null );

		const chosen = {};
		modules.forEach( ( m ) => {
			chosen[ m.name ] = m.enabled;
		} );

		apiFetch( { path: PATH, method: 'POST', data: { modules: chosen } } )
			.then( ( data ) => {
				setModules( data.modules );
				setDirty( false );
				setNotice( {
					status: 'success',
					// Switching a module off unregisters it, which only happens
					// on the next request. Saying so avoids the merchant
					// thinking the switch did not take.
					text: __(
						'Saved. Reload any open screen to see the change.',
						'checkout-lift-for-woocommerce'
					),
				} );
			} )
			.catch( ( e ) => setNotice( { status: 'error', text: e.message } ) )
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
					<span className="scfe-card__title">
						{ __( 'What this plugin does', 'checkout-lift-for-woocommerce' ) }
					</span>
					<span className="scfe-card__count">
						{ modules.filter( ( m ) => m.enabled ).length } / { modules.length }
					</span>
				</div>

				<div className="scfe-modules">
					{ modules.map( ( module ) => (
						<div
							key={ module.name }
							className={ module.enabled ? 'scfe-module is-on' : 'scfe-module' }
						>
							<CheckboxControl
								__nextHasNoMarginBottom
								label={ module.label }
								checked={ module.enabled }
							onChange={ ( on ) => toggle( module.name, on ) }
						/>
						</div>
					) ) }
				</div>

				<p className="scfe-panel__note" style={ { padding: '0 18px 14px' } }>
					{ __(
						'Switching a part off stops it loading at all, on the checkout and in the admin. Nothing you have configured is deleted, so switching it back on restores it.',
						'checkout-lift-for-woocommerce'
					) }
				</p>
			</div>

			<div className="scfe-savebar">
				<Button variant="primary" onClick={ save } isBusy={ saving } disabled={ saving }>
					{ __( 'Save', 'checkout-lift-for-woocommerce' ) }
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
		</div>
	);
}
