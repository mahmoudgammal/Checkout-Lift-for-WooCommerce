/**
 * Lite recovery settings.
 *
 * @package SimpleCheckoutFieldsEditor
 */

import { fireEvent, render, screen, waitFor } from '@testing-library/react';
import apiFetch from '@wordpress/api-fetch';

import Settings from '../Settings';

jest.mock( '@wordpress/api-fetch', () => jest.fn() );

const settings = {
	tracking_enabled: true,
	require_consent: true,
	consent_text: 'I agree',
	retention_days: 30,
	delete_on_uninstall: false,
	idle_minutes: 30,
	email_sequence: false,
};

describe( 'Lite recovery settings', () => {
	beforeEach( () => {
		delete window.scfe_admin_extensions;
		apiFetch.mockReset();
		apiFetch.mockResolvedValue( settings );
	} );

	it( 'shows tracking controls and one contextual recovery upgrade', async () => {
		render( <Settings /> );

		await screen.findByLabelText( 'Treat a checkout as abandoned after' );
		expect( screen.queryByLabelText( 'Send the recovery email after' ) ).toBeNull();
		screen.getByRole( 'button', { name: 'Explore Pro recovery' } );
	} );

	it( 'submits the Lite abandonment threshold', async () => {
		render( <Settings /> );

		fireEvent.change( await screen.findByLabelText( 'Treat a checkout as abandoned after' ), { target: { value: '60' } } );
		fireEvent.click( screen.getByRole( 'button', { name: 'Save settings' } ) );

		await waitFor( () => expect( apiFetch ).toHaveBeenCalledTimes( 2 ) );
		expect( apiFetch.mock.calls[1][0] ).toMatchObject( { method: 'POST', data: expect.objectContaining( { idle_minutes: 60 } ) } );
	} );
} );
