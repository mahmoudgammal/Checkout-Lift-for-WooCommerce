/**
 * Lite revenue overview behavior.
 *
 * @package SimpleCheckoutFieldsEditor
 */

import { fireEvent, render, screen, waitFor } from '@testing-library/react';
import apiFetch from '@wordpress/api-fetch';

import RevenueDashboard from '../RevenueDashboard';

jest.mock( '@wordpress/api-fetch', () => jest.fn() );

const response = {
	tracking: true,
	filters: { currency: 'EGP', currencies: [ 'EGP', 'USD' ], runtime: 'all', status: 'all' },
	totals: { attempts: 20, open: 2, abandoned: 8, placed: 10, placement_rate: 50, abandoned_value: '1200.0000', recognized_revenue: '2100.0000' },
	recovery: { net: '650.0000' },
};

describe( 'RevenueDashboard Lite', () => {
	beforeEach( () => {
		delete window.scfe_admin_pro;
		apiFetch.mockReset();
		apiFetch.mockResolvedValue( response );
	} );

	it( 'shows potential value without Pro-only analytics', async () => {
		render( <RevenueDashboard /> );

		await screen.findByText( 'Checkout funnel' );
		screen.getByText( 'Potential abandoned checkout value' );
		screen.getByText( 'Captured cart value; not guaranteed lost or recoverable revenue' );
		expect( screen.queryByText( 'Recognized revenue' ) ).toBeNull();
		expect( screen.queryByLabelText( 'Checkout' ) ).toBeNull();
		screen.getByRole( 'link', { name: 'Explore Pro recovery' } );
	} );

	it( 'refetches when the merchant changes a Lite filter', async () => {
		render( <RevenueDashboard /> );

		fireEvent.change( await screen.findByLabelText( 'Period' ), { target: { value: '7' } } );

		await waitFor( () => expect( apiFetch.mock.calls.some( ( [ request ] ) => request.path.includes( 'period=7' ) ) ).toBe( true ) );
	} );
} );
