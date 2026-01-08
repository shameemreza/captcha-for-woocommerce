/**
 * Captcha for WooCommerce - Admin Script
 *
 * Handles admin settings functionality.
 *
 * @package Captcha_For_WooCommerce
 * @since   1.0.0
 */

( function( $ ) {
	'use strict';

	var CFWAdmin = {
		/**
		 * Initialize admin functionality.
		 */
		init: function() {
			this.bindEvents();
			this.handleProviderChange();
		},

		/**
		 * Bind event listeners.
		 */
		bindEvents: function() {
			var self = this;

			// Provider change handler.
			$( '#cfwc_provider' ).on( 'change', function() {
				self.handleProviderChange();
			} );

			// Test connection button.
			$( '#cfwc-test-connection' ).on( 'click', function( e ) {
				e.preventDefault();
				self.testConnection();
			} );

			// Score threshold visibility (only for reCAPTCHA v3).
			this.toggleScoreThreshold();
		},

		/**
		 * Handle provider selection change.
		 */
		handleProviderChange: function() {
			var provider = $( '#cfwc_provider' ).val();
			var $siteKeyRow = $( '#cfwc_site_key' ).closest( 'tr' );
			var $secretKeyRow = $( '#cfwc_secret_key' ).closest( 'tr' );
			var $scoreRow = $( '#cfwc_score_threshold' ).closest( 'tr' );

			// Honeypot doesn't need API keys.
			if ( provider === 'honeypot' ) {
				$siteKeyRow.hide();
				$secretKeyRow.hide();
				$scoreRow.hide();
			} else {
				$siteKeyRow.show();
				$secretKeyRow.show();
			}

			// Score threshold only for reCAPTCHA v3.
			if ( provider === 'recaptcha_v3' ) {
				$scoreRow.show();
			} else {
				$scoreRow.hide();
			}

			// Update API key link.
			this.updateApiKeyLink( provider );
		},

		/**
		 * Update the API key link based on provider.
		 *
		 * @param {string} provider Provider ID.
		 */
		updateApiKeyLink: function( provider ) {
			var $link = $( '.cfwc-api-link a' );
			var urls = {
				turnstile: 'https://dash.cloudflare.com/?to=/:account/turnstile',
				recaptcha_v2: 'https://www.google.com/recaptcha/admin/create',
				recaptcha_v3: 'https://www.google.com/recaptcha/admin/create',
				hcaptcha: 'https://dashboard.hcaptcha.com/sites/new'
			};

			if ( urls[ provider ] ) {
				$link.attr( 'href', urls[ provider ] ).parent().show();
			} else {
				$link.parent().hide();
			}
		},

		/**
		 * Toggle score threshold visibility.
		 */
		toggleScoreThreshold: function() {
			var provider = $( '#cfwc_provider' ).val();
			var $scoreRow = $( '#cfwc_score_threshold' ).closest( 'tr' );

			if ( provider === 'recaptcha_v3' ) {
				$scoreRow.show();
			} else {
				$scoreRow.hide();
			}
		},

		/**
		 * Test API connection.
		 */
		testConnection: function() {
			var self = this;
			var $button = $( '#cfwc-test-connection' );
			var $result = $( '.cfwc-test-result' );
			var provider = $( '#cfwc_provider' ).val();
			var siteKey = $( '#cfwc_site_key' ).val();
			var secretKey = $( '#cfwc_secret_key' ).val();

			if ( ! provider ) {
				$result.removeClass( 'success loading' )
					   .addClass( 'error' )
					   .text( cfwAdmin.i18n.failed );
				return;
			}

			// Show loading state.
			$button.prop( 'disabled', true );
			$result.removeClass( 'success error' )
				   .addClass( 'loading' )
				   .text( cfwAdmin.i18n.testing );

			// Make AJAX request.
			$.ajax( {
				url: cfwAdmin.ajaxUrl,
				type: 'POST',
				data: {
					action: 'cfwc_test_connection',
					nonce: cfwAdmin.nonce,
					provider: provider,
					site_key: siteKey,
					secret_key: secretKey
				},
				success: function( response ) {
					$button.prop( 'disabled', false );

					if ( response.success ) {
						$result.removeClass( 'loading error' )
							   .addClass( 'success' )
							   .text( cfwAdmin.i18n.success );
					} else {
						$result.removeClass( 'loading success' )
							   .addClass( 'error' )
							   .text( response.data.message || cfwAdmin.i18n.failed );
					}
				},
				error: function() {
					$button.prop( 'disabled', false );
					$result.removeClass( 'loading success' )
						   .addClass( 'error' )
						   .text( cfwAdmin.i18n.failed );
				}
			} );
		}
	};

	// Initialize on document ready.
	$( document ).ready( function() {
		CFWAdmin.init();
	} );

} )( jQuery );
