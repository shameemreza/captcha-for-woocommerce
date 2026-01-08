/**
 * Captcha for WooCommerce - Frontend Script
 *
 * Lightweight handler for CAPTCHA widget initialization and form integration.
 * Includes advanced honeypot with JS-injection technique.
 * Total size target: < 5KB minified + gzipped.
 *
 * @package Captcha_For_WooCommerce
 * @since   1.0.0
 */

( function() {
	'use strict';

	// Exit if settings not available.
	if ( typeof cfwSettings === 'undefined' ) {
		return;
	}

	var CFW = {
		settings: cfwSettings,
		widgets: {},

		/**
		 * Initialize the CAPTCHA handler.
		 */
		init: function() {
			this.initProvider();
			this.initHoneypot();
			this.bindEvents();
		},

		/**
		 * Initialize the CAPTCHA provider.
		 */
		initProvider: function() {
			var self = this;
			var provider = this.settings.provider;

			switch ( provider ) {
				case 'turnstile':
					this.waitForProvider( 'turnstile', function() {
						self.initTurnstile();
					} );
					break;

				case 'recaptcha_v2':
					this.waitForProvider( 'grecaptcha', function() {
						self.initRecaptchaV2();
					} );
					break;

				case 'recaptcha_v3':
					this.waitForProvider( 'grecaptcha', function() {
						self.initRecaptchaV3();
					} );
					break;

				case 'hcaptcha':
					this.waitForProvider( 'hcaptcha', function() {
						self.initHcaptcha();
					} );
					break;

				case 'honeypot':
					// Honeypot is initialized separately via initHoneypot().
					break;
			}
		},

		/**
		 * Initialize honeypot protection.
		 *
		 * Injects honeypot fields via JavaScript. Bots that don't execute JS
		 * will never see these fields and fail server-side validation.
		 */
		initHoneypot: function() {
			// Only initialize if honeypot is enabled.
			if ( ! this.settings.honeypot ) {
				return;
			}

			var self = this;
			var hp = this.settings.honeypot;

			// Form selectors to target.
			var formSelectors = [
				// WooCommerce forms.
				'form.woocommerce-checkout',
				'form.woocommerce-form-login',
				'form.woocommerce-form-register',
				'form.woocommerce-ResetPassword',
				'form#order_review',
				'form.woocommerce-EditAccountForm',
				// WordPress forms.
				'form#loginform',
				'form#registerform',
				'form#lostpasswordform',
				'form#commentform',
				'form.comment-form',
				// Product Vendors.
				'form#wcpv-vendor-registration',
				// Generic class for custom integrations.
				'form.cfwc-protected'
			];

			// Find all forms and inject honeypot.
			var forms = document.querySelectorAll( formSelectors.join( ', ' ) );
			forms.forEach( function( form ) {
				self.injectHoneypotFields( form, hp );
			} );

			// Also look for placeholder elements.
			var placeholders = document.querySelectorAll( '.cfwc-hp-init' );
			placeholders.forEach( function( placeholder ) {
				var form = placeholder.closest( 'form' );
				if ( form && ! form.querySelector( '.cfwc-hp-injected' ) ) {
					self.injectHoneypotFields( form, hp );
				}
			} );
		},

		/**
		 * Inject honeypot fields into a form.
		 *
		 * @param {Element} form The form element.
		 * @param {Object}  hp   Honeypot configuration.
		 */
		injectHoneypotFields: function( form, hp ) {
			// Skip if already injected.
			if ( form.querySelector( '.cfwc-hp-injected' ) ) {
				return;
			}

			// Build the honeypot HTML.
			// 1. Visible trap field (alt_s) - bots will fill this.
			// 2. Hidden field with unique name - proves JS executed.
			// 3. Hidden verification fields.
			var honeypotHTML = '' +
				'<div class="cfwc-hp-injected" aria-hidden="true">' +
					// Visible trap (positioned off-screen via CSS).
					'<div class="cfwc-hp-trap">' +
						'<label for="cfwc_alt_s">Alternative:</label>' +
						'<input type="text" id="cfwc_alt_s" name="alt_s" autocomplete="new-password" tabindex="-1">' +
					'</div>' +
					// Hidden honeypot with value (JS-only field).
					'<span class="cfwc-hp-hidden">' +
						'<input type="text" name="' + hp.fieldName + '" value="' + hp.timestamp + '" tabindex="-1">' +
					'</span>' +
				'</div>' +
				// Verification fields (hidden inputs).
				'<input type="hidden" name="cfwc_hp_nonce" value="' + hp.nonce + '">' +
				'<input type="hidden" name="cfwc_hp_time" value="' + hp.timestamp + '">' +
				'<input type="hidden" name="cfwc_hp_challenge" value="' + hp.challenge + '">' +
				'<input type="hidden" name="cfwc_hp_js" value="">';

			// Inject into form.
			form.insertAdjacentHTML( 'beforeend', honeypotHTML );

			// Calculate JS challenge response (proves real browser).
			var jsField = form.querySelector( 'input[name="cfwc_hp_js"]' );
			if ( jsField && hp.challengeA && hp.challengeB && hp.challengeC ) {
				var result = ( hp.challengeA * hp.challengeB + hp.challengeC ).toString( 36 );
				jsField.value = result;
			}
		},

		/**
		 * Wait for provider script to load.
		 *
		 * @param {string}   globalName Provider's global object name.
		 * @param {Function} callback   Callback when ready.
		 */
		waitForProvider: function( globalName, callback ) {
			var attempts = 0;
			var maxAttempts = 50;

			var check = setInterval( function() {
				attempts++;

				if ( window[ globalName ] ) {
					clearInterval( check );
					callback();
				} else if ( attempts >= maxAttempts ) {
					clearInterval( check );
					console.warn( 'Captcha for WooCommerce: Provider script failed to load.' );
				}
			}, 100 );
		},

		/**
		 * Initialize Cloudflare Turnstile widgets.
		 */
		initTurnstile: function() {
			var self = this;
			var containers = document.querySelectorAll( '.cf-turnstile:not([data-cfwc-init])' );

			containers.forEach( function( container ) {
				container.setAttribute( 'data-cfwc-init', '1' );

				var widgetId = window.turnstile.render( container, {
					sitekey: self.settings.siteKey,
					theme: self.getTheme(),
					callback: function( token ) {
						self.onSuccess( container, token );
					},
					'expired-callback': function() {
						self.onExpired( container );
					},
					'error-callback': function() {
						self.onError( container );
					}
				} );

				self.widgets[ container.id ] = widgetId;
			} );
		},

		/**
		 * Initialize Google reCAPTCHA v2 widgets.
		 */
		initRecaptchaV2: function() {
			var self = this;
			var containers = document.querySelectorAll( '.g-recaptcha:not([data-cfwc-init])' );

			containers.forEach( function( container ) {
				container.setAttribute( 'data-cfwc-init', '1' );

				var widgetId = window.grecaptcha.render( container, {
					sitekey: self.settings.siteKey,
					theme: self.getTheme() === 'auto' ? 'light' : self.getTheme(),
					callback: function( token ) {
						self.onSuccess( container, token );
					},
					'expired-callback': function() {
						self.onExpired( container );
					},
					'error-callback': function() {
						self.onError( container );
					}
				} );

				self.widgets[ container.id ] = widgetId;
			} );
		},

		/**
		 * Initialize Google reCAPTCHA v3.
		 */
		initRecaptchaV3: function() {
			var self = this;
			var containers = document.querySelectorAll( '.cfwc-recaptcha-v3:not([data-cfwc-init])' );

			containers.forEach( function( container ) {
				container.setAttribute( 'data-cfwc-init', '1' );

				var input = container.querySelector( 'input[type="hidden"]' );
				var action = container.dataset.action || 'submit';

				// Get token on page load.
				self.getRecaptchaV3Token( action, function( token ) {
					if ( input ) {
						input.value = token;
					}
				} );

				// Refresh token periodically (tokens expire after 2 minutes).
				setInterval( function() {
					self.getRecaptchaV3Token( action, function( token ) {
						if ( input ) {
							input.value = token;
						}
					} );
				}, 90000 ); // Refresh every 90 seconds.
			} );
		},

		/**
		 * Get reCAPTCHA v3 token.
		 *
		 * @param {string}   action   Action name.
		 * @param {Function} callback Callback with token.
		 */
		getRecaptchaV3Token: function( action, callback ) {
			var self = this;

			window.grecaptcha.ready( function() {
				window.grecaptcha.execute( self.settings.siteKey, { action: action } )
					.then( function( token ) {
						callback( token );
					} )
					.catch( function() {
						console.warn( 'Captcha for WooCommerce: Failed to get reCAPTCHA v3 token.' );
					} );
			} );
		},

		/**
		 * Initialize hCaptcha widgets.
		 */
		initHcaptcha: function() {
			var self = this;
			var containers = document.querySelectorAll( '.h-captcha:not([data-cfwc-init])' );

			containers.forEach( function( container ) {
				container.setAttribute( 'data-cfwc-init', '1' );

				var widgetId = window.hcaptcha.render( container, {
					sitekey: self.settings.siteKey,
					theme: self.getTheme() === 'auto' ? 'light' : self.getTheme(),
					callback: function( token ) {
						self.onSuccess( container, token );
					},
					'expired-callback': function() {
						self.onExpired( container );
					},
					'error-callback': function() {
						self.onError( container );
					}
				} );

				self.widgets[ container.id ] = widgetId;
			} );
		},

		/**
		 * Get theme setting, handling 'auto' value.
		 *
		 * @return {string} Theme value.
		 */
		getTheme: function() {
			var theme = this.settings.theme;

			if ( theme === 'auto' ) {
				// Check for dark mode preference.
				if ( window.matchMedia && window.matchMedia( '(prefers-color-scheme: dark)' ).matches ) {
					return 'dark';
				}
				return 'light';
			}

			return theme;
		},

		/**
		 * Handle successful CAPTCHA completion.
		 *
		 * @param {Element} container Widget container.
		 * @param {string}  token     Response token.
		 */
		onSuccess: function( container, token ) {
			// Remove any error messages.
			var errorEl = container.parentElement.querySelector( '.cfwc-error' );
			if ( errorEl ) {
				errorEl.remove();
			}

			// Trigger custom event for integration.
			var event = new CustomEvent( 'cfw:captcha:success', {
				detail: { token: token, container: container }
			} );
			document.dispatchEvent( event );
		},

		/**
		 * Handle CAPTCHA expiration.
		 *
		 * @param {Element} container Widget container.
		 */
		onExpired: function( container ) {
			this.showError( container, this.settings.i18n.expired );

			var event = new CustomEvent( 'cfw:captcha:expired', {
				detail: { container: container }
			} );
			document.dispatchEvent( event );
		},

		/**
		 * Handle CAPTCHA error.
		 *
		 * @param {Element} container Widget container.
		 */
		onError: function( container ) {
			this.showError( container, this.settings.i18n.failed );

			var event = new CustomEvent( 'cfw:captcha:error', {
				detail: { container: container }
			} );
			document.dispatchEvent( event );
		},

		/**
		 * Show error message below widget.
		 *
		 * @param {Element} container Widget container.
		 * @param {string}  message   Error message.
		 */
		showError: function( container, message ) {
			// Remove existing error.
			var existingError = container.parentElement.querySelector( '.cfwc-error' );
			if ( existingError ) {
				existingError.remove();
			}

			// Create error element.
			var errorEl = document.createElement( 'div' );
			errorEl.className = 'cfwc-error';
			errorEl.setAttribute( 'role', 'alert' );
			errorEl.setAttribute( 'aria-live', 'polite' );
			errorEl.textContent = message;

			container.parentElement.appendChild( errorEl );
		},

		/**
		 * Bind event listeners.
		 */
		bindEvents: function() {
			var self = this;

			// Re-initialize on AJAX content updates (WooCommerce Classic Checkout).
			document.body.addEventListener( 'updated_checkout', function() {
				self.initProvider();
				self.initHoneypot();
			} );

			// Re-initialize when fragments are refreshed.
			document.body.addEventListener( 'wc_fragments_refreshed', function() {
				self.initProvider();
				self.initHoneypot();
			} );

			// Reset on checkout error.
			jQuery( document.body ).on( 'checkout_error', function() {
				self.resetAllWidgets();
			} );

			// Initialize Block Checkout integration.
			this.initBlockCheckout();
		},

		/**
		 * Initialize WooCommerce Block Checkout integration.
		 *
		 * Uses wp.data to communicate with the Store API.
		 * Token is sent via extensions data.
		 */
		initBlockCheckout: function() {
			var self = this;

			// Check if we're in Block Checkout context.
			// The isBlockCheckout flag is set by the PHP Checkout_Integration class.
			if ( ! this.settings.isBlockCheckout ) {
				return;
			}

			// Check if wp.data is available (should be loaded as dependency).
			if ( typeof wp === 'undefined' || ! wp.data ) {
				console.warn( 'Captcha for WooCommerce: wp.data not available for Block Checkout.' );
				return;
			}

			// Subscribe to WooCommerce store changes.
			var unsubscribe = wp.data.subscribe( function() {
				var container = document.getElementById( 'cfwc-block-checkout-captcha' );

				// Skip if container not found or already initialized.
				if ( ! container || container.getAttribute( 'data-cfwc-block-init' ) === '1' ) {
					return;
				}

				// Skip if widget already rendered.
				if ( container.innerHTML.trim() !== '' ) {
					return;
				}

				container.setAttribute( 'data-cfwc-block-init', '1' );
				self.renderBlockCheckoutWidget( container );

				// Unsubscribe after initialization.
				if ( typeof unsubscribe === 'function' ) {
					unsubscribe();
				}
			} );
		},

		/**
		 * Render CAPTCHA widget for Block Checkout.
		 *
		 * @param {Element} container The widget container.
		 */
		renderBlockCheckoutWidget: function( container ) {
			var self = this;
			var provider = this.settings.provider;
			var siteKey = this.settings.siteKey;

			// Callback to send token to Store API.
			var onTokenReceived = function( token ) {
				if ( typeof wp !== 'undefined' && wp.data ) {
					wp.data.dispatch( 'wc/store/checkout' ).__internalSetExtensionData(
						'captcha-for-woocommerce',
						{ token: token }
					);
				}
			};

			switch ( provider ) {
				case 'turnstile':
					if ( window.turnstile ) {
						window.turnstile.render( container, {
							sitekey: siteKey,
							theme: self.getTheme(),
							callback: onTokenReceived
						} );
					}
					break;

				case 'recaptcha_v2':
					if ( window.grecaptcha ) {
						window.grecaptcha.render( container, {
							sitekey: siteKey,
							theme: self.getTheme() === 'auto' ? 'light' : self.getTheme(),
							callback: onTokenReceived
						} );
					}
					break;

				case 'recaptcha_v3':
					if ( window.grecaptcha ) {
						window.grecaptcha.ready( function() {
							window.grecaptcha.execute( siteKey, { action: 'checkout' } )
								.then( onTokenReceived );
						} );

						// Refresh token periodically.
						setInterval( function() {
							window.grecaptcha.ready( function() {
								window.grecaptcha.execute( siteKey, { action: 'checkout' } )
									.then( onTokenReceived );
							} );
						}, 90000 );
					}
					break;

				case 'hcaptcha':
					if ( window.hcaptcha ) {
						window.hcaptcha.render( container, {
							sitekey: siteKey,
							theme: self.getTheme() === 'auto' ? 'light' : self.getTheme(),
							callback: onTokenReceived
						} );
					}
					break;

				case 'honeypot':
					// Honeypot doesn't need client-side rendering for Block Checkout.
					// It's handled server-side via Store API extension.
					onTokenReceived( 'honeypot' );
					break;
			}
		},

		/**
		 * Reset all widgets on the page.
		 */
		resetAllWidgets: function() {
			var self = this;
			Object.keys( this.widgets ).forEach( function( containerId ) {
				self.reset( containerId );
			} );
		},

		/**
		 * Reset a specific widget.
		 *
		 * @param {string} containerId Widget container ID.
		 */
		reset: function( containerId ) {
			var widgetId = this.widgets[ containerId ];
			var provider = this.settings.provider;

			if ( typeof widgetId === 'undefined' ) {
				return;
			}

			switch ( provider ) {
				case 'turnstile':
					if ( window.turnstile ) {
						window.turnstile.reset( widgetId );
					}
					break;

				case 'recaptcha_v2':
					if ( window.grecaptcha ) {
						window.grecaptcha.reset( widgetId );
					}
					break;

				case 'hcaptcha':
					if ( window.hcaptcha ) {
						window.hcaptcha.reset( widgetId );
					}
					break;
			}
		}
	};

	// Global callback functions for provider scripts.
	window.cfwRecaptchaCallback = function( token ) {
		document.dispatchEvent( new CustomEvent( 'cfw:captcha:success', { detail: { token: token } } ) );
	};

	window.cfwRecaptchaExpired = function() {
		document.dispatchEvent( new CustomEvent( 'cfw:captcha:expired', { detail: {} } ) );
	};

	window.cfwHcaptchaCallback = function( token ) {
		document.dispatchEvent( new CustomEvent( 'cfw:captcha:success', { detail: { token: token } } ) );
	};

	window.cfwHcaptchaExpired = function() {
		document.dispatchEvent( new CustomEvent( 'cfw:captcha:expired', { detail: {} } ) );
	};

	// Initialize on DOM ready.
	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', function() {
			CFW.init();
		} );
	} else {
		CFW.init();
	}

	// Expose globally for extensions.
	window.CFW = CFW;

} )();
