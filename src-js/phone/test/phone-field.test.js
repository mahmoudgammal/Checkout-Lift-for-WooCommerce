const PHONE_SCRIPT = '../../../checkout-lift-for-woocommerce/assets/js/modules/phone.js';

let checkoutHandler;
let countryHandler;
let phoneWidget;

function jquery( target ) {
	if ( typeof target === 'function' ) {
		target();
		return undefined;
	}

	return {
		val: () => ( typeof target === 'string' ? document.querySelector( target )?.value : target?.value ),
		on: ( event, handler ) => {
			if ( target === 'form.checkout' && event === 'checkout_place_order' ) {
				checkoutHandler = handler;
			}
			if ( target === '#billing_country' && event === 'change' ) {
				countryHandler = handler;
			}
		},
		animate: jest.fn(),
		offset: () => ( { top: 100 } ),
		trigger: jest.fn(),
	};
}

function loadPhoneField( phone = '' ) {
	document.body.innerHTML = `
		<form class="checkout">
			<select id="billing_country">
				<option value="EG" selected>Egypt</option>
				<option value="SA">Saudi Arabia</option>
			</select>
			<p><input id="billing_phone" value="${ phone }"></p>
		</form>
	`;

	window.scfePhone = {
		initialCountry: 'sa',
		errors: {
			invalid: 'Invalid',
			invalidCode: 'Bad code',
			tooShort: 'Too short',
			tooLong: 'Too long',
		},
	};
	window.intlTelInput = jest.fn( () => phoneWidget );
	global.jQuery = jquery;
	jest.isolateModules( () => require( PHONE_SCRIPT ) );
}

describe( 'international phone field', () => {
	beforeEach( () => {
		checkoutHandler = undefined;
		countryHandler = undefined;
		phoneWidget = {
			getNumber: jest.fn( () => '+201001234567' ),
			getValidationError: jest.fn( () => 'TOO_SHORT' ),
			isValidNumber: jest.fn( () => false ),
			setNumber: jest.fn(),
			setSelectedCountry: jest.fn(),
		};
		jest.resetModules();
	} );

	afterEach( () => {
		delete global.jQuery;
		delete window.intlTelInput;
		delete window.scfePhone;
		document.body.innerHTML = '';
	} );

	test( 'uses the current library API and follows the billing country', () => {
		loadPhoneField( '+201001234567' );

		expect( window.intlTelInput ).toHaveBeenCalledWith(
			document.querySelector( '#billing_phone' ),
			expect.objectContaining( {
				allowedNumberTypes: null,
				countryOrder: expect.arrayContaining( [ 'eg', 'sa', 'us' ] ),
				initialCountry: 'eg',
				showFlags: true,
			} )
		);
		expect( phoneWidget.setNumber ).toHaveBeenCalledWith( '+201001234567' );

		document.querySelector( '#billing_country' ).value = 'SA';
		countryHandler.call( document.querySelector( '#billing_country' ) );
		expect( phoneWidget.setSelectedCountry ).toHaveBeenCalledWith( 'sa' );
	} );

	test( 'maps string validation errors and blocks an invalid checkout', () => {
		loadPhoneField( '123' );
		const phoneInput = document.querySelector( '#billing_phone' );

		phoneInput.dispatchEvent( new Event( 'blur' ) );
		expect( document.querySelector( '.scfe-phone-error' ).textContent ).toBe( 'Too short' );
		expect( checkoutHandler() ).toBe( false );
	} );

	test( 'normalizes a valid number before checkout', () => {
		phoneWidget.isValidNumber.mockReturnValue( true );
		loadPhoneField( '01001234567' );
		const phoneInput = document.querySelector( '#billing_phone' );

		expect( checkoutHandler() ).toBe( true );
		expect( phoneInput.value ).toBe( '+201001234567' );
	} );
} );
