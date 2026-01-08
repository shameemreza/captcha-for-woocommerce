/**
 * Captcha for WooCommerce - Admin Notices Handler
 *
 * Handles dismissal of admin notices via AJAX.
 *
 * @package Captcha_For_WooCommerce
 * @since   1.0.0
 */

( function( $ ) {
	'use strict';

	$( document ).ready( function() {
		// Handle notice dismissal.
		$( '.cfwc-welcome-notice, .cfwc-config-notice' ).on( 'click', '.notice-dismiss', function() {
			var $notice = $( this ).closest( '.notice' );
			var noticeType = $notice.data( 'notice' );

			// Send AJAX request to dismiss notice.
			$.post( ajaxurl, {
				action: 'cfwc_dismiss_welcome_notice',
				notice: noticeType,
				nonce: cfwcNotices.nonce
			} );
		} );
	} );

} )( jQuery );
