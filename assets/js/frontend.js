/**
 * Captcha for WooCommerce - Frontend Script
 *
 * Lightweight handler for CAPTCHA widget initialization and form integration.
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

			// Re-initialize on AJAX content updates (WooCommerce).
			document.body.addEventListener( 'updated_checkout', function() {
				self.initProvider();
			} );

			// Re-initialize when fragments are refreshed.
			document.body.addEventListener( 'wc_fragments_refreshed', function() {
				self.initProvider();
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
