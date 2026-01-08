<?php
/**
 * WooCommerce Classic Checkout Integration.
 *
 * Adds CAPTCHA protection to the classic WooCommerce checkout form.
 *
 * @package Captcha_For_WooCommerce
 * @since   1.0.0
 */

namespace CFWC\Forms\WooCommerce;

use CFWC\Plugin;

// Prevent direct file access.
defined( 'ABSPATH' ) || exit;

/**
 * Checkout_Classic class.
 *
 * Handles CAPTCHA integration with the classic WooCommerce checkout.
 *
 * @since 1.0.0
 */
class Checkout_Classic {

	/**
	 * Form type identifier.
	 *
	 * @var string
	 */
	const FORM_TYPE = 'wc_checkout_classic';

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		if ( ! Plugin::instance()->settings()->is_form_enabled( self::FORM_TYPE ) ) {
			return;
		}

		// Render CAPTCHA on checkout form.
		add_action( 'woocommerce_review_order_before_submit', array( $this, 'render_captcha' ) );

		// Validate CAPTCHA on checkout.
		add_action( 'woocommerce_checkout_process', array( $this, 'validate_captcha' ) );
	}

	/**
	 * Render CAPTCHA on the checkout form.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function render_captcha() {
		// Skip if using block checkout.
		if ( $this->is_block_checkout() ) {
			return;
		}

		// Check if we should skip for this payment method.
		if ( $this->should_skip_for_payment_method() ) {
			// Output hidden marker for JS to detect.
			echo '<div class="cfwc-checkout-skip" data-skip="1" style="display:none;"></div>';
			return;
		}

		Plugin::instance()->render( self::FORM_TYPE );
	}

	/**
	 * Validate CAPTCHA during checkout.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function validate_captcha() {
		// Skip if using block checkout.
		if ( $this->is_block_checkout() ) {
			return;
		}

		// Check if we should skip for this payment method.
		if ( $this->should_skip_for_payment_method() ) {
			return;
		}

		$result = Plugin::instance()->verify( self::FORM_TYPE );

		if ( is_wp_error( $result ) ) {
			wc_add_notice( $result->get_error_message(), 'error' );
		}
	}

	/**
	 * Check if currently using block checkout.
	 *
	 * @since 1.0.0
	 * @return bool True if block checkout is being used.
	 */
	private function is_block_checkout() {
		// Check if checkout page uses blocks.
		if ( function_exists( 'has_block' ) ) {
			$checkout_page_id = wc_get_page_id( 'checkout' );
			if ( $checkout_page_id && has_block( 'woocommerce/checkout', $checkout_page_id ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Check if CAPTCHA should be skipped for the current payment method.
	 *
	 * Some payment methods have their own fraud protection or external
	 * verification, so we skip CAPTCHA to avoid double friction.
	 *
	 * @since 1.0.0
	 * @return bool True if CAPTCHA should be skipped.
	 */
	private function should_skip_for_payment_method() {
		// Get the selected payment method.
		$payment_method = WC()->session ? WC()->session->get( 'chosen_payment_method' ) : '';

		// Also check POST data (for form submission).
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- WooCommerce handles nonce verification in checkout process.
		if ( empty( $payment_method ) && isset( $_POST['payment_method'] ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- WooCommerce handles nonce verification in checkout process.
			$payment_method = sanitize_text_field( wp_unslash( $_POST['payment_method'] ) );
		}

		if ( empty( $payment_method ) ) {
			return false;
		}

		/**
		 * Filter whether to skip CAPTCHA for a specific payment method.
		 *
		 * @since 1.0.0
		 * @param bool   $skip           Whether to skip.
		 * @param string $payment_method The payment method ID.
		 */
		return apply_filters( 'cfwc_skip_for_payment_method', false, $payment_method );
	}
}
