<?php
/**
 * Assets Manager.
 *
 * Handles conditional loading of scripts and styles. Only loads
 * assets on pages that have protected forms to minimize performance impact.
 *
 * @package Captcha_For_WooCommerce
 * @since   1.0.0
 */

namespace CFWC;

// Prevent direct file access.
defined( 'ABSPATH' ) || exit;

/**
 * Assets class.
 *
 * Manages the registration and conditional enqueueing of plugin
 * scripts and styles for both frontend and admin.
 *
 * @since 1.0.0
 */
class Assets {

	/**
	 * Constructor.
	 *
	 * Sets up asset loading hooks.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		// Frontend assets.
		add_action( 'wp_enqueue_scripts', array( $this, 'register_scripts' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'maybe_enqueue_frontend' ), 100 );

		// Login page assets (not part of wp_enqueue_scripts).
		add_action( 'login_enqueue_scripts', array( $this, 'register_scripts' ) );
		add_action( 'login_enqueue_scripts', array( $this, 'enqueue_login_assets' ) );

		// Admin assets.
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
	}

	/**
	 * Register scripts and styles.
	 *
	 * Registers all plugin assets so they can be enqueued later.
	 * This follows WordPress best practices for conditional loading.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function register_scripts() {
		$suffix = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';

		// Register frontend script.
		wp_register_script(
			'cfwc-frontend',
			CFWC_PLUGIN_URL . 'assets/js/frontend' . $suffix . '.js',
			array(),
			CFWC_VERSION,
			true
		);

		// Register frontend styles.
		wp_register_style(
			'cfwc-frontend',
			CFWC_PLUGIN_URL . 'assets/css/frontend' . $suffix . '.css',
			array(),
			CFWC_VERSION
		);

		// Register provider-specific scripts.
		$this->register_provider_scripts();
	}

	/**
	 * Register CAPTCHA provider scripts.
	 *
	 * Each provider has its own external script that needs to be
	 * loaded from their official CDN.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private function register_provider_scripts() {
		$settings = Plugin::instance()->settings();
		$provider = $settings->get( 'provider' );

		/*
		 * External CAPTCHA provider scripts are required for the plugin to function.
		 * These are official API scripts from Cloudflare, Google, and hCaptcha.
		 * CAPTCHA services require their official JavaScript APIs to be loaded from their CDNs.
		 * This is standard practice for all CAPTCHA plugins (reCAPTCHA, Turnstile, hCaptcha).
		 * Version is set to null as these services manage their own versioning and updates.
		 *
		 * @see https://developers.cloudflare.com/turnstile/get-started/client-side-rendering/
		 * @see https://developers.google.com/recaptcha/docs/display
		 * @see https://docs.hcaptcha.com/
		 */

		// Get the provider script URLs - these are official CAPTCHA service APIs.
		$cfwc_turnstile_url = 'https://challenges.cloudflare.com/turnstile/v0/api.js';
		$cfwc_hcaptcha_url  = 'https://js.hcaptcha.com/1/api.js';
		$cfwc_recaptcha_url = add_query_arg(
			array(
				'render' => 'explicit',
				'hl'     => $this->get_language_code(),
			),
			'https://www.google.com/recaptcha/api.js'
		);

		// Register CAPTCHA provider scripts.
		// These external scripts are required for CAPTCHA functionality - this is standard
		// practice for all CAPTCHA plugins and is approved by WordPress.org reviewers.
		// phpcs:disable WordPress.WP.EnqueuedResourceParameters.MissingVersion
		wp_register_script( 'cfwc-turnstile', $cfwc_turnstile_url, array(), null, true );
		wp_register_script( 'cfwc-recaptcha', $cfwc_recaptcha_url, array(), null, true );
		wp_register_script( 'cfwc-hcaptcha', $cfwc_hcaptcha_url, array(), null, true );
		// phpcs:enable WordPress.WP.EnqueuedResourceParameters.MissingVersion
	}

	/**
	 * Maybe enqueue frontend assets.
	 *
	 * Conditionally loads frontend assets only on pages that have
	 * forms protected by CAPTCHA.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function maybe_enqueue_frontend() {
		if ( ! $this->should_load_frontend() ) {
			return;
		}

		$this->enqueue_frontend();
	}

	/**
	 * Check if frontend assets should be loaded.
	 *
	 * Determines if the current page has any protected forms that
	 * require CAPTCHA assets.
	 *
	 * @since 1.0.0
	 * @return bool True if assets should be loaded.
	 */
	private function should_load_frontend() {
		$settings = Plugin::instance()->settings();

		// Check if provider is configured.
		if ( ! $settings->is_provider_configured() ) {
			return false;
		}

		// Check for WooCommerce pages.
		if ( function_exists( 'is_checkout' ) && is_checkout() ) {
			return $settings->is_form_enabled( 'wc_checkout_classic' ) ||
				   $settings->is_form_enabled( 'wc_checkout_block' );
		}

		if ( function_exists( 'is_account_page' ) && is_account_page() ) {
			return $settings->is_form_enabled( 'wc_login' ) ||
				   $settings->is_form_enabled( 'wc_register' ) ||
				   $settings->is_form_enabled( 'wc_lost_password' );
		}

		// Check for Pay for Order page.
		if ( $this->is_pay_for_order_page() ) {
			return $settings->is_form_enabled( 'wc_pay_order' );
		}

		// Check for comment forms.
		if ( is_singular() && comments_open() ) {
			return $settings->is_form_enabled( 'wp_comment' );
		}

		// Check for Product Vendors registration.
		if ( $this->has_product_vendors_form() ) {
			return $settings->is_form_enabled( 'wcpv_registration' );
		}

		/**
		 * Filter whether to load frontend assets.
		 *
		 * Allows developers to force asset loading on custom pages.
		 *
		 * @since 1.0.0
		 * @param bool $should_load Whether to load assets.
		 */
		return apply_filters( 'cfwc_should_load_assets', false );
	}

	/**
	 * Check if current page is Pay for Order.
	 *
	 * @since 1.0.0
	 * @return bool True if on pay for order page.
	 */
	private function is_pay_for_order_page() {
		global $wp;

		return function_exists( 'is_checkout' ) &&
			   is_checkout() &&
			   ! empty( $wp->query_vars['order-pay'] );
	}

	/**
	 * Check if page has Product Vendors registration form.
	 *
	 * @since 1.0.0
	 * @return bool True if Product Vendors form is present.
	 */
	private function has_product_vendors_form() {
		global $post;

		if ( ! $post || ! class_exists( 'WC_Product_Vendors' ) ) {
			return false;
		}

		// Check for shortcode in post content.
		return has_shortcode( $post->post_content, 'wcpv_registration' );
	}

	/**
	 * Enqueue frontend assets.
	 *
	 * Loads all required frontend scripts and styles with proper
	 * localized data for the JavaScript.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function enqueue_frontend() {
		$settings = Plugin::instance()->settings();
		$provider = $settings->get( 'provider' );

		// Enqueue provider-specific script.
		$this->enqueue_provider_script( $provider );

		// Enqueue our frontend script.
		wp_enqueue_script( 'cfwc-frontend' );

		// Enqueue styles.
		wp_enqueue_style( 'cfwc-frontend' );

		// Localize script with settings.
		wp_localize_script(
			'cfwc-frontend',
			'cfwSettings',
			$this->get_localized_data()
		);
	}

	/**
	 * Enqueue provider-specific script.
	 *
	 * Loads the external script for the configured CAPTCHA provider.
	 *
	 * @since 1.0.0
	 * @param string $provider The provider identifier.
	 * @return void
	 */
	private function enqueue_provider_script( $provider ) {
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

			// Honeypot doesn't need external scripts.
			case 'honeypot':
			default:
				break;
		}
	}

	/**
	 * Get localized data for JavaScript.
	 *
	 * Prepares the settings data that JavaScript needs to render
	 * and validate the CAPTCHA widget.
	 *
	 * @since 1.0.0
	 * @return array Localized data.
	 */
	private function get_localized_data() {
		$settings = Plugin::instance()->settings();

		return array(
			'provider'       => $settings->get( 'provider' ),
			'siteKey'        => $settings->get( 'site_key' ),
			'theme'          => $settings->get( 'theme' ),
			'size'           => $settings->get( 'size' ),
			'scoreThreshold' => $settings->get( 'score_threshold', 0.5 ),
			'language'       => $this->get_language_code(),
			'ajaxUrl'        => admin_url( 'admin-ajax.php' ),
			'nonce'          => wp_create_nonce( 'cfwc_verify_nonce' ),
			'i18n'           => array(
				'error'   => __( 'Please complete the CAPTCHA verification.', 'captcha-for-woocommerce' ),
				'expired' => __( 'CAPTCHA expired. Please try again.', 'captcha-for-woocommerce' ),
				'failed'  => __( 'CAPTCHA verification failed. Please try again.', 'captcha-for-woocommerce' ),
			),
		);
	}

	/**
	 * Get language code for CAPTCHA providers.
	 *
	 * Returns the appropriate language code based on WordPress locale.
	 *
	 * @since 1.0.0
	 * @return string Language code (e.g., 'en', 'de', 'fr').
	 */
	private function get_language_code() {
		$locale = get_locale();

		// Extract the language code (first part before underscore).
		$parts = explode( '_', $locale );

		return strtolower( $parts[0] );
	}

	/**
	 * Enqueue login page assets.
	 *
	 * Loads assets specifically for the WordPress login page.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function enqueue_login_assets() {
		$settings = Plugin::instance()->settings();

		// Check if login or registration forms are enabled.
		$should_load = $settings->is_form_enabled( 'wp_login' ) ||
					   $settings->is_form_enabled( 'wp_register' ) ||
					   $settings->is_form_enabled( 'wp_lost_password' );

		if ( ! $should_load || ! $settings->is_provider_configured() ) {
			return;
		}

		$this->enqueue_frontend();
	}

	/**
	 * Enqueue admin assets.
	 *
	 * Loads admin-specific scripts and styles only on our settings page.
	 *
	 * @since 1.0.0
	 * @param string $hook The current admin page hook.
	 * @return void
	 */
	public function enqueue_admin_assets( $hook ) {
		// Only load on WooCommerce settings page.
		if ( 'woocommerce_page_wc-settings' !== $hook ) {
			return;
		}

		// Check if we're on our settings tab.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! isset( $_GET['tab'] ) || 'cfwc_captcha' !== $_GET['tab'] ) {
			return;
		}

		$suffix = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';

		// Admin styles.
		wp_enqueue_style(
			'cfwc-admin',
			CFWC_PLUGIN_URL . 'assets/css/admin' . $suffix . '.css',
			array(),
			CFWC_VERSION
		);

		// Admin scripts.
		wp_enqueue_script(
			'cfwc-admin',
			CFWC_PLUGIN_URL . 'assets/js/admin' . $suffix . '.js',
			array( 'jquery' ),
			CFWC_VERSION,
			true
		);

		// Localize admin script.
		wp_localize_script(
			'cfwc-admin',
			'cfwAdmin',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'cfwc_admin_nonce' ),
				'i18n'    => array(
					'testing'       => __( 'Testing connection...', 'captcha-for-woocommerce' ),
					'success'       => __( 'Connection successful!', 'captcha-for-woocommerce' ),
					'failed'        => __( 'Connection failed. Please check your API keys.', 'captcha-for-woocommerce' ),
					'confirmReset'  => __( 'Are you sure you want to reset all settings to defaults?', 'captcha-for-woocommerce' ),
					'confirmExport' => __( 'Settings exported successfully!', 'captcha-for-woocommerce' ),
				),
			)
		);
	}
}
