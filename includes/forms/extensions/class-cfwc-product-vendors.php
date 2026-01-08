<?php
/**
 * WooCommerce Product Vendors Integration.
 *
 * Adds CAPTCHA protection to the Product Vendors registration form.
 * This is a unique feature - no other CAPTCHA plugin supports this.
 *
 * @package Captcha_For_WooCommerce
 * @since   1.0.0
 */

namespace CFWC\Forms\Extensions;

use CFWC\Plugin;

// Prevent direct file access.
defined( 'ABSPATH' ) || exit;

/**
 * Product_Vendors class.
 *
 * Handles CAPTCHA integration with WooCommerce Product Vendors.
 *
 * @since 1.0.0
 */
class Product_Vendors {

	/**
	 * Form type identifier.
	 *
	 * @var string
	 */
	const FORM_TYPE = 'wcpv_registration';

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		// Check if Product Vendors is active.
		if ( ! class_exists( 'WC_Product_Vendors' ) ) {
			return;
		}

		if ( ! Plugin::instance()->settings()->is_form_enabled( self::FORM_TYPE ) ) {
			return;
		}

		// Render CAPTCHA on vendor registration form.
		add_action( 'wcpv_registration_form', array( $this, 'render_captcha' ) );

		// Validate CAPTCHA on vendor registration (AJAX).
		add_action( 'wcpv_shortcode_registration_form_validation', array( $this, 'validate_captcha' ), 10, 2 );
	}

	/**
	 * Render CAPTCHA on the vendor registration form.
	 *
	 * The Product Vendors registration form uses the wcpv_registration_form
	 * action hook to allow adding fields before the submit button.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function render_captcha() {
		echo '<div class="cfwc-wcpv-captcha">';
		Plugin::instance()->render( self::FORM_TYPE );
		echo '</div>';
	}

	/**
	 * Validate CAPTCHA during vendor registration.
	 *
	 * Product Vendors uses AJAX for form submission. Errors should be
	 * added to the $errors array which is passed by reference.
	 *
	 * @since 1.0.0
	 * @param array $errors     Reference to errors array.
	 * @param array $form_items The submitted form data.
	 * @return void
	 */
	public function validate_captcha( &$errors, $form_items ) {
		$result = Plugin::instance()->verify( self::FORM_TYPE );

		if ( is_wp_error( $result ) ) {
			$errors[] = $result->get_error_message();
		}
	}
}
