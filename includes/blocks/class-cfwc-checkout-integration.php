<?php
/**
 * Block Checkout Integration.
 *
 * Integrates CAPTCHA with WooCommerce Block Checkout using the
 * official Blocks API.
 *
 * @package Captcha_For_WooCommerce
 * @since   1.0.0
 */

namespace CFWC\Blocks;

use CFWC\Plugin;

// Prevent direct file access.
defined( 'ABSPATH' ) || exit;

// Bail early if the interface doesn't exist (WooCommerce Blocks not loaded).
if ( ! interface_exists( 'Automattic\WooCommerce\Blocks\Integrations\IntegrationInterface' ) ) {
	return;
}

/**
 * Checkout_Integration class.
 *
 * Implements the WooCommerce Blocks IntegrationInterface to properly
 * integrate CAPTCHA with the React-based Block Checkout.
 *
 * @since 1.0.0
 */
class Checkout_Integration implements \Automattic\WooCommerce\Blocks\Integrations\IntegrationInterface {

	/**
	 * Integration name.
	 *
	 * @var string
	 */
	const INTEGRATION_NAME = 'captcha-for-woocommerce';

	/**
	 * Get the name of the integration.
	 *
	 * @since 1.0.0
	 * @return string Integration name.
	 */
	public function get_name() {
		return self::INTEGRATION_NAME;
	}

	/**
	 * Initialize the integration.
	 *
	 * Called when WooCommerce Blocks loads the integration.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function initialize() {
		$this->register_block_scripts();
		$this->register_block_editor_scripts();
	}

	/**
	 * Get the script handles for the frontend.
	 *
	 * @since 1.0.0
	 * @return array Script handles.
	 */
	public function get_script_handles() {
		return array( 'cfwc-checkout-block-frontend' );
	}

	/**
	 * Get the script handles for the editor.
	 *
	 * @since 1.0.0
	 * @return array Script handles.
	 */
	public function get_editor_script_handles() {
		return array( 'cfwc-checkout-block-editor' );
	}

	/**
	 * Get script data for the frontend.
	 *
	 * This data is passed to the JavaScript via wp_localize_script.
	 *
	 * @since 1.0.0
	 * @return array Script data.
	 */
	public function get_script_data() {
		$settings = Plugin::instance()->settings();

		return array(
			'enabled'        => $settings->is_form_enabled( 'wc_checkout_block' ),
			'provider'       => $settings->get( 'provider' ),
			'siteKey'        => $settings->get( 'site_key' ),
			'theme'          => $settings->get( 'theme' ),
			'size'           => $settings->get( 'size' ),
			'scoreThreshold' => $settings->get( 'score_threshold', 0.5 ),
			'namespace'      => self::INTEGRATION_NAME,
			'i18n'           => array(
				'error'   => __( 'Please complete the CAPTCHA verification.', 'captcha-for-woocommerce' ),
				'expired' => __( 'CAPTCHA expired. Please try again.', 'captcha-for-woocommerce' ),
				'failed'  => __( 'CAPTCHA verification failed. Please try again.', 'captcha-for-woocommerce' ),
			),
		);
	}

	/**
	 * Register frontend scripts for Block Checkout.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private function register_block_scripts() {
		$asset_file = CFWC_PLUGIN_DIR . 'build/checkout-block/index.asset.php';

		if ( file_exists( $asset_file ) ) {
			$asset = require $asset_file;
		} else {
			$asset = array(
				'dependencies' => array(
					'wp-element',
					'wp-data',
					'wp-plugins',
					'wc-blocks-checkout',
				),
				'version'      => CFWC_VERSION,
			);
		}

		wp_register_script(
			'cfwc-checkout-block-frontend',
			CFWC_PLUGIN_URL . 'build/checkout-block/index.js',
			$asset['dependencies'],
			$asset['version'],
			true
		);

		// Register provider scripts that might be needed.
		$this->register_provider_scripts();

		// Add script data using the proper WordPress method.
		wp_add_inline_script(
			'cfwc-checkout-block-frontend',
			'var cfwcBlockCheckout = ' . wp_json_encode( $this->get_script_data() ) . ';',
			'before'
		);
	}

	/**
	 * Register editor scripts for Block Checkout.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private function register_block_editor_scripts() {
		$asset_file = CFWC_PLUGIN_DIR . 'build/checkout-block/editor.asset.php';

		if ( file_exists( $asset_file ) ) {
			$asset = require $asset_file;
		} else {
			$asset = array(
				'dependencies' => array( 'wp-element' ),
				'version'      => CFWC_VERSION,
			);
		}

		wp_register_script(
			'cfwc-checkout-block-editor',
			CFWC_PLUGIN_URL . 'build/checkout-block/editor.js',
			$asset['dependencies'],
			$asset['version'],
			true
		);
	}

	/**
	 * Register provider scripts for Block Checkout.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private function register_provider_scripts() {
		$settings = Plugin::instance()->settings();
		$provider = $settings->get( 'provider' );

		// Provider scripts are registered globally in Assets class.
		// Enqueue the appropriate one for Block Checkout.
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
	}
}
