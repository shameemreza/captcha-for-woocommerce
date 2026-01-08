<?php
/**
 * WordPress Registration Form Integration.
 *
 * Adds CAPTCHA protection to the WordPress registration form.
 *
 * @package Captcha_For_WooCommerce
 * @since   1.0.0
 */

namespace CFWC\Forms\WordPress;

use CFWC\Plugin;

// Prevent direct file access.
defined( 'ABSPATH' ) || exit;

/**
 * Register_Form class.
 *
 * Handles CAPTCHA integration with the WordPress registration form.
 *
 * @since 1.0.0
 */
class Register_Form {

	/**
	 * Form type identifier.
	 *
	 * @var string
	 */
	const FORM_TYPE = 'wp_register';

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		if ( ! Plugin::instance()->settings()->is_form_enabled( self::FORM_TYPE ) ) {
			return;
		}

		// Render CAPTCHA on registration form.
		add_action( 'register_form', array( $this, 'render_captcha' ) );

		// Validate CAPTCHA on registration.
		add_filter( 'registration_errors', array( $this, 'validate_captcha' ), 10, 3 );
	}

	/**
	 * Render CAPTCHA on the registration form.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function render_captcha() {
		Plugin::instance()->render( self::FORM_TYPE );
	}

	/**
	 * Validate CAPTCHA during registration.
	 *
	 * @since 1.0.0
	 * @param \WP_Error $errors             Registration errors.
	 * @param string    $sanitized_user_login The sanitized username.
	 * @param string    $user_email           The user email.
	 * @return \WP_Error Modified errors object.
	 */
	public function validate_captcha( $errors, $sanitized_user_login, $user_email ) {
		$result = Plugin::instance()->verify( self::FORM_TYPE );

		if ( is_wp_error( $result ) ) {
			$errors->add( 'cfwc_captcha_error', $result->get_error_message() );
		}

		return $errors;
	}
}
