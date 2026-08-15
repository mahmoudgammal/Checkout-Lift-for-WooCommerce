/**
 * Browser-side field dependency UX.
 *
 * Shows and hides checkout fields as their trigger fields change. Server-side
 * authority for the same rules lives in the FieldConditions module; this file
 * only improves perceived responsiveness.
 *
 * Requires `assets/js/core/wc-bridge.js` for WooCommerce checkout events.
 *
 * @package SimpleCheckoutFieldsEditor
 */

( function () {
	'use strict';

	var bridge = window.scfe && window.scfe.wc;

	if ( ! bridge ) {
		return;
	}

	var SCFEDependencies = {

		/**
		 * Field dependency configuration keyed by target field.
		 *
		 * @type {Object}
		 */
		fields: {},

		/**
		 * Read configuration and start evaluating.
		 */
		init: function () {
			if ( 'undefined' === typeof scfe_dependencies ) {
				return;
			}

			this.fields = scfe_dependencies.fields || {};

			if ( 0 === Object.keys( this.fields ).length ) {
				return;
			}

			this.bindEvents();
			this.evaluateAll();
		},

		/**
		 * Listen to trigger fields and checkout refreshes.
		 */
		bindEvents: function () {
			var self = this;

			this.getTriggerFields().forEach( function ( fieldKey ) {
				var field = self.getFieldInput( fieldKey );

				if ( ! field ) {
					return;
				}

				// Routed through the bridge: Select2-driven selects announce
				// their changes with jQuery only.
				bridge.onField( field, 'change keyup', function () {
					self.evaluateAll();
				} );
			} );

			// WooCommerce replaces checkout markup on refresh.
			bridge.on( 'updated_checkout', function () {
				self.evaluateAll();
			} );
		},

		/**
		 * Collect every field key referenced by a rule.
		 *
		 * @return {Array} Trigger field keys.
		 */
		getTriggerFields: function () {
			var triggers = [];

			Object.keys( this.fields ).forEach( function ( targetField ) {
				var rules = this.fields[ targetField ].rules || [];

				rules.forEach( function ( rule ) {
					if ( rule.field && -1 === triggers.indexOf( rule.field ) ) {
						triggers.push( rule.field );
					}
				} );
			}, this );

			return triggers;
		},

		/**
		 * Evaluate every configured dependency.
		 */
		evaluateAll: function () {
			Object.keys( this.fields ).forEach( function ( targetField ) {
				this.evaluateField( targetField );
			}, this );
		},

		/**
		 * Evaluate one target field and apply its visibility.
		 *
		 * @param {string} targetField Target field key.
		 */
		evaluateField: function ( targetField ) {
			var config = this.fields[ targetField ];
			var rules = config.rules || [];
			var action = config.action || 'show';
			var logic = config.logic || 'all';
			var effect = config.effect || 'fade';

			var results = rules.map( function ( rule ) {
				return this.evaluateRule( rule );
			}, this );

			var conditionMet = 'all' === logic
				? results.every( Boolean )
				: results.some( Boolean );

			var shouldShow = ( 'show' === action && conditionMet )
				|| ( 'hide' === action && ! conditionMet );

			this.toggleField( targetField, shouldShow, effect );
		},

		/**
		 * Evaluate a single rule against current field values.
		 *
		 * @param {Object} rule Rule configuration.
		 * @return {boolean} Whether the rule is satisfied.
		 */
		evaluateRule: function ( rule ) {
			var fieldKey = rule.field;
			var operator = rule.operator || 'is';
			var ruleValue = rule.value || '';
			var fieldValue = this.getFieldValue( fieldKey );

			switch ( operator ) {
				case 'is':
					return fieldValue === ruleValue;

				case 'is_not':
					return fieldValue !== ruleValue;

				case 'contains':
					return -1 !== fieldValue.indexOf( ruleValue );

				case 'not_contains':
					return -1 === fieldValue.indexOf( ruleValue );

				case 'starts_with':
					return 0 === fieldValue.indexOf( ruleValue );

				case 'ends_with':
					return fieldValue.slice( -ruleValue.length ) === ruleValue;

				case 'is_empty':
					return ! fieldValue || '' === fieldValue.trim();

				case 'is_not_empty':
					return !! fieldValue && '' !== fieldValue.trim();

				case 'greater_than':
					return parseFloat( fieldValue ) > parseFloat( ruleValue );

				case 'less_than':
					return parseFloat( fieldValue ) < parseFloat( ruleValue );

				case 'checked':
					return this.isFieldChecked( fieldKey );

				case 'unchecked':
					return ! this.isFieldChecked( fieldKey );

				case 'in':
					return -1 !== this.splitList( ruleValue ).indexOf( fieldValue );

				case 'not_in':
					return -1 === this.splitList( ruleValue ).indexOf( fieldValue );

				default:
					return false;
			}
		},

		/**
		 * Split a comma separated rule value into trimmed entries.
		 *
		 * @param {string} ruleValue Raw rule value.
		 * @return {Array} Trimmed entries.
		 */
		splitList: function ( ruleValue ) {
			return ruleValue.split( ',' ).map( function ( entry ) {
				return entry.trim();
			} );
		},

		/**
		 * Resolve a field input by id or name, preferring document order.
		 *
		 * @param {string} fieldKey Field key.
		 * @return {HTMLElement|null} Matching input or null.
		 */
		getFieldInput: function ( fieldKey ) {
			var byId = document.getElementById( fieldKey );
			var byName = document.getElementsByName( fieldKey )[ 0 ] || null;

			if ( ! byId ) {
				return byName;
			}

			if ( ! byName || byId === byName ) {
				return byId;
			}

			var byNameComesFirst = byId.compareDocumentPosition( byName )
				& Node.DOCUMENT_POSITION_PRECEDING;

			return byNameComesFirst ? byName : byId;
		},

		/**
		 * Read the current value of a field.
		 *
		 * @param {string} fieldKey Field key.
		 * @return {string} Field value, or an empty string when absent.
		 */
		getFieldValue: function ( fieldKey ) {
			var field = this.getFieldInput( fieldKey );

			if ( ! field ) {
				return '';
			}

			if ( 'checkbox' === field.type ) {
				return field.checked ? field.value : '';
			}

			if ( 'radio' === field.type ) {
				return this.getCheckedRadioValue( fieldKey );
			}

			return field.value || '';
		},

		/**
		 * Read the selected value of a radio group.
		 *
		 * @param {string} fieldKey Field key.
		 * @return {string} Selected value, or an empty string.
		 */
		getCheckedRadioValue: function ( fieldKey ) {
			var group = document.getElementsByName( fieldKey );

			for ( var index = 0; index < group.length; index++ ) {
				if ( group[ index ].checked ) {
					return group[ index ].value;
				}
			}

			return '';
		},

		/**
		 * Check whether a checkbox or radio field is checked.
		 *
		 * @param {string} fieldKey Field key.
		 * @return {boolean} Whether the field is checked.
		 */
		isFieldChecked: function ( fieldKey ) {
			var field = this.getFieldInput( fieldKey );

			return !! ( field && field.checked );
		},

		/**
		 * Resolve the wrapper element that carries field visibility.
		 *
		 * @param {string} fieldKey Field key.
		 * @return {HTMLElement|null} Wrapper element or null.
		 */
		getFieldWrapper: function ( fieldKey ) {
			var wrapper = document.getElementById( fieldKey + '_field' );

			if ( wrapper ) {
				return wrapper;
			}

			return document.querySelector(
				'[data-scfe-dependency="' + fieldKey.replace( /"/g, '\\"' ) + '"]'
			);
		},

		/**
		 * Show or hide a field and keep hidden inputs out of validation.
		 *
		 * @param {string}  fieldKey Field key.
		 * @param {boolean} show     Whether the field should be visible.
		 * @param {string}  effect   Transition effect name.
		 */
		toggleField: function ( fieldKey, show, effect ) {
			var wrapper = this.getFieldWrapper( fieldKey );

			if ( ! wrapper ) {
				return;
			}

			var isSlide = 'slide' === effect;

			wrapper.classList.toggle( 'scfe-dependency-slide', isSlide );
			wrapper.classList.toggle( 'scfe-dependency-fade', ! isSlide );

			wrapper.classList.toggle( 'scfe-field-visible', show );
			wrapper.classList.toggle( 'scfe-field-hidden', ! show );

			var inputs = wrapper.querySelectorAll( 'input, select, textarea' );

			for ( var index = 0; index < inputs.length; index++ ) {
				inputs[ index ].disabled = ! show;
			}
		},
	};

	bridge.ready( function () {
		SCFEDependencies.init();
	} );

	window.SCFEDependencies = SCFEDependencies;
}() );
