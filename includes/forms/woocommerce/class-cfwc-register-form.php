<?php
/**
 * WooCommerce Registration Form Integration.
 *
 * Adds CAPTCHA protection to the WooCommerce My Account registration form.
 *
 * @package Captcha_For_WooCommerce
 * @since   1.0.0
 */

namespace CFWC\Forms\WooCommerce;

use CFWC\Plugin;

// Prevent direct file access.
defined( 'ABSPATH' ) || exit;

/**
 * Register_Form class.
 *
 * Handles CAPTCHA integration with the WooCommerce registration form.
 *
 * @since 1.0.0
 */
class Register_Form {

	/**
	 * Form type identifier.
	 *
	 * @var string
	 */
	const FORM_TYPE = 'wc_register';

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		if ( ! Plugin::instance()->settings()->is_form_enabled( self::FORM_TYPE ) ) {
			return;
		}

		// Render CAPTCHA on WooCommerce registration form.
		add_action( 'woocommerce_register_form', array( $this, 'render_captcha' ) );

		// Validate CAPTCHA on registration.
		add_filter( 'woocommerce_registration_errors', array( $this, 'validate_captcha' ), 10, 3 );
	}

	/**
	 * Render CAPTCHA on the WooCommerce registration form.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function render_captcha() {
		Plugin::instance()->render( self::FORM_TYPE );
	}

	/**
	 * Validate CAPTCHA during WooCommerce registration.
	 *
	 * @since 1.0.0
	 * @param \WP_Error $validation_error Validation errors.
	 * @param string    $username         The username.
	 * @param string    $email            The email.
	 * @return \WP_Error Modified validation errors.
	 */
	public function validate_captcha( $validation_error, $username, $email ) {
		$result = Plugin::instance()->verify( self::FORM_TYPE );

		if ( is_wp_error( $result ) ) {
			$validation_error->add( 'cfwc_captcha_error', $result->get_error_message() );
		}

		return $validation_error;
	}
}
