<?php
/**
 * Block Checkout Integration.
 *
 * Integrates CAPTCHA with WooCommerce Block Checkout using vanilla JavaScript.
 * This approach is simpler and doesn't require wp-scripts compilation.
 *
 * @package Captcha_For_WooCommerce
 * @since   1.0.0
 */

namespace CFWC\Blocks;

use CFWC\Plugin;

// Prevent direct file access.
defined( 'ABSPATH' ) || exit;

/**
 * Checkout_Integration class.
 *
 * Handles Block Checkout CAPTCHA widget rendering and validation.
 * Uses vanilla JavaScript with wp.data for Store API communication.
 *
 * @since 1.0.0
 */
class Checkout_Integration {

	/**
	 * Integration namespace for Store API.
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
		// Only load if Block Checkout is enabled.
		if ( ! Plugin::instance()->settings()->is_form_enabled( 'wc_checkout_block' ) ) {
			return;
		}

		// Render widget container in Block Checkout.
		add_action( 'woocommerce_blocks_checkout_block_registration', array( $this, 'register_integration' ) );
		add_action( 'woocommerce_blocks_enqueue_checkout_block_scripts_after', array( $this, 'enqueue_scripts' ) );

		// Add widget container to checkout block.
		add_filter( 'render_block_woocommerce/checkout', array( $this, 'render_captcha_container' ), 10, 2 );
	}

	/**
	 * Register with WooCommerce Blocks if available.
	 *
	 * Uses vanilla JavaScript approach with wp.data for Store API integration.
	 * IntegrationInterface is not required for basic functionality.
	 *
	 * @since 1.0.0
	 * @param object $integration_registry Integration registry.
	 * @return void
	 */
	public function register_integration( $integration_registry ) {
		// Our implementation uses vanilla JS with wp.data.
		// No IntegrationInterface registration needed.
	}

	/**
	 * Enqueue scripts for Block Checkout.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function enqueue_scripts() {
		$settings = Plugin::instance()->settings();
		$provider = $settings->get( 'provider' );

		// Enqueue the provider script.
		switch ( $provider ) {
			case 'turnstile':
				wp_enqueue_script( 'cfwc-turnstile' );
				break;
			case 'recaptcha_v2':
			case 'recaptcha_v3':
				wp_enqueue_script( 'cfwc-recaptcha' );
				break;
			case 'hcaptcha':
				wp_enqueue_script( 'cfwc-hcaptcha' );
				break;
		}

		// Ensure wp-data is loaded (required for wp.data.dispatch).
		// Block Checkout uses wp.data to communicate with the Store API.
		wp_enqueue_script( 'wp-data' );

		// Enqueue frontend script.
		wp_enqueue_script( 'cfwc-frontend' );

		// Pass settings to frontend.
		wp_localize_script(
			'cfwc-frontend',
			'cfwSettings',
			array(
				'provider'        => $provider,
				'siteKey'         => $settings->get( 'site_key' ),
				'theme'           => $settings->get( 'theme' ),
				'size'            => $settings->get( 'size' ),
				'namespace'       => self::NAMESPACE,
				'isBlockCheckout' => true,
				'i18n'            => array(
					'error'   => __( 'Please complete the CAPTCHA verification.', 'captcha-for-woocommerce' ),
					'expired' => __( 'CAPTCHA expired. Please try again.', 'captcha-for-woocommerce' ),
					'failed'  => __( 'CAPTCHA verification failed. Please try again.', 'captcha-for-woocommerce' ),
				),
			)
		);
	}

	/**
	 * Render CAPTCHA container in Block Checkout.
	 *
	 * Injects the widget container before the place order button.
	 *
	 * @since 1.0.0
	 * @param string $content Block content.
	 * @param array  $block   Block data.
	 * @return string Modified content.
	 */
	public function render_captcha_container( $content, $block ) {
		$settings = Plugin::instance()->settings();

		// Skip if honeypot (handled server-side only).
		if ( 'honeypot' === $settings->get( 'provider' ) ) {
			return $content;
		}

		// Build the captcha container.
		$captcha_html = sprintf(
			'<div id="cfwc-block-checkout-captcha" class="cfwc-captcha-field cfwc-block-checkout" data-provider="%s" data-sitekey="%s" data-theme="%s"></div>',
			esc_attr( $settings->get( 'provider' ) ),
			esc_attr( $settings->get( 'site_key' ) ),
			esc_attr( $settings->get( 'theme' ) )
		);

		// Inject before the place order button.
		// Look for the checkout actions block.
		$pattern = '/(<div[^>]*class="[^"]*wc-block-checkout__actions[^"]*"[^>]*>)/i';
		if ( preg_match( $pattern, $content ) ) {
			$content = preg_replace( $pattern, $captcha_html . '$1', $content );
		} else {
			// Fallback: append to the end.
			$content .= $captcha_html;
		}

		return $content;
	}
}
