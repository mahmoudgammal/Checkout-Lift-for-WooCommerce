/**
 * Build configuration for React admin and Checkout Blocks bundles.
 *
 * Classic checkout runtime scripts under `assets/js` are hand-written and are
 * deliberately not built: they are small, ship as-is, and adding a bundler to
 * the checkout hot path would cost more than it saves.
 *
 * @package CheckoutLift
 */

const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );
const path = require( 'path' );

module.exports = {
	...defaultConfig,
	entry: {
		dashboard: path.resolve( __dirname, 'src-js/dashboard/index.js' ),
	},
	output: {
		...defaultConfig.output,
		path: path.resolve( __dirname, 'build' ),
	},
};
