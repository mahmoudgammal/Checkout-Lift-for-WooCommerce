/**
 * Money and date formatting.
 *
 * @package SimpleCheckoutFieldsEditor
 */

import { formatMoney, formatDate } from '../format';

describe( 'formatMoney', () => {
	it( 'formats an amount in the given currency', () => {
		const result = formatMoney( 1234.5, 'USD' );

		expect( result ).toContain( '1,234.50' );
	} );

	it( 'treats a missing or unreadable amount as zero rather than NaN', () => {
		expect( formatMoney( undefined, 'USD' ) ).toContain( '0.00' );
		expect( formatMoney( 'not a number', 'USD' ) ).toContain( '0.00' );
		expect( formatMoney( null, 'USD' ) ).toContain( '0.00' );
	} );

	it( 'falls back readably when the currency code is not one Intl knows', () => {
		const result = formatMoney( 25, 'NOTREAL' );

		expect( result ).toContain( '25.00' );
		expect( result ).toContain( 'NOTREAL' );
	} );

	it( 'still produces something when no currency is given', () => {
		expect( formatMoney( 10, '' ) ).toContain( '10.00' );
	} );
} );

describe( 'formatDate', () => {
	it( 'reads stored timestamps as UTC, not as local time', () => {
		// The stored form carries no zone marker. Parsing it as local time
		// would shift every timestamp by the viewer's offset, which is the
		// defect this exists to prevent.
		const asUtc = new Date( '2026-08-03T22:30:00Z' ).toLocaleString();

		expect( formatDate( '2026-08-03 22:30:00' ) ).toBe( asUtc );
	} );

	it( 'returns nothing for an empty value instead of the epoch', () => {
		expect( formatDate( '' ) ).toBe( '' );
		expect( formatDate( null ) ).toBe( '' );
		expect( formatDate( undefined ) ).toBe( '' );
	} );

	it( 'returns the original text when it cannot be parsed', () => {
		expect( formatDate( 'not a date' ) ).toBe( 'not a date' );
	} );
} );
