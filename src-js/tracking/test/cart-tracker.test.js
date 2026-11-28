/**
 * The checkout tracker, on both checkouts.
 *
 * The tracker is a plain script rather than a module, so each case sets up the
 * page and the globals it expects and then loads it, the way a browser would.
 *
 * @package SimpleCheckoutFieldsEditor
 */

const TRACKER = '../../../checkout-lift-for-woocommerce/assets/js/cart-tracker.js';

const BLOCK_MARKUP = '<div class="wp-block-woocommerce-checkout"></div>';

let subscribers = [];

// Each case loads the tracker again, and every load registers its own
// document listener. Without unregistering them the instance under test shares
// the page with every instance before it, and they all answer at once.
let documentListeners = [];

/**
 * Install the Blocks data stores the tracker reads.
 *
 * @param {Object} stores Store name to selectors.
 */
function installStores( stores ) {
	subscribers = [];

	window.wp = {
		data: {
			select: ( name ) => stores[ name ] || null,
			subscribe: ( callback ) => subscribers.push( callback ),
		},
	};
}

/**
 * Build a cart store from a billing address and cart data.
 *
 * @param {Object} billing  Billing address.
 * @param {Object} cartData Cart data.
 * @return {Object} Cart store selectors.
 */
function cartStore( billing, cartData = {} ) {
	return {
		getCustomerData: () => ( { billingAddress: billing } ),
		getCartData: () => cartData,
	};
}

/**
 * Load the tracker and run every timer it sets.
 */
function run() {
	jest.isolateModules( () => require( TRACKER ) );

	subscribers.forEach( ( callback ) => callback() );
	jest.runOnlyPendingTimers();
}

/**
 * Hide the page, the way a customer switching away from it does.
 */
function hide() {
	Object.defineProperty( document, 'visibilityState', {
		value: 'hidden',
		configurable: true,
	} );

	document.dispatchEvent( new Event( 'visibilitychange' ) );
}

/**
 * Return the body of the only capture request that was sent.
 *
 * @return {Object} Parsed payload.
 */
function sent() {
	expect( global.fetch ).toHaveBeenCalledTimes( 1 );

	return JSON.parse( global.fetch.mock.calls[ 0 ][ 1 ].body );
}

describe( 'cart tracker', () => {
	beforeEach( () => {
		jest.useFakeTimers();

		global.fetch = jest.fn( () => Promise.resolve( { status: 200 } ) );

		window.scfe_tracking = {
			endpoint: 'https://example.test/wp-json/scfe/v1/capture',
			nonce: 'nonce',
			debounce: 3000,
			consentRequired: false,
			consentField: 'scfe-recovery-consent',
			blockConsentField: 'scfe/recovery-consent',
		};

		delete window.scfe;
		delete window.wp;
		document.body.innerHTML = '';
		subscribers = [];
		documentListeners = [];

		const add = EventTarget.prototype.addEventListener;

		jest.spyOn( document, 'addEventListener' ).mockImplementation( function ( type, handler, options ) {
			documentListeners.push( [ type, handler ] );
			add.call( document, type, handler, options );
		} );
	} );

	afterEach( () => {
		documentListeners.forEach( ( [ type, handler ] ) => document.removeEventListener( type, handler ) );

		jest.restoreAllMocks();
		jest.useRealTimers();
	} );

	describe( 'on the Blocks checkout', () => {
		it( 'reports what the customer has entered, read from the store', () => {
			document.body.innerHTML = BLOCK_MARKUP;
			installStores( {
				'wc/store/cart': cartStore( {
					email: ' shopper@example.test ',
					phone: '01000000000',
					first_name: 'Nour',
					last_name: 'Hassan',
					country: 'EG',
					address_1: '',
				} ),
				'wc/store/payment': { getActivePaymentMethod: () => 'cod' },
			} );

			run();

			const body = sent();

			expect( body.email ).toBe( 'shopper@example.test' );
			expect( body.phone ).toBe( '01000000000' );
			expect( body.first_name ).toBe( 'Nour' );
			expect( body.last_name ).toBe( 'Hassan' );
			expect( body.country ).toBe( 'EG' );
			expect( body.payment_method ).toBe( 'cod' );
		} );

		it( 'never sends a total, which only the server may decide', () => {
			document.body.innerHTML = BLOCK_MARKUP;
			installStores( {
				'wc/store/cart': cartStore(
					{ email: 'shopper@example.test' },
					{ totals: { total_price: '9900' } }
				),
			} );

			run();

			const body = sent();

			expect( body ).not.toHaveProperty( 'total' );
			expect( body ).not.toHaveProperty( 'currency' );
			expect( body ).not.toHaveProperty( 'items' );
		} );

		it( 'calls an address-less checkout the contact step', () => {
			document.body.innerHTML = BLOCK_MARKUP;
			installStores( {
				'wc/store/cart': cartStore( { email: 'shopper@example.test', address_1: '' } ),
			} );

			run();

			expect( sent().checkout_step ).toBe( 'contact' );
		} );

		it( 'holds at the shipping step until a rate is chosen', () => {
			document.body.innerHTML = BLOCK_MARKUP;
			installStores( {
				'wc/store/cart': cartStore(
					{
						email: 'shopper@example.test',
						address_1: '5 Nile Street',
						city: 'Cairo',
						country: 'EG',
					},
					{
						needsShipping: true,
						shippingRates: [ { shipping_rates: [ { rate_id: 'flat_rate:1', selected: false } ] } ],
					}
				),
			} );

			run();

			expect( sent().checkout_step ).toBe( 'shipping' );
		} );

		it( 'reaches the payment step once the address and a rate are settled', () => {
			document.body.innerHTML = BLOCK_MARKUP;
			installStores( {
				'wc/store/cart': cartStore(
					{
						email: 'shopper@example.test',
						address_1: '5 Nile Street',
						city: 'Cairo',
						country: 'EG',
					},
					{
						needsShipping: true,
						shippingRates: [ { shipping_rates: [ { rate_id: 'flat_rate:1', selected: true } ] } ],
					}
				),
			} );

			run();

			const body = sent();

			expect( body.checkout_step ).toBe( 'payment' );
			expect( body.shipping_method ).toBe( 'flat_rate:1' );
		} );

		it( 'sends nothing when there is no way to reach the customer', () => {
			document.body.innerHTML = BLOCK_MARKUP;
			installStores( {
				'wc/store/cart': cartStore( { first_name: 'Nour', address_1: '5 Nile Street' } ),
			} );

			run();

			expect( global.fetch ).not.toHaveBeenCalled();
		} );

		it( 'withholds an unticked consent, and reports a ticked one', () => {
			document.body.innerHTML = BLOCK_MARKUP;
			window.scfe_tracking.consentRequired = true;

			const fields = { 'scfe/recovery-consent': false };

			installStores( {
				'wc/store/cart': cartStore( { email: 'shopper@example.test' } ),
				'wc/store/checkout': { getAdditionalFields: () => fields },
			} );

			run();

			expect( sent().consent ).toBe( false );

			fields[ 'scfe/recovery-consent' ] = true;
			subscribers.forEach( ( callback ) => callback() );
			jest.runOnlyPendingTimers();

			expect( global.fetch ).toHaveBeenCalledTimes( 2 );
			expect( JSON.parse( global.fetch.mock.calls[ 1 ][ 1 ].body ).consent ).toBe( true );
		} );

		it( 'reads consent from the rendered input when the store cannot answer', () => {
			// The name WooCommerce actually renders: it prefixes the field with
			// the location it chose, and rewrites the id further still.
			document.body.innerHTML =
				BLOCK_MARKUP +
				'<input type="checkbox" id="contact-scfe-recovery-consent"' +
				' name="contact_scfe/recovery-consent" checked>';
			window.scfe_tracking.consentRequired = true;

			installStores( {
				'wc/store/cart': cartStore( { email: 'shopper@example.test' } ),
			} );

			run();

			expect( sent().consent ).toBe( true );
		} );

		it( 'waits for stores that have not registered yet instead of giving up', () => {
			document.body.innerHTML = BLOCK_MARKUP;
			installStores( {} );

			jest.isolateModules( () => require( TRACKER ) );

			expect( subscribers ).toHaveLength( 0 );

			// The checkout block's scripts run and register the store.
			const stores = { 'wc/store/cart': cartStore( { email: 'shopper@example.test' } ) };
			window.wp.data.select = ( name ) => stores[ name ] || null;

			jest.advanceTimersByTime( 250 );

			expect( subscribers ).toHaveLength( 1 );

			subscribers.forEach( ( callback ) => callback() );
			jest.runOnlyPendingTimers();

			expect( sent().email ).toBe( 'shopper@example.test' );
		} );

		it( 'does not resend an unchanged state every time the page is hidden', () => {
			document.body.innerHTML = BLOCK_MARKUP;
			installStores( {
				'wc/store/cart': cartStore( { email: 'shopper@example.test' } ),
			} );

			run();

			expect( global.fetch ).toHaveBeenCalledTimes( 1 );

			// A page can be hidden and shown any number of times. Resending
			// what the server already holds on each one is pure traffic, and
			// on a browser that toggles visibility on a timer it never stops.
			hide();
			hide();
			hide();

			expect( global.fetch ).toHaveBeenCalledTimes( 1 );
		} );

		it( 'sends what is still pending when the customer leaves', () => {
			document.body.innerHTML = BLOCK_MARKUP;

			const billing = { email: 'shopper@example.test' };

			installStores( { 'wc/store/cart': cartStore( billing ) } );

			run();

			expect( global.fetch ).toHaveBeenCalledTimes( 1 );

			// A change the debounce has not released yet.
			billing.phone = '01000000000';
			subscribers.forEach( ( callback ) => callback() );

			hide();

			expect( global.fetch ).toHaveBeenCalledTimes( 2 );
			expect( JSON.parse( global.fetch.mock.calls[ 1 ][ 1 ].body ).phone ).toBe( '01000000000' );
		} );

		it( 'carries a throttled state into the next send instead of losing it', async () => {
			document.body.innerHTML = BLOCK_MARKUP;
			global.fetch = jest.fn( () => Promise.resolve( { status: 429, ok: false } ) );

			installStores( {
				'wc/store/cart': cartStore( { email: 'shopper@example.test' } ),
			} );

			run();

			expect( global.fetch ).toHaveBeenCalledTimes( 1 );

			// Let the refusal be read before anything else happens.
			await Promise.resolve();
			await Promise.resolve();

			hide();

			// The server never stored the first attempt, so the same state has
			// to go out again rather than be suppressed as already sent.
			expect( global.fetch ).toHaveBeenCalledTimes( 2 );
		} );

		it( 'runs without the jQuery bridge, which that checkout does not load', () => {
			document.body.innerHTML = BLOCK_MARKUP;
			installStores( {
				'wc/store/cart': cartStore( { email: 'shopper@example.test' } ),
			} );

			expect( window.scfe ).toBeUndefined();

			run();

			expect( global.fetch ).toHaveBeenCalledTimes( 1 );
		} );
	} );

	describe( 'on the classic checkout', () => {
		/**
		 * Install a bridge that records what the tracker binds to.
		 *
		 * @return {Object} The handlers the tracker registered.
		 */
		function installBridge() {
			const bound = { fields: [], events: {} };

			window.scfe = {
				wc: {
					ready: ( callback ) => callback(),
					on: ( event, callback ) => {
						bound.events[ event ] = callback;
					},
					onField: ( field, events, callback ) => {
						bound.fields.push( { field, events, callback } );
					},
				},
			};

			return bound;
		}

		it( 'reads the form, not the data stores', () => {
			document.body.innerHTML =
				'<input id="billing_email" value="shopper@example.test">' +
				'<input id="billing_first_name" value="Nour">' +
				'<input id="billing_address_1" value="5 Nile Street">';

			const bound = installBridge();

			jest.isolateModules( () => require( TRACKER ) );

			bound.fields[ 0 ].callback();
			jest.runOnlyPendingTimers();

			const body = sent();

			expect( body.email ).toBe( 'shopper@example.test' );
			expect( body.first_name ).toBe( 'Nour' );
			expect( body.checkout_step ).toBe( 'shipping' );
		} );

		it( 'stores the phone with its country code when the phone widget is active', () => {
			document.body.innerHTML = '<input id="billing_phone" value="10 14000159">';
			window.intlTelInput = {
				getInstance: () => ( { getNumber: () => '+201014000159' } ),
			};

			const bound = installBridge();

			jest.isolateModules( () => require( TRACKER ) );

			bound.fields[ 0 ].callback();
			jest.runOnlyPendingTimers();

			expect( sent().phone ).toBe( '+201014000159' );

			delete window.intlTelInput;
		} );

		it( 'binds through the bridge, because Select2 tells jQuery only', () => {
			document.body.innerHTML = '<select id="billing_country"><option value="EG" selected>EG</option></select>';

			const bound = installBridge();

			jest.isolateModules( () => require( TRACKER ) );

			expect( bound.fields.map( ( entry ) => entry.field.id ) ).toContain( 'billing_country' );
			expect( bound.events ).toHaveProperty( 'updated_checkout' );
		} );

		it( 'does nothing at all without the bridge it depends on', () => {
			document.body.innerHTML = '<input id="billing_email" value="shopper@example.test">';

			jest.isolateModules( () => require( TRACKER ) );
			jest.runOnlyPendingTimers();

			expect( global.fetch ).not.toHaveBeenCalled();
		} );
	} );
} );
