/**
 * Shared formatting helpers.
 *
 * @package SimpleCheckoutFieldsEditor
 */

/**
 * Format an amount for display.
 *
 * Uses the browser's locale rather than a hardcoded pattern, so a store
 * reading this in Arabic or German sees its own conventions. Falls back to a
 * plain join when the currency code is one Intl does not recognise.
 *
 * @param {number} amount   Amount.
 * @param {string} currency ISO currency code.
 * @return {string} Formatted amount.
 */
export function formatMoney( amount, currency ) {
	const value = Number( amount ) || 0;

	try {
		return new Intl.NumberFormat( undefined, {
			style: 'currency',
			currency: currency || 'USD',
			maximumFractionDigits: 2,
		} ).format( value );
	} catch ( e ) {
		return `${ value.toFixed( 2 ) } ${ currency || '' }`.trim();
	}
}

/**
 * Format a stored UTC timestamp as a readable local date and time.
 *
 * @param {string} utc MySQL datetime in UTC.
 * @return {string} Formatted timestamp.
 */
export function formatDate( utc ) {
	if ( ! utc ) {
		return '';
	}

	// Stored values are UTC but carry no zone marker, so one is added before
	// parsing. Without it the browser reads them as local time and every
	// timestamp is wrong by the viewer's offset.
	const parsed = new Date( `${ utc.replace( ' ', 'T' ) }Z` );

	if ( Number.isNaN( parsed.getTime() ) ) {
		return utc;
	}

	return parsed.toLocaleString();
}
