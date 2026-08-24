/**
 * Field types follow the modules that are actually running.
 *
 * @package SimpleCheckoutFieldsEditor
 */

import { availableTypes } from '../fieldTypes';
import proFieldTypes from '../../../checkout-lift-for-woocommerce-pro/src-js/admin/fieldTypes';

describe( 'availableTypes', () => {
	afterEach( () => {
		delete window.scfe_admin;
		delete window.scfe_admin_extensions;
	} );

	it( 'the Lite entry offers only base field types', () => {
		expect( availableTypes().map( ( type ) => type.value ) ).toEqual( [
			'text',
			'number',
			'email',
			'url',
			'select',
			'textarea',
		] );
	} );

	it( 'the Pro entry adds only types whose runtime module is enabled', () => {
		window.scfe_admin_extensions = { FieldTypes: proFieldTypes };
		window.scfe_admin = {
			modules: {
				datetime: false,
				'file-upload': false,
				fee: true,
				repeater: true,
			},
		};

		const values = availableTypes().map( ( type ) => type.value );

		expect( values ).not.toEqual( expect.arrayContaining( [ 'date', 'time', 'datetime', 'file' ] ) );
		expect( values ).toEqual( expect.arrayContaining( [ 'text', 'fee', 'repeater' ] ) );
	} );

	it( 'keeps an existing disabled Pro type editable', () => {
		window.scfe_admin_extensions = { FieldTypes: proFieldTypes };
		window.scfe_admin = { modules: { fee: false } };

		expect( availableTypes( 'fee' ).map( ( type ) => type.value ) ).toContain( 'fee' );
	} );

	it( 'keeps an existing Pro value round-trip safe when Pro is absent', () => {
		expect( availableTypes( 'fee' ) ).toContainEqual( { value: 'fee', label: 'fee' } );
	} );
} );
