/**
 * Records checkout progress so an unfinished order can be recovered.
 *
 * Runs entirely off the customer's critical path: it never blocks input,
 * never delays submission, and a failure here must never stop someone from
 * completing their order.
 *
 * Two checkouts, one tracker. The classic checkout is a server-rendered form
 * whose inputs carry stable ids, so it is read from the DOM. The Blocks
 * checkout renders through React, where ids are an implementation detail and
 * the values live in a data store; reading its markup would break on any
 * change WooCommerce makes to it. The two readers share everything after the
 * read: the payload, the debounce, the throttle and the send.
 *
 * The classic path requires `assets/js/core/wc-bridge.js`, because
 * WooCommerce enhances the country and state selects with Select2, which
 * announces changes to jQuery only. The Blocks path never touches the bridge,
 * and is loaded without it so that a checkout with no jQuery on it does not
 * gain one because of this plugin.
 *
 * @package SimpleCheckoutFieldsEditor
 */

( function () {
	'use strict';

	var bridge = window.scfe && window.scfe.wc;
	var config = window.scfe_tracking;

	if ( ! config || ! config.endpoint ) {
		return;
	}

	/**
	 * Classic checkout inputs worth reporting, mapped to the payload key.
	 *
	 * Only what a recovery message needs. Nothing here is trusted by the
	 * server, which reads the cart and its total itself.
	 */
	var WATCHED = {
		billing_email: 'email',
		billing_phone: 'phone',
		billing_first_name: 'first_name',
		billing_last_name: 'last_name',
		billing_country: 'country',
	};

	var timer = null;
	var stopped = false;
	var lastPayload = '';

	/**
	 * Read the current value of one input.
	 *
	 * @param {string} id Element id.
	 * @return {string} Trimmed value, or an empty string.
	 */
	function value( id ) {
		var field = document.getElementById( id );

		return field && field.value ? String( field.value ).trim() : '';
	}

	/**
	 * Trim a value that may not be a string.
	 *
	 * @param {*} raw Candidate value.
	 * @return {string} Trimmed value, or an empty string.
	 */
	function text( raw ) {
		return raw ? String( raw ).trim() : '';
	}

	/**
	 * Return a Blocks data store, or null while it is still registering.
	 *
	 * @param {string} name Store name.
	 * @return {Object|null} Selectors, or null.
	 */
	function store( name ) {
		if ( ! window.wp || ! window.wp.data || ! window.wp.data.select ) {
			return null;
		}

		try {
			return window.wp.data.select( name ) || null;
		} catch ( e ) {
			// Missing stores vary by WooCommerce version and are expected during startup.
			return null;
		}
	}

	/**
	 * The classic checkout: a server-rendered form, read from the DOM.
	 */
	var classicSource = {
		/**
		 * Read the current form state.
		 *
		 * @return {Object} Partial payload.
		 */
		read: function () {
			var data = {};

			Object.keys( WATCHED ).forEach( function ( id ) {
				data[ WATCHED[ id ] ] = value( id );
			} );

			var payment = document.querySelector( 'input[name="payment_method"]:checked' );
			var shipping = document.querySelector( 'input[name^="shipping_method"]:checked' );

			data.payment_method = payment ? payment.value : '';
			data.shipping_method = shipping ? shipping.value : '';

			if ( payment ) {
				data.checkout_step = 'payment';
			} else {
				data.checkout_step = value( 'billing_address_1' ) ? 'shipping' : 'contact';
			}

			return data;
		},

		/**
		 * Report whether the customer has ticked the consent box.
		 *
		 * @return {boolean} Whether consent was given.
		 */
		consented: function () {
			var box = document.getElementById( config.consentField );

			return !! ( box && box.checked );
		},

		/**
		 * Watch the form for changes.
		 *
		 * @param {Function} onChange  Called when something may have changed.
		 * @param {Function} immediate Called when a change should not wait.
		 */
		watch: function ( onChange, immediate ) {
			var bind = function () {
				Object.keys( WATCHED ).forEach( function ( id ) {
					var field = document.getElementById( id );

					if ( field && ! field.dataset.scfeTracked ) {
						field.dataset.scfeTracked = '1';
						bridge.onField( field, 'change keyup', onChange );
					}
				} );

				var box = document.getElementById( config.consentField );

				if ( box && ! box.dataset.scfeTracked ) {
					box.dataset.scfeTracked = '1';
					// A fresh agreement should not wait for the debounce.
					box.addEventListener( 'change', immediate );
				}
			};

			bind();

			// Rebind after WooCommerce replaces checkout markup.
			bridge.on( 'updated_checkout', function () {
				bind();
				onChange();
			} );

			bridge.on( 'payment_method_selected', onChange );
		},
	};

	/**
	 * The Blocks checkout: read from the data stores, never from the markup.
	 */
	var blocksSource = {
		/**
		 * Read the current checkout state.
		 *
		 * @return {Object} Partial payload.
		 */
		read: function () {
			var data = {
				email: '',
				phone: '',
				first_name: '',
				last_name: '',
				country: '',
				payment_method: '',
				shipping_method: '',
				checkout_step: 'contact',
			};

			var cart = store( 'wc/store/cart' );

			if ( ! cart || ! cart.getCustomerData ) {
				return data;
			}

			var billing = ( cart.getCustomerData() || {} ).billingAddress || {};

			data.email = text( billing.email );
			data.phone = text( billing.phone );
			data.first_name = text( billing.first_name );
			data.last_name = text( billing.last_name );
			data.country = text( billing.country );

			var payment = store( 'wc/store/payment' );

			if ( payment && payment.getActivePaymentMethod ) {
				data.payment_method = text( payment.getActivePaymentMethod() );
			}

			var cartData = cart.getCartData ? cart.getCartData() || {} : {};

			data.shipping_method = this.selectedRate( cartData );
			data.checkout_step = this.step( billing, cartData, data.shipping_method );

			return data;
		},

		/**
		 * Return the chosen shipping rate, if the customer has chosen one.
		 *
		 * @param {Object} cartData Cart store data.
		 * @return {string} Rate id, or an empty string.
		 */
		selectedRate: function ( cartData ) {
			var chosen = '';

			( cartData.shippingRates || [] ).forEach( function ( shippingPackage ) {
				( shippingPackage.shipping_rates || [] ).forEach( function ( rate ) {
					if ( rate.selected && '' === chosen ) {
						chosen = text( rate.rate_id );
					}
				} );
			} );

			return chosen;
		},

		/**
		 * Report how far through the checkout the customer has reached.
		 *
		 * The Blocks checkout is one page, so progress is read from how much
		 * has been filled rather than from which panel is open. A chosen
		 * payment method is not evidence of progress here the way it is on the
		 * classic checkout, because Blocks selects one before the customer has
		 * touched anything.
		 *
		 * @param {Object} billing  Billing address.
		 * @param {Object} cartData Cart store data.
		 * @param {string} rate     Chosen shipping rate.
		 * @return {string} Step name.
		 */
		step: function ( billing, cartData, rate ) {
			if ( '' === text( billing.address_1 ) ) {
				return 'contact';
			}

			if ( '' === text( billing.city ) || '' === text( billing.country ) ) {
				return 'shipping';
			}

			if ( cartData.needsShipping && '' === rate ) {
				return 'shipping';
			}

			return 'payment';
		},

		/**
		 * Report whether the customer has ticked the consent box.
		 *
		 * The control is an additional checkout field, so its value lives in
		 * the checkout store. The store is read first and the rendered input
		 * second, because the selector arrived later than the field API and is
		 * missing on the earliest versions that can render the field at all.
		 *
		 * The field is matched on the end of its name. WooCommerce prefixes
		 * both the name and the id with the location it chose to render the
		 * field in, and rewrites the id further, so neither is ours to predict.
		 *
		 * @return {boolean} Whether consent was given.
		 */
		consented: function () {
			var checkout = store( 'wc/store/checkout' );

			if ( checkout && checkout.getAdditionalFields ) {
				var fields = checkout.getAdditionalFields() || {};

				if ( Object.prototype.hasOwnProperty.call( fields, config.blockConsentField ) ) {
					return !! fields[ config.blockConsentField ];
				}
			}

			var box = document.querySelector( 'input[name$="' + config.blockConsentField + '"]' );

			return !! ( box && box.checked );
		},

		/**
		 * Watch the data stores for changes.
		 *
		 * @param {Function} onChange Called when something may have changed.
		 */
		watch: function ( onChange ) {
			var self = this;
			var attempts = 0;

			var subscribe = function () {
				if ( ! window.wp || ! window.wp.data || ! window.wp.data.subscribe ) {
					return false;
				}

				if ( ! store( 'wc/store/cart' ) ) {
					return false;
				}

				var last = '';

				// Compare payloads so unrelated store writes do not restart the debounce.
				window.wp.data.subscribe( function () {
					var current = JSON.stringify( self.read() ) + String( self.consented() );

					if ( current !== last ) {
						last = current;
						onChange();
					}
				} );

				return true;
			};

			if ( subscribe() ) {
				return;
			}

			// Block stores may register later, so retry for a bounded window.
			var poll = window.setInterval( function () {
				attempts += 1;

				if ( subscribe() || attempts > 40 ) {
					window.clearInterval( poll );
				}
			}, 250 );
		},
	};

	// Server-rendered markup identifies the checkout type without racing block-store registration.
	var source = document.querySelector( '.wp-block-woocommerce-checkout' ) ? blocksSource : classicSource;

	// Classic checkout needs the bridge for Select2 changes and refreshed markup.
	if ( classicSource === source && ! bridge ) {
		return;
	}

	/**
	 * Report whether the customer has agreed to being contacted.
	 *
	 * With no control on the page the merchant has taken responsibility for
	 * the legal basis, and the server decides what that permits.
	 *
	 * @return {boolean} Whether consent was given.
	 */
	function consented() {
		if ( ! config.consentRequired ) {
			return false;
		}

		return source.consented();
	}

	/**
	 * Build the payload from the current checkout state.
	 *
	 * @return {Object} Payload.
	 */
	function payload() {
		var data = source.read();

		data.consent = consented();

		return data;
	}

	/**
	 * Send the current state, if there is anything worth sending.
	 *
	 * @param {boolean} leaving Whether the page is going away.
	 */
	function send( leaving ) {
		if ( stopped ) {
			return;
		}

		var data = payload();

		// The server rejects captures without a recoverable contact.
		if ( ! data.email && ! data.phone ) {
			return;
		}

		var body = JSON.stringify( data );

		// Avoid resending state the server already accepted, including repeated page hides.
		if ( body === lastPayload ) {
			return;
		}

		lastPayload = body;

		fetch( config.endpoint, {
			method: 'POST',
			headers: {
				'Content-Type': 'application/json',
				'X-WP-Nonce': config.nonce,
			},
			credentials: 'same-origin',
			// Capture can outlive the page when the customer leaves.
			keepalive: !! leaving,
			body: body,
		} )
			.then( function ( response ) {
				// A rejected nonce will not start working, so stop asking.
				if ( 403 === response.status ) {
					stopped = true;

					return;
				}

				// Failed writes must remain eligible for the next capture.
				if ( ! response.ok ) {
					lastPayload = '';
				}
			} )
			.catch( function () {
				// Capture failures must not interrupt checkout.
				lastPayload = '';
			} );
	}

	/**
	 * Queue a send once the customer pauses.
	 */
	function schedule() {
		window.clearTimeout( timer );
		timer = window.setTimeout( function () {
			send( false );
		}, config.debounce );
	}

	/**
	 * Send at once, without waiting for the debounce.
	 */
	function now() {
		send( false );
	}

	/**
	 * Start once the document is ready.
	 */
	function start() {
		source.watch( schedule, now );

		// The customer leaving is the event this whole feature exists for.
		document.addEventListener( 'visibilitychange', function () {
			if ( 'hidden' === document.visibilityState ) {
				window.clearTimeout( timer );
				send( true );
			}
		} );
	}

	if ( bridge ) {
		bridge.ready( start );
	} else if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', start );
	} else {
		start();
	}
}() );
