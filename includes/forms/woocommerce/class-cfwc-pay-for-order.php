<?php
/**
 * WooCommerce Pay for Order Integration.
 *
 * Adds CAPTCHA protection to the WooCommerce pay for order form.
 *
 * @package Captcha_For_WooCommerce
 * @since   1.0.0
 */

namespace CFWC\Forms\WooCommerce;

use CFWC\Plugin;

// Prevent direct file access.
defined( 'ABSPATH' ) || exit;

/**
 * Pay_For_Order class.
 *
 * Handles CAPTCHA integration with the pay for order page.
 *
 * @since 1.0.0
 */
class Pay_For_Order {

	/**
	 * Form type identifier.
	 *
	 * @var string
	 */
	const FORM_TYPE = 'wc_pay_order';

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		if ( ! Plugin::instance()->settings()->is_form_enabled( self::FORM_TYPE ) ) {
			return;
		}

		// Render CAPTCHA on pay for order form.
		add_action( 'woocommerce_pay_order_before_submit', array( $this, 'render_captcha' ) );

		// Validate CAPTCHA on pay for order submission.
		add_action( 'woocommerce_before_pay_action', array( $this, 'validate_captcha' ) );
	}

	/**
	 * Render CAPTCHA on the pay for order form.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function render_captcha() {
		Plugin::instance()->render( self::FORM_TYPE );
	}

	/**
	 * Validate CAPTCHA during pay for order submission.
	 *
	 * @since 1.0.0
	 * @param \WC_Order $order The order being paid for.
	 * @return void
	 */
	public function validate_captcha( $order ) {
		$result = Plugin::instance()->verify( self::FORM_TYPE );

		if ( is_wp_error( $result ) ) {
			wc_add_notice( $result->get_error_message(), 'error' );
		}
	}
}
