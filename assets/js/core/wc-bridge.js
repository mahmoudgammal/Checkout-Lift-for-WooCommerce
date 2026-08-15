/**
 * WooCommerce classic checkout event bridge.
 *
 * WooCommerce's classic checkout publishes its lifecycle through jQuery custom
 * events on `document.body`. Those are dispatched by `jQuery.trigger()` and are
 * therefore invisible to `addEventListener`. This bridge is the single place in
 * the plugin allowed to touch jQuery: it republishes WooCommerce events as real
 * DOM events so every other module can be written in plain JavaScript.
 *
 * WooCommerce already loads jQuery on the classic checkout, so this file adds a
 * dependency that is present regardless. It is not loaded on block checkout.
 *
 * @package SimpleCheckoutFieldsEditor
 */

( function () {
	'use strict';

	/**
	 * WooCommerce events republished as DOM events.
	 *
	 * @type {string[]}
	 */
	var WC_EVENTS = [
		'updated_checkout',
		'update_checkout',
		'payment_method_selected',
		'checkout_error',
		'country_to_state_changed',
		'updated_shipping_method',
	];

	var bus = new EventTarget();
	var $ = window.jQuery || null;

	if ( $ ) {
		WC_EVENTS.forEach( function ( eventName ) {
			$( document.body ).on( eventName, function () {
				// `arguments` carries the extra payload jQuery handlers receive.
				var payload = Array.prototype.slice.call( arguments, 1 );

				bus.dispatchEvent(
					new CustomEvent( eventName, { detail: payload } )
				);
			} );
		} );
	}

	/**
	 * Subscribe to a WooCommerce checkout event.
	 *
	 * @param {string}   eventName Event name.
	 * @param {Function} handler   Handler invoked with the CustomEvent.
	 */
	function on( eventName, handler ) {
		bus.addEventListener( eventName, handler );
	}

	/**
	 * Unsubscribe from a WooCommerce checkout event.
	 *
	 * @param {string}   eventName Event name.
	 * @param {Function} handler   Handler passed to `on`.
	 */
	function off( eventName, handler ) {
		bus.removeEventListener( eventName, handler );
	}

	/**
	 * Ask WooCommerce to refresh the order review.
	 *
	 * No-op when jQuery is unavailable, which is the case on block checkout.
	 *
	 * @return {boolean} Whether the refresh was requested.
	 */
	function refresh() {
		if ( ! $ ) {
			return false;
		}

		$( document.body ).trigger( 'update_checkout' );

		return true;
	}

	/**
	 * Emit a plugin event on both transports.
	 *
	 * Plugin events such as `scfe_step_changed` are a documented extension
	 * point, so third-party jQuery listeners must keep working while new code
	 * listens natively.
	 *
	 * @param {string}  eventName Event name.
	 * @param {Array}   payload   Extra arguments for jQuery listeners.
	 */
	function emit( eventName, payload ) {
		var args = payload || [];

		if ( $ ) {
			$( document.body ).trigger( eventName, args );
		}

		bus.dispatchEvent( new CustomEvent( eventName, { detail: args } ) );
	}

	/**
	 * Listen for changes on a form field, whatever fires them.
	 *
	 * WooCommerce enhances country and state selects with Select2, which
	 * updates the underlying element and announces it with
	 * `jQuery.trigger( 'change' )`. That never reaches `addEventListener`,
	 * so a native-only listener silently misses every change made through
	 * the visible dropdown.
	 *
	 * jQuery handlers receive both jQuery-triggered and real DOM events, so
	 * binding through jQuery when it exists catches everything exactly once.
	 * Without jQuery, native listeners are already sufficient.
	 *
	 * @param {HTMLElement} field    Field to observe.
	 * @param {string}      events   Space separated event names.
	 * @param {Function}    handler  Handler to run.
	 */
	function onField( field, events, handler ) {
		if ( $ ) {
			$( field ).on( events, handler );
			return;
		}

		events.split( ' ' ).forEach( function ( eventName ) {
			field.addEventListener( eventName, handler );
		} );
	}

	/**
	 * Run a callback once the DOM is parsed.
	 *
	 * @param {Function} callback Callback to run.
	 */
	function ready( callback ) {
		if ( 'loading' !== document.readyState ) {
			callback();
			return;
		}

		document.addEventListener( 'DOMContentLoaded', callback, { once: true } );
	}

	window.scfe = window.scfe || {};
	window.scfe.wc = {
		on: on,
		off: off,
		emit: emit,
		onField: onField,
		ready: ready,
		refresh: refresh,
		hasJQuery: null !== $,
	};
}() );
