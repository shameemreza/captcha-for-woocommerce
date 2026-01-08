<?php
/**
 * WooCommerce Subscriptions Integration.
 *
 * Adds CAPTCHA protection for subscription checkout flows.
 *
 * @package Captcha_For_WooCommerce
 * @since   1.0.0
 */

namespace CFWC\Forms\Extensions;

use CFWC\Plugin;

// Prevent direct file access.
defined( 'ABSPATH' ) || exit;

/**
 * Subscriptions class.
 *
 * Handles CAPTCHA integration with WooCommerce Subscriptions.
 * Subscriptions use the standard checkout flow, so the main integration
 * is ensuring CAPTCHA works correctly with subscription products.
 *
 * @since 1.0.0
 */
class Subscriptions {

	/**
	 * Form type identifier.
	 *
	 * @var string
	 */
	const FORM_TYPE = 'wc_subscriptions';

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		// Check if Subscriptions is active.
		if ( ! class_exists( 'WC_Subscriptions' ) ) {
			return;
		}

		if ( ! Plugin::instance()->settings()->is_form_enabled( self::FORM_TYPE ) ) {
			return;
		}

		// Hook into subscription-specific checkout validation.
		add_action( 'woocommerce_checkout_subscription_created', array( $this, 'log_subscription_captcha' ), 10, 1 );

		// Support for early renewal and switch forms.
		add_action( 'wcs_before_early_renewal_form', array( $this, 'render_renewal_captcha' ) );
		add_action( 'wcs_switch_form_before_submit', array( $this, 'render_switch_captcha' ) );
	}

	/**
	 * Log CAPTCHA verification for subscription orders.
	 *
	 * @since 1.0.0
	 * @param \WC_Subscription $subscription The subscription object.
	 * @return void
	 */
	public function log_subscription_captcha( $subscription ) {
		// Add order meta to indicate CAPTCHA was verified.
		$subscription->update_meta_data( '_cfwc_captcha_verified', 'yes' );
		$subscription->update_meta_data( '_cfwc_captcha_timestamp', time() );
		$subscription->save();
	}

	/**
	 * Render CAPTCHA on early renewal form.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function render_renewal_captcha() {
		Plugin::instance()->render( self::FORM_TYPE );
	}

	/**
	 * Render CAPTCHA on subscription switch form.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function render_switch_captcha() {
		Plugin::instance()->render( self::FORM_TYPE );
	}
}
