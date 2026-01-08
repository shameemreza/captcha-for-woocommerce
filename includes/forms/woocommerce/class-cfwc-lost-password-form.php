<?php
/**
 * WooCommerce Lost Password Form Integration.
 *
 * Adds CAPTCHA protection to the WooCommerce My Account lost password form.
 *
 * @package Captcha_For_WooCommerce
 * @since   1.0.0
 */

namespace CFWC\Forms\WooCommerce;

use CFWC\Plugin;

// Prevent direct file access.
defined( 'ABSPATH' ) || exit;

/**
 * Lost_Password_Form class.
 *
 * Handles CAPTCHA integration with the WooCommerce lost password form.
 *
 * @since 1.0.0
 */
class Lost_Password_Form {

	/**
	 * Form type identifier.
	 *
	 * @var string
	 */
	const FORM_TYPE = 'wc_lost_password';

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		if ( ! Plugin::instance()->settings()->is_form_enabled( self::FORM_TYPE ) ) {
			return;
		}

		// Render CAPTCHA on WooCommerce lost password form.
		add_action( 'woocommerce_lostpassword_form', array( $this, 'render_captcha' ) );

		// Validate CAPTCHA on lost password request.
		add_action( 'lostpassword_post', array( $this, 'validate_captcha' ) );
	}

	/**
	 * Render CAPTCHA on the WooCommerce lost password form.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function render_captcha() {
		Plugin::instance()->render( self::FORM_TYPE );
	}

	/**
	 * Validate CAPTCHA during lost password request.
	 *
	 * @since 1.0.0
	 * @param \WP_Error $errors Lost password errors.
	 * @return void
	 */
	public function validate_captcha( $errors ) {
		// Check if this is a WooCommerce context.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( ! isset( $_POST['wc_reset_password'] ) ) {
			return;
		}

		$result = Plugin::instance()->verify( self::FORM_TYPE );

		if ( is_wp_error( $result ) ) {
			$errors->add( 'cfwc_captcha_error', $result->get_error_message() );
		}
	}
}
