/**
 * The field editor screen.
 *
 * @package SimpleCheckoutFieldsEditor
 */

import { render, screen, waitFor } from '@testing-library/react';
import apiFetch from '@wordpress/api-fetch';

import FieldsEditor from '../FieldsEditor';

jest.mock( '@wordpress/api-fetch' );

const response = {
	fingerprint: 'abc123',
	disable_shipping: false,
	payment_methods: [ { value: 'cod', label: 'Cash on delivery' } ],
	shipping_methods: [],
	sections: [
		{
			key: 'billing',
			label: 'Billing Fields',
			fields: [
				{
					key: 'billing_email',
					label: 'Email address',
					placeholder: '',
					width: 100,
					enabled: true,
					required: true,
					custom: false,
					type: 'text',
					settings: {},
					conditions: {},
					carried: {},
				},
			],
		},
	],
};

describe( 'FieldsEditor', () => {
	beforeEach( () => {
		apiFetch.mockReset();
		delete window.scfe_admin;
	} );

	// The exact sentence, not a substring: a loose match also matches every
	// ancestor that contains it, and the query fails on the ambiguity.
	const blockWarning = 'Your checkout page uses the Checkout block.';

	it( 'lists the fields it loads', async () => {
		apiFetch.mockResolvedValue( response );

		render( <FieldsEditor /> );

		// These queries throw when nothing matches, so finding is the assertion.
		await screen.findByText( 'billing_email' );

		// The label is editable in the settings panel, which opens on the first
		// field so the screen is not blank on arrival.
		screen.getByDisplayValue( 'Email address' );

		// Twice over: once on the section, once on the panel saying which
		// section the chosen field belongs to.
		expect( screen.getAllByText( 'Billing Fields' ).length ).toBeGreaterThan( 0 );
	} );

	it( 'hides the shipping fields while the shipping section is removed from the checkout', async () => {
		const shipping = {
			key: 'shipping',
			label: 'Shipping Fields',
			fields: [ { ...response.sections[ 0 ].fields[ 0 ], key: 'shipping_city', label: 'City' } ],
		};

		apiFetch.mockResolvedValue( {
			...response,
			disable_shipping: true,
			sections: [ ...response.sections, shipping ],
		} );

		render( <FieldsEditor /> );

		await screen.findByText( 'billing_email' );
		expect( screen.queryByText( 'shipping_city' ) ).toBeNull();
		expect( screen.queryByText( 'Shipping Fields' ) ).toBeNull();
	} );

	it( 'reports a failure instead of rendering an empty screen', async () => {
		apiFetch.mockRejectedValue( new Error( 'Network is down' ) );

		render( <FieldsEditor /> );

		await screen.findByText( 'Network is down' );
	} );

	it( 'marks a field that carries conditions', async () => {
		apiFetch.mockResolvedValue( {
			...response,
			sections: [
				{
					...response.sections[ 0 ],
					fields: [
						{
							...response.sections[ 0 ].fields[ 0 ],
							conditions: { field_dependencies: { action: 'show', rules: [ {} ] } },
						},
					],
				},
			],
		} );

		render( <FieldsEditor /> );

		await screen.findByText( 'conditional' );
	} );

	it( 'names the checkout it is configuring when that is the block one', async () => {
		// The shape wp_localize_script actually emits: it casts booleans to
		// "1" and "", not to true and false.
		window.scfe_admin = { blockCheckout: '1' };
		apiFetch.mockResolvedValue( response );

		render( <FieldsEditor /> );

		// A merchant needs to know which checkout they are configuring, and
		// that some settings on this screen carry a mark.
		await screen.findByText( blockWarning );
	} );

	it( 'stays quiet on a classic checkout, where everything here applies', async () => {
		window.scfe_admin = { blockCheckout: '' };
		apiFetch.mockResolvedValue( response );

		render( <FieldsEditor /> );

		await screen.findByText( 'billing_email' );

		expect( screen.queryByText( blockWarning ) ).toBeNull();
	} );

	it( 'stays quiet when the server said nothing about the checkout', async () => {
		apiFetch.mockResolvedValue( response );

		render( <FieldsEditor /> );

		await screen.findByText( 'billing_email' );

		// A missing value must not be read as a block checkout. Warning every
		// classic store would train merchants to ignore the notice.
		expect( screen.queryByText( blockWarning ) ).toBeNull();
	} );

	it( 'opens the settings panel on the first field rather than on nothing', async () => {
		apiFetch.mockResolvedValue( response );

		render( <FieldsEditor /> );

		// A blank panel on arrival makes the merchant guess that a row is
		// clickable. Opening on something says it.
		await screen.findByDisplayValue( 'Email address' );
		screen.getByText( 'Type, rules and conditions' );
	} );

	it( 'shows a field with no label by its key rather than as a blank row', async () => {
		apiFetch.mockResolvedValue( {
			...response,
			sections: [
				{
					...response.sections[ 0 ],
					fields: [ { ...response.sections[ 0 ].fields[ 0 ], label: '' } ],
				},
			],
		} );

		render( <FieldsEditor /> );

		expect( ( await screen.findAllByText( 'billing_email' ) ).length ).toBeGreaterThan( 1 );
	} );

	it( 'offers the two tabs this screen still owns', async () => {
		apiFetch.mockResolvedValue( response );

		render( <FieldsEditor /> );

		await waitFor( () => screen.getByText( 'Fields' ) );
		screen.getByText( 'Tools' );

		// Advanced moved to the settings screen, where the rest of the
		// plugin's settings are. Two places to change one thing was the
		// problem the settings screen exists to end.
		expect( screen.queryByText( 'Advanced' ) ).toBeNull();
	} );
} );
