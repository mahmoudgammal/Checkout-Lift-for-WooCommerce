( function( $ ) {
	'use strict';

	var visibilityUpdateTimeout;

	function parsedConditions( conditionsData ) {
		try {
			return JSON.parse( conditionsData );
		} catch ( error ) {
			if ( error instanceof SyntaxError ) {
				return null;
			}

			throw error;
		}
	}

	function fieldValue( fieldId ) {
		var $field = $( '#' + fieldId );

		if ( ! $field.length ) {
			$field = $( '[id="' + fieldId + '"]' );
		}

		if ( ! $field.length ) {
			return '';
		}

		if ( $field.is( ':checkbox' ) ) {
			return $field.is( ':checked' ) ? '1' : '';
		}

		if ( $field.is( ':radio' ) ) {
			return $( 'input[name="' + $field.attr( 'name' ) + '"]:checked' ).val() || '';
		}

		return $field.val() || '';
	}

	function evaluateRule( rule ) {
		var currentValue = fieldValue( rule.field );
		var targetValue = rule.value || '';

		switch ( rule.operator ) {
			case 'is':
				return currentValue === targetValue;
			case 'is_not':
				return currentValue !== targetValue;
			case 'contains':
				return currentValue.toLowerCase().indexOf( targetValue.toLowerCase() ) !== -1;
			case 'not_contains':
				return currentValue.toLowerCase().indexOf( targetValue.toLowerCase() ) === -1;
			case 'starts_with':
				return currentValue.toLowerCase().indexOf( targetValue.toLowerCase() ) === 0;
			case 'ends_with':
				return currentValue.toLowerCase().slice( -targetValue.length ) === targetValue.toLowerCase();
			case 'is_empty':
				return currentValue === '' || currentValue === null || currentValue === undefined;
			case 'is_not_empty':
				return currentValue !== '' && currentValue !== null && currentValue !== undefined;
			case 'greater_than':
				return parseFloat( currentValue ) > parseFloat( targetValue );
			case 'less_than':
				return parseFloat( currentValue ) < parseFloat( targetValue );
			default:
				return false;
		}
	}

	function evaluateConditions( conditions ) {
		var results = [];

		if ( ! conditions || ! conditions.rules || conditions.rules.length === 0 ) {
			return true;
		}

		conditions.rules.forEach( function( rule ) {
			results.push( evaluateRule( rule ) );
		} );

		if ( ( conditions.logic || 'all' ) === 'all' ) {
			return results.every( function( result ) {
				return result;
			} );
		}

		return results.some( function( result ) {
			return result;
		} );
	}

	function fieldConditionsData( $wrapper ) {
		var conditionsData = $wrapper.attr( 'data-scfe-conditions' );
		var $input;

		if ( conditionsData ) {
			return conditionsData;
		}

		$input = $wrapper.find( 'input, select, textarea' ).first();

		return $input.length ? $input.attr( 'data-scfe-conditions' ) : '';
	}

	function processConditionalField( $wrapper ) {
		var conditions = parsedConditions( fieldConditionsData( $wrapper ) || '' );
		var shouldShow;

		if ( ! conditions ) {
			return;
		}

		shouldShow = evaluateConditions( conditions );

		if ( ( conditions.action || 'show' ) === 'hide' ) {
			shouldShow = ! shouldShow;
		}

		if ( shouldShow ) {
			$wrapper.slideDown( 200 );
			$wrapper.find( 'input, select, textarea' ).prop( 'disabled', false );
			return;
		}

		$wrapper.slideUp( 200 );
		$wrapper.find( 'input, select, textarea' ).prop( 'disabled', true );
	}

	function updateFieldVisibility() {
		window.clearTimeout( visibilityUpdateTimeout );
		visibilityUpdateTimeout = window.setTimeout( function() {
			$( '.scfe-conditional-field' ).each( function() {
				processConditionalField( $( this ) );
			} );

			$( '.form-row input[data-scfe-conditions], .form-row select[data-scfe-conditions], .form-row textarea[data-scfe-conditions]' ).each( function() {
				var $wrapper = $( this ).closest( '.form-row' );

				$wrapper.addClass( 'scfe-conditional-field' );
				processConditionalField( $wrapper );
			} );
		}, 100 );
	}

	function watchedFields() {
		var fields = [];

		function extractFields( conditionsData ) {
			var conditions = parsedConditions( conditionsData || '' );

			if ( ! conditions || ! conditions.rules ) {
				return;
			}

			conditions.rules.forEach( function( rule ) {
				var selector = '#' + rule.field;

				if ( fields.indexOf( selector ) === -1 ) {
					fields.push( selector );
				}
			} );
		}

		$( '.scfe-conditional-field' ).each( function() {
			extractFields( fieldConditionsData( $( this ) ) );
		} );

		return fields.join( ', ' );
	}

	$( document ).ready( function() {
		var fields = watchedFields();

		updateFieldVisibility();

		if ( fields ) {
			$( document ).on( 'change keyup', fields, updateFieldVisibility );
		}

		$( document.body ).on( 'update_checkout updated_checkout', updateFieldVisibility );
	} );
} )( jQuery );
