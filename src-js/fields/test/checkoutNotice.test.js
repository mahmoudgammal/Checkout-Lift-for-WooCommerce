/**
 * What the editor says about the block checkout.
 *
 * @package SimpleCheckoutFieldsEditor
 */

import {
	annotateTypes,
	annotateFieldOperators,
	widthNote,
	usesBlockCheckout,
} from '../CheckoutNotice';

const types = () => [
	{ value: 'text', label: 'Text' },
	{ value: 'select', label: 'Select' },
	{ value: 'number', label: 'Number' },
];

describe( 'usesBlockCheckout', () => {
	afterEach( () => {
		delete window.scfe_admin;
	} );

	it( 'reads the string form wp_localize_script actually sends', () => {
		window.scfe_admin = { blockCheckout: '1' };
		expect( usesBlockCheckout() ).toBe( true );

		window.scfe_admin = { blockCheckout: '' };
		expect( usesBlockCheckout() ).toBe( false );
	} );

	it( 'treats a missing answer as a classic checkout', () => {
		expect( usesBlockCheckout() ).toBe( false );

		window.scfe_admin = {};
		expect( usesBlockCheckout() ).toBe( false );
	} );
} );

describe( 'annotateTypes', () => {
	afterEach( () => {
		delete window.scfe_admin;
	} );

	it( 'says nothing on a classic checkout, where every type works', () => {
		const original = types();

		expect( annotateTypes( original ) ).toBe( original );
	} );


	it( 'marks the types that appear but lose their input type', () => {
		window.scfe_admin = { blockCheckout: '1' };

		const byValue = Object.fromEntries(
			annotateTypes( types() ).map( ( t ) => [ t.value, t.label ] )
		);

		expect( byValue.number ).toContain( 'plain text' );
	} );

	it( 'leaves the two types that work untouched', () => {
		window.scfe_admin = { blockCheckout: '1' };

		const byValue = Object.fromEntries(
			annotateTypes( types() ).map( ( t ) => [ t.value, t.label ] )
		);

		// A qualifier on a type that works would be worse than none at all.
		expect( byValue.text ).toBe( 'Text' );
		expect( byValue.select ).toBe( 'Select' );
	} );
} );

describe( 'annotateFieldOperators', () => {
	afterEach( () => {
		delete window.scfe_admin;
	} );

	const operators = () => [
		{ value: 'is', label: 'is' },
		{ value: 'is_not', label: 'is not' },
		{ value: 'contains', label: 'contains' },
		{ value: 'greater_than', label: 'greater than' },
	];

	it( 'says nothing on a classic checkout, where every operator works', () => {
		const original = operators();

		expect( annotateFieldOperators( original ) ).toBe( original );
	} );

	it( 'marks the comparisons the block checkout cannot express', () => {
		window.scfe_admin = { blockCheckout: '1' };

		const byValue = Object.fromEntries(
			annotateFieldOperators( operators() ).map( ( o ) => [ o.value, o.label ] )
		);

		expect( byValue.contains ).toContain( 'not on the Checkout block' );
		expect( byValue.greater_than ).toContain( 'not on the Checkout block' );

		// Equality is what does carry, and a qualifier on it would mislead.
		expect( byValue.is ).toBe( 'is' );
		expect( byValue.is_not ).toBe( 'is not' );
	} );
} );


describe( 'widthNote', () => {
	afterEach( () => {
		delete window.scfe_admin;
	} );

	it( 'says nothing on a classic checkout, where width applies to everything', () => {
		expect( widthNote( { custom: true } ) ).toBe( '' );
	} );

	it( 'leaves a built-in address field alone, its width being honoured', () => {
		window.scfe_admin = { blockCheckout: '1' };

		expect( widthNote( { custom: false } ) ).toBe( '' );
	} );

	it( 'marks a custom field, which the block checkout stacks rather than rows', () => {
		window.scfe_admin = { blockCheckout: '1' };

		expect( widthNote( { custom: true } ) ).toContain( 'not on the Checkout block' );
	} );
} );
