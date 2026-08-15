/**
 * Which tabs the settings screen offers.
 *
 * @package SimpleCheckoutFieldsEditor
 */

import { tabsFor } from '../index';

const names = ( enabled ) => tabsFor( enabled ).map( ( t ) => t.name );

describe( 'tabsFor', () => {
	it( 'always offers General, which is where the switches are', () => {
		// Without it a merchant who switched everything off would have no way
		// back to switching anything on.
		expect( names( { recovery: false, multistep: false, 'address-autocomplete': false } ) ).toEqual(
			[ 'general' ]
		);
	} );

	it( 'offers every tab on a shop that has changed nothing', () => {
		expect( names( {} ) ).toEqual( [ 'general', 'recovery', 'checkout' ] );
	} );

	it( 'drops a tab whose settings would change nothing', () => {
		expect( names( { recovery: false } ) ).toEqual( [ 'general', 'checkout' ] );
	} );

	it( 'keeps the Checkout tab while either of its two parts is on', () => {
		// The two share a tab because they are one question to a merchant, so
		// one of them is enough to justify it.
		expect( names( { multistep: false } ) ).toContain( 'checkout' );
		expect( names( { 'address-autocomplete': false } ) ).toContain( 'checkout' );
		expect(
			names( { multistep: false, 'address-autocomplete': false } )
		).not.toContain( 'checkout' );
	} );
} );
