<?php
/**
 * Express Payments Compatibility.
 *
 * Handles compatibility with express payment methods that have
 * their own fraud protection (Apple Pay, Google Pay, etc.).
 *
 * @package Captcha_For_WooCommerce
 * @since   1.0.0
 */

namespace CFWC\Compatibility;

// Prevent direct file access.
defined( 'ABSPATH' ) || exit;

/**
 * Express_Payments class.
 *
 * Manages CAPTCHA skip logic for express payment methods.
 *
 * @since 1.0.0
 */
class Express_Payments {

	/**
	 * Express payment method identifiers.
	 *
	 * @var array
	 */
	private $express_methods = array();

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		$this->init_express_methods();

		// Register filter to skip CAPTCHA for express payments.
		add_filter( 'cfwc_skip_for_payment_method', array( $this, 'skip_for_express' ), 10, 2 );
	}

	/**
	 * Initialize express payment method identifiers.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private function init_express_methods() {
		$this->express_methods = array(
			// Apple Pay.
			'apple_pay',
			'woocommerce_payments_apple_pay',
			'stripe_apple_pay',
			'ppcp-apple-pay',

			// Google Pay.
			'google_pay',
			'woocommerce_payments_google_pay',
			'stripe_google_pay',
			'ppcp-google-pay',

			// Amazon Pay.
			'amazon_payments_advanced',
			'amazon_pay',

			// WooPayments Express.
			'woocommerce_payments',

			// Link by Stripe.
			'stripe_link',
		);

		/**
		 * Filter express payment method identifiers.
		 *
		 * @since 1.0.0
		 * @param array $methods Array of express payment method IDs.
		 */
		$this->express_methods = apply_filters( 'cfwc_express_payment_methods', $this->express_methods );
	}

	/**
	 * Check if a payment method is express (should skip CAPTCHA).
	 *
	 * @since 1.0.0
	 * @param string $payment_method The payment method ID.
	 * @return bool True if express payment method.
	 */
	public function is_express_method( $payment_method ) {
		// Exact match.
		if ( in_array( $payment_method, $this->express_methods, true ) ) {
			return true;
		}

		// Pattern matching for variations.
		$express_patterns = array(
			'apple_pay',
			'applepay',
			'google_pay',
			'googlepay',
			'amazon_pay',
			'amazonpay',
		);

		foreach ( $express_patterns as $pattern ) {
			if ( false !== strpos( strtolower( $payment_method ), $pattern ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Skip CAPTCHA for express payment methods.
	 *
	 * @since 1.0.0
	 * @param bool   $skip           Whether to skip CAPTCHA.
	 * @param string $payment_method The payment method ID.
	 * @return bool Whether to skip.
	 */
	public function skip_for_express( $skip, $payment_method ) {
		if ( $skip ) {
			return $skip;
		}

		if ( $this->is_express_method( $payment_method ) ) {
			return true;
		}

		return $skip;
	}

	/**
	 * Get list of express payment methods.
	 *
	 * @since 1.0.0
	 * @return array Array of express payment method IDs.
	 */
	public function get_express_methods() {
		return $this->express_methods;
	}
}
