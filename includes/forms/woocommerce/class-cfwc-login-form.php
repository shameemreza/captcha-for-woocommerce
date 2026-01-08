<?php
/**
 * WooCommerce Login Form Integration.
 *
 * Adds CAPTCHA protection to the WooCommerce My Account login form.
 *
 * @package Captcha_For_WooCommerce
 * @since   1.0.0
 */

namespace CFWC\Forms\WooCommerce;

use CFWC\Plugin;

// Prevent direct file access.
defined( 'ABSPATH' ) || exit;

/**
 * Login_Form class.
 *
 * Handles CAPTCHA integration with the WooCommerce login form.
 *
 * @since 1.0.0
 */
class Login_Form {

	/**
	 * Form type identifier.
	 *
	 * @var string
	 */
	const FORM_TYPE = 'wc_login';

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		if ( ! Plugin::instance()->settings()->is_form_enabled( self::FORM_TYPE ) ) {
			return;
		}

		// Render CAPTCHA on WooCommerce login form.
		add_action( 'woocommerce_login_form', array( $this, 'render_captcha' ) );

		// Validate CAPTCHA on login.
		add_filter( 'woocommerce_process_login_errors', array( $this, 'validate_captcha' ), 10, 3 );
	}

	/**
	 * Render CAPTCHA on the WooCommerce login form.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function render_captcha() {
		Plugin::instance()->render( self::FORM_TYPE );
	}

	/**
	 * Validate CAPTCHA during WooCommerce login.
	 *
	 * @since 1.0.0
	 * @param \WP_Error $validation_error Validation errors.
	 * @param string    $username         The username.
	 * @param string    $password         The password.
	 * @return \WP_Error Modified validation errors.
	 */
	public function validate_captcha( $validation_error, $username, $password ) {
		$result = Plugin::instance()->verify( self::FORM_TYPE );

		if ( is_wp_error( $result ) ) {
			$validation_error->add( 'cfwc_captcha_error', $result->get_error_message() );
		}

		return $validation_error;
	}
}
