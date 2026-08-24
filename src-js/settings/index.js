/**
 * The plugin's settings screen.
 *
 * @package SimpleCheckoutFieldsEditor
 */

import { __ } from '@wordpress/i18n';
import { TabPanel } from '@wordpress/components';

import Modules from './Modules';
import Recovery from '../dashboard/Settings';
import Checkout from '../fields/Advanced';

/**
 * Return the tabs this installation has something to show on.
 *
 * A tab for a part that is switched off would be a tab whose settings change
 * nothing, so the list follows what is actually running.
 *
 * @param {Object} enabled Which modules are on, keyed by name.
 * @return {Array} Tabs.
 */
export function tabsFor( enabled ) {
	const tabs = [ { name: 'general', title: __( 'General', 'checkout-lift-for-woocommerce' ) } ];

	if ( false !== enabled.recovery ) {
		tabs.push( {
			name: 'recovery',
			title: __( 'Checkout Recovery', 'checkout-lift-for-woocommerce' ),
		} );
	}

	tabs.push( { name: 'checkout', title: __( 'Checkout', 'checkout-lift-for-woocommerce' ) } );

	return tabs;
}

/**
 * The settings screen.
 *
 * @param {Object} props         Component props.
 * @param {Object} props.enabled Which modules are on.
 * @return {JSX.Element} Screen.
 */
export default function SettingsScreen( { enabled = {} } ) {
	return (
		<div className="scfe-app">
			<h1>{ __( 'Settings', 'checkout-lift-for-woocommerce' ) }</h1>
			<p className="scfe-app__lede">
				{ __(
					'Everything the plugin can do, and how each part behaves.',
					'checkout-lift-for-woocommerce'
				) }
			</p>

			<TabPanel className="scfe-settings-tabs" tabs={ tabsFor( enabled ) }>
				{ ( tab ) => {
					if ( 'recovery' === tab.name ) {
						return <Recovery />;
					}

					if ( 'checkout' === tab.name ) {
						return <Checkout />;
					}

					return <Modules />;
				} }
			</TabPanel>
		</div>
	);
}
