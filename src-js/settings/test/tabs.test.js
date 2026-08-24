/**
 * Settings tabs exposed by Lite.
 */

import { tabsFor } from '../index';

const names = ( enabled ) => tabsFor( enabled ).map( ( tab ) => tab.name );

describe( 'tabsFor', () => {
	it( 'always offers General and Checkout because both contain Lite settings', () => {
		expect( names( { recovery: false } ) ).toEqual( [ 'general', 'checkout' ] );
	} );

	it( 'offers recovery only while its Lite tracking module is enabled', () => {
		expect( names( {} ) ).toEqual( [ 'general', 'recovery', 'checkout' ] );
		expect( names( { recovery: false } ) ).not.toContain( 'recovery' );
	} );
} );
