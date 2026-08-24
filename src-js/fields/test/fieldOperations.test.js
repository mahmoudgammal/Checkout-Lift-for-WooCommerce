/**
 * Operations on the editor's field list.
 *
 * @package SimpleCheckoutFieldsEditor
 */

import {
	moveField,
	upsertField,
	removeField,
	updateField,
} from '../fieldOperations';

const sections = () => [
	{
		key: 'billing',
		fields: [ { key: 'a' }, { key: 'b' }, { key: 'c' } ],
	},
	{
		key: 'order',
		fields: [ { key: 'notes' } ],
	},
];

describe( 'moveField', () => {
	it( 'reorders within a section', () => {
		const result = moveField( sections(), 'billing', 2, 0 );

		expect( result[ 0 ].fields.map( ( f ) => f.key ) ).toEqual( [ 'c', 'a', 'b' ] );
	} );

	it( 'leaves other sections untouched', () => {
		const before = sections();
		const result = moveField( before, 'billing', 0, 1 );

		// Identity, not just equality: an untouched section must not re-render.
		expect( result[ 1 ] ).toBe( before[ 1 ] );
	} );

	it( 'ignores a move that goes nowhere', () => {
		const before = sections();

		expect( moveField( before, 'billing', 1, 1 ) ).toBe( before );
	} );

	it( 'refuses to move outside the list rather than dropping the field', () => {
		expect( moveField( sections(), 'billing', 0, -1 )[ 0 ].fields ).toHaveLength( 3 );
		expect( moveField( sections(), 'billing', 0, 99 )[ 0 ].fields ).toHaveLength( 3 );
	} );
} );

describe( 'upsertField', () => {
	it( 'appends a new field', () => {
		const result = upsertField( sections(), { key: 'new' }, 'billing' );

		expect( result[ 0 ].fields.map( ( f ) => f.key ) ).toEqual( [ 'a', 'b', 'c', 'new' ] );
	} );

	it( 'replaces an existing field in place rather than moving it to the end', () => {
		const result = upsertField( sections(), { key: 'b', label: 'edited' }, 'billing', 'billing' );

		expect( result[ 0 ].fields.map( ( f ) => f.key ) ).toEqual( [ 'a', 'b', 'c' ] );
		expect( result[ 0 ].fields[ 1 ].label ).toBe( 'edited' );
	} );

	it( 'moves a field between sections without leaving a copy behind', () => {
		const result = upsertField( sections(), { key: 'b' }, 'order', 'billing' );

		expect( result[ 0 ].fields.map( ( f ) => f.key ) ).toEqual( [ 'a', 'c' ] );
		expect( result[ 1 ].fields.map( ( f ) => f.key ) ).toEqual( [ 'notes', 'b' ] );
	} );
} );

describe( 'removeField', () => {
	it( 'removes only the named field', () => {
		const result = removeField( sections(), 'billing', 'b' );

		expect( result[ 0 ].fields.map( ( f ) => f.key ) ).toEqual( [ 'a', 'c' ] );
		expect( result[ 1 ].fields ).toHaveLength( 1 );
	} );

	it( 'does nothing when the field is not there', () => {
		const result = removeField( sections(), 'billing', 'missing' );

		expect( result[ 0 ].fields ).toHaveLength( 3 );
	} );
} );

describe( 'updateField', () => {
	it( 'merges changes into one field', () => {
		const result = updateField( sections(), 'billing', 'b', { label: 'Two', width: 50 } );

		expect( result[ 0 ].fields[ 1 ] ).toEqual( { key: 'b', label: 'Two', width: 50 } );
		expect( result[ 0 ].fields[ 0 ] ).toEqual( { key: 'a' } );
	} );

	it( 'does not touch a field of the same key in another section', () => {
		const withDuplicate = [
			{ key: 'billing', fields: [ { key: 'shared' } ] },
			{ key: 'order', fields: [ { key: 'shared' } ] },
		];

		const result = updateField( withDuplicate, 'billing', 'shared', { label: 'only here' } );

		expect( result[ 0 ].fields[ 0 ].label ).toBe( 'only here' );
		expect( result[ 1 ].fields[ 0 ].label ).toBeUndefined();
	} );
} );
