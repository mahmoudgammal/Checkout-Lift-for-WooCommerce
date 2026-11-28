/**
 * The recent checkout activity table.
 *
 * @package SimpleCheckoutFieldsEditor
 */

import { render, screen } from '@testing-library/react';

import RecentCarts from '../RecentCarts';

const cart = ( overrides = {} ) => ( {
	id: 1,
	status: 'abandoned',
	name: 'Nour Hassan',
	email: 'nour@example.test',
	phone: '01000000000',
	total: 600,
	currency: 'EGP',
	items: 2,
	country: 'EG',
	step: 'payment',
	consent: 1,
	order_id: 0,
	order_url: '',
	updated_at: '2026-08-03 12:00:00',
	...overrides,
} );

describe( 'RecentCarts', () => {
	it( 'says so plainly when nothing has been captured', () => {
		render( <RecentCarts carts={ [] } /> );

		// An empty table with headers reads as broken; a sentence does not.
		screen.getByText( /Nothing recorded yet/ );
	} );

	it( 'shows what the customer entered, which is the point of the screen', () => {
		render( <RecentCarts carts={ [ cart() ] } /> );

		screen.getByText( 'Nour Hassan' );
		expect( screen.getByText( 'nour@example.test' ).getAttribute( 'href' ) ).toBe(
			'mailto:nour@example.test'
		);
		expect( screen.getByText( '01000000000' ).getAttribute( 'href' ) ).toBe(
			'tel:01000000000'
		);
	} );

	it( 'carries the status as a class so the palette lives in one place', () => {
		render( <RecentCarts carts={ [ cart( { status: 'completed' } ) ] } /> );

		expect( screen.getByText( 'Completed' ).className ).toContain( 'is-completed' );
	} );

	it( 'lists what was in the cart, not only how many items', () => {
		render(
			<RecentCarts
				carts={ [ cart( { products: [ { name: 'Cold Peel', quantity: 2 }, { name: 'Serum', quantity: 1 } ] } ) ] }
			/>
		);

		screen.getByText( 'Cold Peel × 2' );
		screen.getByText( 'Serum × 1' );
	} );

	it( 'falls back to the item count for rows captured without product lines', () => {
		render( <RecentCarts carts={ [ cart( { items: 3, products: [] } ) ] } /> );

		screen.getByText( '3' );
	} );

	it( 'names the furthest step reached rather than showing its stored key', () => {
		render( <RecentCarts carts={ [ cart() ] } /> );

		screen.getByText( 'Payment' );
		expect( screen.queryByText( 'payment' ) ).toBeNull();
	} );

	it( 'flags a record kept without the customer agreeing', () => {
		render( <RecentCarts carts={ [ cart( { consent: 0 } ) ] } /> );

		screen.getByText( 'no permission' );
	} );

	it( 'links a completed checkout to its order', () => {
		render(
			<RecentCarts
				carts={ [
					cart( {
						status: 'completed',
						order_id: 42,
						order_url: 'admin.php?page=wc-orders&action=edit&id=42',
					} ),
				] }
			/>
		);

		expect( screen.getByText( '#42' ).getAttribute( 'href' ) ).toContain( 'wc-orders' );
	} );

	it( 'shows a dash rather than a blank cell for what it does not know', () => {
		render(
			<RecentCarts
				carts={ [ cart( { name: '', email: '', phone: '', country: '', step: '' } ) ] }
			/>
		);

		// Three dashes for customer, country and step, and one for the order.
		expect( screen.getAllByText( '—' ).length ).toBeGreaterThan( 2 );
	} );
} );
