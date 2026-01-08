<?php
/**
 * WordPress Login Form Integration.
 *
 * Adds CAPTCHA protection to the WordPress login form.
 *
 * @package Captcha_For_WooCommerce
 * @since   1.0.0
 */

namespace CFWC\Forms\WordPress;

use CFWC\Plugin;

// Prevent direct file access.
defined( 'ABSPATH' ) || exit;

/**
 * Login_Form class.
 *
 * Handles CAPTCHA integration with the WordPress login form.
 *
 * @since 1.0.0
 */
class Login_Form {

	/**
	 * Form type identifier.
	 *
	 * @var string
	 */
	const FORM_TYPE = 'wp_login';

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		// Only initialize if this form is enabled.
		if ( ! Plugin::instance()->settings()->is_form_enabled( self::FORM_TYPE ) ) {
			return;
		}

		// Render CAPTCHA on login form.
		add_action( 'login_form', array( $this, 'render_captcha' ) );

		// Validate CAPTCHA on authentication.
		add_filter( 'authenticate', array( $this, 'validate_captcha' ), 30, 3 );
	}

	/**
	 * Render CAPTCHA on the login form.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function render_captcha() {
		Plugin::instance()->render( self::FORM_TYPE );
	}

	/**
	 * Validate CAPTCHA during authentication.
	 *
	 * @since 1.0.0
	 * @param \WP_User|\WP_Error|null $user     The user object or error.
	 * @param string                  $username The username.
	 * @param string                  $password The password.
	 * @return \WP_User|\WP_Error The user object or error.
	 */
	public function validate_captcha( $user, $username, $password ) {
		// Skip if already errored or empty credentials.
		if ( is_wp_error( $user ) || empty( $username ) ) {
			return $user;
		}

		// Skip for AJAX requests that aren't form submissions.
		if ( wp_doing_ajax() ) {
			return $user;
		}

		// Only validate on POST requests.
		if ( ! isset( $_SERVER['REQUEST_METHOD'] ) || 'POST' !== $_SERVER['REQUEST_METHOD'] ) {
			return $user;
		}

		$result = Plugin::instance()->verify( self::FORM_TYPE );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return $user;
	}
}
