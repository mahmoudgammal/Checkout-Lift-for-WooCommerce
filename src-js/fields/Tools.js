/**
 * Export, import, and reset.
 *
 * @package SimpleCheckoutFieldsEditor
 */

import { useState } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import { __ } from '@wordpress/i18n';
import { Button, Card, CardBody, CardHeader, Notice } from '@wordpress/components';

/**
 * Backup and restore tools.
 *
 * @return {JSX.Element} Tools.
 */
export default function Tools() {
	const [ busy, setBusy ] = useState( '' );
	const [ notice, setNotice ] = useState( null );

	const fail = ( e ) => setNotice( { status: 'error', text: e.message } );

	const exportSettings = () => {
		setBusy( 'export' );
		setNotice( null );

		apiFetch( { path: '/scfe/v1/export' } )
			.then( ( data ) => {
				const blob = new Blob( [ JSON.stringify( data, null, 2 ) ], {
					type: 'application/json',
				} );
				const url = URL.createObjectURL( blob );
				const link = document.createElement( 'a' );

				link.href = url;
				link.download = `scfe-settings-${ new Date().toISOString().slice( 0, 10 ) }.json`;
				document.body.appendChild( link );
				link.click();
				document.body.removeChild( link );
				URL.revokeObjectURL( url );

				setNotice( {
					status: 'success',
					text: __( 'Settings downloaded.', 'checkout-lift-for-woocommerce' ),
				} );
			} )
			.catch( fail )
			.finally( () => setBusy( '' ) );
	};

	const importSettings = ( event ) => {
		const file = event.target.files?.[ 0 ];

		if ( ! file ) {
			return;
		}

		setBusy( 'import' );
		setNotice( null );

		file.text()
			.then( ( text ) => JSON.parse( text ) )
			.then( ( payload ) =>
				apiFetch( { path: '/scfe/v1/import', method: 'POST', data: { payload } } )
			)
			.then( () => {
				setNotice( {
					status: 'success',
					text: __( 'Settings imported. Reloading…', 'checkout-lift-for-woocommerce' ),
				} );
				window.setTimeout( () => window.location.reload(), 800 );
			} )
			.catch( ( e ) =>
				fail(
					e instanceof SyntaxError
						? new Error( __( 'That file is not valid JSON.', 'checkout-lift-for-woocommerce' ) )
						: e
				)
			)
			.finally( () => {
				setBusy( '' );
				// Clearing lets the same file be chosen again after a failure.
				event.target.value = '';
			} );
	};

	const reset = () => {
		// eslint-disable-next-line no-alert
		if (
			! window.confirm(
				__(
					'This clears every checkout field customisation and returns to the WooCommerce defaults. Continue?',
					'checkout-lift-for-woocommerce'
				)
			)
		) {
			return;
		}

		setBusy( 'reset' );
		setNotice( null );

		apiFetch( { path: '/scfe/v1/reset', method: 'POST' } )
			.then( () => {
				setNotice( {
					status: 'success',
					text: __( 'Reset. Reloading…', 'checkout-lift-for-woocommerce' ),
				} );
				window.setTimeout( () => window.location.reload(), 800 );
			} )
			.catch( fail )
			.finally( () => setBusy( '' ) );
	};

	return (
		<>
			{ notice && (
				<Notice status={ notice.status } onRemove={ () => setNotice( null ) }>
					{ notice.text }
				</Notice>
			) }

			<Card style={ { marginTop: 16 } }>
				<CardHeader>
					<strong>{ __( 'Backup', 'checkout-lift-for-woocommerce' ) }</strong>
				</CardHeader>
				<CardBody>
					<p>
						{ __(
							'Download every checkout field, along with the multi-step and autocomplete settings, as one file. Keep it before making large changes, or use it to copy this setup to another store.',
							'checkout-lift-for-woocommerce'
						) }
					</p>
					<Button variant="secondary" onClick={ exportSettings } isBusy={ 'export' === busy }>
						{ __( 'Export settings', 'checkout-lift-for-woocommerce' ) }
					</Button>
				</CardBody>
			</Card>

			<Card style={ { marginTop: 16 } }>
				<CardHeader>
					<strong>{ __( 'Restore', 'checkout-lift-for-woocommerce' ) }</strong>
				</CardHeader>
				<CardBody>
					<p>
						{ __(
							'Importing replaces your current checkout fields with the ones in the file.',
							'checkout-lift-for-woocommerce'
						) }
					</p>
					<input
						type="file"
						accept=".json,application/json"
						onChange={ importSettings }
						disabled={ 'import' === busy }
					/>
				</CardBody>
			</Card>

			<Card style={ { marginTop: 16 } }>
				<CardHeader>
					<strong>{ __( 'Reset', 'checkout-lift-for-woocommerce' ) }</strong>
				</CardHeader>
				<CardBody>
					<p>
						{ __(
							'Clears every field customisation and returns the checkout to the WooCommerce defaults. Multi-step, autocomplete, and recovery settings are left alone.',
							'checkout-lift-for-woocommerce'
						) }
					</p>
					<Button variant="secondary" isDestructive onClick={ reset } isBusy={ 'reset' === busy }>
						{ __( 'Reset checkout fields', 'checkout-lift-for-woocommerce' ) }
					</Button>
				</CardBody>
			</Card>
		</>
	);
}
