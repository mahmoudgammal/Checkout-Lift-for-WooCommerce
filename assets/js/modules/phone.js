( function( $ ) {
	'use strict';

	var config = window.scfePhone || {};
	var preferredCountries = [ 'sa', 'eg', 'ae', 'kw', 'qa', 'bh', 'om', 'us', 'gb' ];

	function initializePhoneField() {
		var phoneInput = document.querySelector( '#billing_phone' );

		if ( ! phoneInput || typeof window.intlTelInput === 'undefined' ) {
			return;
		}

		var errorMessage = createErrorMessage( phoneInput );
		var phoneWidget = createPhoneWidget( phoneInput );

		seedPrefilledNumber( phoneInput, phoneWidget );
		bindValidation( phoneInput, phoneWidget, errorMessage );
		bindCountryChange( phoneWidget, errorMessage, phoneInput );
		normalizeBeforeCheckout( phoneInput, phoneWidget, errorMessage );
	}

	function createErrorMessage( phoneInput ) {
		var errorMessage = document.createElement( 'span' );

		errorMessage.classList.add( 'scfe-phone-error' );
		errorMessage.style.display = 'none';
		phoneInput.parentNode.insertBefore( errorMessage, phoneInput.nextSibling );

		return errorMessage;
	}

	function createPhoneWidget( phoneInput ) {
		var billingCountry = String( $( '#billing_country' ).val() || '' ).toLowerCase();
		var initialCountry = billingCountry || config.initialCountry || '';
		var phoneWidget = window.intlTelInput( phoneInput, {
			allowedNumberTypes: null,
			separateDialCode: true,
			showFlags: true,
			initialCountry: initialCountry,
			countryOrder: preferredCountries
		} );

		return phoneWidget;
	}

	function seedPrefilledNumber( phoneInput, phoneWidget ) {
		var normalizedNumber = normalizedPrefill( phoneInput.value );

		if ( ! normalizedNumber ) {
			return;
		}

		phoneWidget.setNumber( normalizedNumber );
	}

	function normalizedPrefill( phoneNumber ) {
		var normalizedNumber = String( phoneNumber || '' ).trim().replace( /[^\d+]/g, '' );

		if ( normalizedNumber.charAt( 0 ) === '+' ) {
			return normalizedNumber;
		}

		if ( normalizedNumber.slice( 0, 2 ) === '00' ) {
			return '+' + normalizedNumber.slice( 2 );
		}

		if ( /^[1-9]\d{9,14}$/.test( normalizedNumber ) ) {
			return '+' + normalizedNumber;
		}

		return '';
	}

	function bindValidation( phoneInput, phoneWidget, errorMessage ) {
		var clear = function() {
			clearError( phoneInput, errorMessage );
		};

		phoneInput.addEventListener( 'blur', function() {
			clear();

			if ( phoneInput.value.trim() && ! isValidPhone( phoneInput, phoneWidget ) ) {
				showError( phoneInput, phoneWidget, errorMessage );
			}
		} );
		phoneInput.addEventListener( 'change', clear );
		phoneInput.addEventListener( 'keyup', clear );
	}

	function bindCountryChange( phoneWidget, errorMessage, phoneInput ) {
		$( '#billing_country' ).on( 'change', function() {
			var countryCode = String( $( this ).val() || '' ).toLowerCase();

			if ( countryCode ) {
				phoneWidget.setSelectedCountry( countryCode );
			}

			clearError( phoneInput, errorMessage );
		} );
	}

	function normalizeBeforeCheckout( phoneInput, phoneWidget, errorMessage ) {
		$( 'form.checkout' ).on( 'checkout_place_order', function() {
			if ( ! phoneInput.value.trim() ) {
				return true;
			}

			if ( isValidPhone( phoneInput, phoneWidget ) ) {
				phoneInput.value = phoneWidget.getNumber();
				return true;
			}

			showError( phoneInput, phoneWidget, errorMessage );
			$( 'html, body' ).animate( { scrollTop: $( phoneInput ).offset().top - 100 }, 500 );
			$( document.body ).trigger( 'checkout_error' );
			return false;
		} );
	}

	function isValidPhone( phoneInput, phoneWidget ) {
		return ! phoneInput.value.trim() || phoneWidget.isValidNumber() === true;
	}

	function showError( phoneInput, phoneWidget, errorMessage ) {
		var errors = config.errors || {};
		var errorMap = {
			INVALID_COUNTRY_CODE: errors.invalidCode || 'Invalid country code',
			TOO_SHORT: errors.tooShort || 'Phone number is too short',
			TOO_LONG: errors.tooLong || 'Phone number is too long',
			INVALID_LENGTH: errors.invalid || 'Invalid phone number',
			IS_POSSIBLE_LOCAL_ONLY: errors.invalid || 'Invalid phone number'
		};
		var validationError = phoneWidget.getValidationError();

		phoneInput.classList.add( 'error' );
		errorMessage.textContent = errorMap[ validationError ] || errors.invalid || 'Invalid phone number';
		errorMessage.style.display = 'block';
	}

	function clearError( phoneInput, errorMessage ) {
		phoneInput.classList.remove( 'error' );
		errorMessage.textContent = '';
		errorMessage.style.display = 'none';
	}

	$( initializePhoneField );
} )( jQuery );
