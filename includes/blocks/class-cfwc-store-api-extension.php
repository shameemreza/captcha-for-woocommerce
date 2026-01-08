<?php
/**
 * Store API Extension.
 *
 * Extends the WooCommerce Store API to handle CAPTCHA validation
 * for Block Checkout submissions.
 *
 * @package Captcha_For_WooCommerce
 * @since   1.0.0
 */

namespace CFWC\Blocks;

use CFWC\Plugin;
use CFWC\Compatibility\PayPal_Payments;

// Prevent direct file access.
defined( 'ABSPATH' ) || exit;

/**
 * Store_API_Extension class.
 *
 * Registers a Store API endpoint extension for CAPTCHA token
 * validation during Block Checkout.
 *
 * @since 1.0.0
 */
class Store_API_Extension {

	/**
	 * Extension namespace.
	 *
	 * @var string
	 */
	const NAMESPACE = 'captcha-for-woocommerce';

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		// Register the endpoint data extension.
		$this->register_endpoint_data();

		// Validate CAPTCHA on checkout submission.
		add_action(
			'woocommerce_store_api_checkout_update_order_from_request',
			array( $this, 'validate_checkout' ),
			10,
			2
		);
	}

	/**
	 * Register endpoint data for the checkout API.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private function register_endpoint_data() {
		if ( ! function_exists( 'woocommerce_store_api_register_endpoint_data' ) ) {
			return;
		}

		// Check if CheckoutSchema class exists (WooCommerce Blocks available).
		if ( ! class_exists( 'Automattic\WooCommerce\StoreApi\Schemas\V1\CheckoutSchema' ) ) {
			return;
		}

		woocommerce_store_api_register_endpoint_data(
			array(
				'endpoint'        => \Automattic\WooCommerce\StoreApi\Schemas\V1\CheckoutSchema::IDENTIFIER,
				'namespace'       => self::NAMESPACE,
				'schema_callback' => array( $this, 'get_schema' ),
				'schema_type'     => ARRAY_A,
			)
		);
	}

	/**
	 * Get the schema for the extension data.
	 *
	 * @since 1.0.0
	 * @return array Schema definition.
	 */
	public function get_schema() {
		return array(
			'token' => array(
				'description' => __( 'CAPTCHA verification token', 'captcha-for-woocommerce' ),
				'type'        => 'string',
				'context'     => array( 'view', 'edit' ),
			),
		);
	}

	/**
	 * Validate CAPTCHA on checkout submission.
	 *
	 * @since 1.0.0
	 * @param \WC_Order        $order   The order being processed.
	 * @param \WP_REST_Request $request The REST request.
	 * @return void
	 * @throws \Exception If validation fails.
	 */
	public function validate_checkout( $order, $request ) {
		// Only validate POST requests.
		if ( 'POST' !== $request->get_method() ) {
			return;
		}

		// Check if Block Checkout CAPTCHA is enabled.
		if ( ! Plugin::instance()->settings()->is_form_enabled( 'wc_checkout_block' ) ) {
			return;
		}

		// Check if we should skip for this payment method.
		if ( $this->should_skip_for_payment_method( $request ) ) {
			return;
		}

		// Get the CAPTCHA token from extensions data.
		$extensions = $request->get_param( 'extensions' );
		$token      = '';

		if ( isset( $extensions[ self::NAMESPACE ]['token'] ) ) {
			$token = sanitize_text_field( $extensions[ self::NAMESPACE ]['token'] );
		}

		// Get provider and verify.
		$provider = Plugin::instance()->provider();

		if ( ! $provider ) {
			// No provider configured - skip validation.
			return;
		}

		// For honeypot provider, token might be empty (validation happens differently).
		if ( 'honeypot' === $provider->get_id() ) {
			return;
		}

		// Check for missing token.
		if ( empty( $token ) ) {
			throw new \Exception(
				esc_html__( 'CAPTCHA verification is required. Please complete the security check.', 'captcha-for-woocommerce' )
			);
		}

		// Verify the token.
		$result = $provider->verify( $token );

		if ( is_wp_error( $result ) ) {
			throw new \Exception( esc_html( $result->get_error_message() ) );
		}

		// Add verification meta to order.
		$order->update_meta_data( '_cfwc_captcha_verified', 'yes' );
		$order->update_meta_data( '_cfwc_captcha_provider', $provider->get_id() );
		$order->update_meta_data( '_cfwc_captcha_timestamp', time() );
	}

	/**
	 * Check if CAPTCHA should be skipped for the payment method.
	 *
	 * @since 1.0.0
	 * @param \WP_REST_Request $request The REST request.
	 * @return bool True if should skip.
	 */
	private function should_skip_for_payment_method( $request ) {
		$payment_method = $request->get_param( 'payment_method' );

		if ( empty( $payment_method ) ) {
			return false;
		}

		// Check for PayPal Payments.
		if ( class_exists( '\CFW\Compatibility\PayPal_Payments' ) && PayPal_Payments::is_paypal_recaptcha_enabled() ) {
			$paypal_methods = PayPal_Payments::get_paypal_protected_methods();
			if ( in_array( $payment_method, $paypal_methods, true ) ) {
				return true;
			}
		}

		// Check for express payment methods.
		$express_patterns = array( 'applepay', 'googlepay', 'apple_pay', 'google_pay', 'amazonpay' );
		$method_lower     = strtolower( $payment_method );

		foreach ( $express_patterns as $pattern ) {
			if ( false !== strpos( $method_lower, $pattern ) ) {
				return true;
			}
		}

		/**
		 * Filter whether to skip CAPTCHA for a payment method.
		 *
		 * @since 1.0.0
		 * @param bool   $skip           Whether to skip.
		 * @param string $payment_method The payment method ID.
		 */
		return apply_filters( 'cfwc_skip_for_payment_method', false, $payment_method );
	}
}
