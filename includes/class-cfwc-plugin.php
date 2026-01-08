<?php
/**
 * Main Plugin Class.
 *
 * The main orchestrator class that initializes and coordinates
 * all plugin components. Uses singleton pattern to ensure only
 * one instance exists.
 *
 * @package Captcha_For_WooCommerce
 * @since   1.0.0
 */

namespace CFWC;

// Prevent direct file access.
defined( 'ABSPATH' ) || exit;

/**
 * Plugin class.
 *
 * Main entry point for the plugin. Handles initialization of all
 * components including settings, providers, forms, and assets.
 *
 * @since 1.0.0
 */
final class Plugin {

	/**
	 * Single instance of the class.
	 *
	 * @var Plugin|null
	 */
	private static $instance = null;

	/**
	 * Settings manager instance.
	 *
	 * @var Settings|null
	 */
	private $settings = null;

	/**
	 * Provider manager instance.
	 *
	 * @var Providers\Manager|null
	 */
	private $providers = null;

	/**
	 * Assets manager instance.
	 *
	 * @var Assets|null
	 */
	private $assets = null;

	/**
	 * Get single instance of the class.
	 *
	 * Uses singleton pattern to ensure only one instance exists,
	 * which is important for maintaining consistent state across
	 * the plugin.
	 *
	 * @since 1.0.0
	 * @return Plugin Single instance of the class.
	 */
	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 *
	 * Private to enforce singleton pattern. Initializes all plugin
	 * components and sets up necessary hooks.
	 *
	 * @since 1.0.0
	 */
	private function __construct() {
		$this->init_components();
		$this->init_hooks();

		/**
		 * Fires after the plugin is fully loaded.
		 *
		 * Use this hook to extend the plugin functionality or add
		 * custom integrations that depend on the plugin being ready.
		 *
		 * @since 1.0.0
		 */
		do_action( 'cfwc_loaded' );
	}

	/**
	 * Prevent cloning.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private function __clone() {}

	/**
	 * Prevent unserializing.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function __wakeup() {
		throw new \Exception( 'Cannot unserialize singleton' );
	}

	/**
	 * Initialize plugin components.
	 *
	 * Creates instances of all major plugin components. Each component
	 * is responsible for a specific area of functionality.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private function init_components() {
		// Core components (these don't call Plugin::instance() in constructors).
		$this->settings  = new Settings();
		$this->providers = new Providers\Manager();
		$this->assets    = new Assets();

		// Initialize admin components if in admin context.
		if ( is_admin() ) {
			new Admin\Settings_Page();
			new Admin\Admin_Notices();
		}

		// Defer form initialization to 'init' hook to avoid recursion.
		// Form classes call Plugin::instance() in their constructors.
		add_action( 'init', array( $this, 'init_forms' ), 0 );

		// Initialize WooCommerce Block Checkout support.
		if ( $this->is_block_checkout_enabled() ) {
			add_action( 'woocommerce_blocks_loaded', array( $this, 'init_block_checkout' ) );
		}
	}

	/**
	 * Initialize WordPress and WooCommerce form integrations.
	 *
	 * Sets up CAPTCHA protection for all supported forms based
	 * on the user's configuration. Called at 'init' hook to avoid
	 * recursion during plugin construction.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function init_forms() {
		// WordPress core forms.
		new Forms\WordPress\Login_Form();
		new Forms\WordPress\Register_Form();
		new Forms\WordPress\Lost_Password_Form();
		new Forms\WordPress\Comment_Form();

		// WooCommerce forms.
		new Forms\WooCommerce\Login_Form();
		new Forms\WooCommerce\Register_Form();
		new Forms\WooCommerce\Lost_Password_Form();
		new Forms\WooCommerce\Checkout_Classic();
		new Forms\WooCommerce\Pay_For_Order();

		// WooCommerce extension forms.
		$this->init_extension_forms();
	}

	/**
	 * Initialize WooCommerce extension form integrations.
	 *
	 * Conditionally loads form handlers for installed WooCommerce
	 * extensions like Product Vendors, Subscriptions, and Memberships.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private function init_extension_forms() {
		// Product Vendors - check if plugin is active.
		if ( class_exists( 'WC_Product_Vendors' ) ) {
			new Forms\Extensions\Product_Vendors();
		}

		// Subscriptions - check if plugin is active.
		if ( class_exists( 'WC_Subscriptions' ) ) {
			new Forms\Extensions\Subscriptions();
		}

		// Memberships - check if plugin is active.
		if ( function_exists( 'wc_memberships' ) ) {
			new Forms\Extensions\Memberships();
		}
	}

	/**
	 * Initialize Block Checkout integration.
	 *
	 * Sets up the necessary components for CAPTCHA support in the
	 * WooCommerce Block-based Checkout.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function init_block_checkout() {
		new Blocks\Checkout_Integration();
		new Blocks\Store_API_Extension();
	}

	/**
	 * Check if Block Checkout is enabled.
	 *
	 * Determines if the store is using the block-based checkout
	 * which requires special handling for CAPTCHA integration.
	 *
	 * @since 1.0.0
	 * @return bool True if block checkout is potentially in use.
	 */
	private function is_block_checkout_enabled() {
		// Check if WooCommerce Blocks is available.
		return class_exists( 'Automattic\WooCommerce\Blocks\Package' );
	}

	/**
	 * Initialize hooks.
	 *
	 * Sets up action and filter hooks that need to be registered
	 * at the plugin level.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private function init_hooks() {
		// Initialize payment gateway compatibility.
		add_action( 'init', array( $this, 'init_payment_compatibility' ) );
	}

	/**
	 * Initialize payment gateway compatibility.
	 *
	 * Sets up smart detection and skip logic for payment gateways
	 * that have their own fraud protection.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function init_payment_compatibility() {
		new Compatibility\PayPal_Payments();
		new Compatibility\Express_Payments();
	}

	/**
	 * Get settings manager.
	 *
	 * @since 1.0.0
	 * @return Settings The settings manager instance.
	 */
	public function settings() {
		return $this->settings;
	}

	/**
	 * Get provider manager.
	 *
	 * @since 1.0.0
	 * @return Providers\Manager The provider manager instance.
	 */
	public function providers() {
		return $this->providers;
	}

	/**
	 * Get current active provider.
	 *
	 * Convenience method to get the currently configured CAPTCHA
	 * provider instance.
	 *
	 * @since 1.0.0
	 * @return Providers\Provider_Interface|null The active provider or null.
	 */
	public function provider() {
		$provider_id = $this->settings->get( 'provider' );
		return $this->providers->get_provider( $provider_id );
	}

	/**
	 * Get assets manager.
	 *
	 * @since 1.0.0
	 * @return Assets The assets manager instance.
	 */
	public function assets() {
		return $this->assets;
	}

	/**
	 * Render CAPTCHA widget for a specific form.
	 *
	 * Convenience method to render the CAPTCHA widget. Handles
	 * all the logic of checking if CAPTCHA should be shown and
	 * which provider to use.
	 *
	 * @since 1.0.0
	 * @param string $form_type The form identifier (e.g., 'wp_login', 'wc_checkout').
	 * @param array  $args      Optional. Additional arguments for rendering.
	 * @return void
	 */
	public function render( $form_type, $args = array() ) {
		// Check if this form has CAPTCHA enabled.
		if ( ! $this->settings->is_form_enabled( $form_type ) ) {
			return;
		}

		// Check if user should be skipped (whitelist).
		if ( $this->should_skip_for_user() ) {
			return;
		}

		/**
		 * Filter to skip CAPTCHA verification for specific conditions.
		 *
		 * @since 1.0.0
		 * @param bool   $skip      Whether to skip CAPTCHA. Default false.
		 * @param string $form_type The form identifier.
		 * @param array  $args      Additional render arguments.
		 */
		if ( apply_filters( 'cfwc_skip_verification', false, $form_type, $args ) ) {
			return;
		}

		// Get provider and render.
		$provider = $this->provider();
		if ( $provider ) {
			/**
			 * Fires before the CAPTCHA widget is rendered.
			 *
			 * @since 1.0.0
			 * @param string $form_type The form identifier.
			 * @param array  $args      Additional render arguments.
			 */
			do_action( 'cfwc_before_render', $form_type, $args );

			$provider->render( $form_type, $args );

			/**
			 * Fires after the CAPTCHA widget is rendered.
			 *
			 * @since 1.0.0
			 * @param string $form_type The form identifier.
			 * @param array  $args      Additional render arguments.
			 */
			do_action( 'cfwc_after_render', $form_type, $args );
		}
	}

	/**
	 * Verify CAPTCHA response for a specific form.
	 *
	 * Validates the CAPTCHA response submitted with the form.
	 * Returns true on success, or WP_Error on failure.
	 *
	 * @since 1.0.0
	 * @param string $form_type The form identifier.
	 * @return bool|\WP_Error True on success, WP_Error on failure.
	 */
	public function verify( $form_type ) {
		// Check if this form has CAPTCHA enabled.
		if ( ! $this->settings->is_form_enabled( $form_type ) ) {
			return true;
		}

		// Check if user should be skipped.
		if ( $this->should_skip_for_user() ) {
			return true;
		}

		/**
		 * Filter to skip CAPTCHA verification for specific conditions.
		 *
		 * @since 1.0.0
		 * @param bool   $skip      Whether to skip verification. Default false.
		 * @param string $form_type The form identifier.
		 * @param mixed  $user      Current user or null.
		 */
		if ( apply_filters( 'cfwc_skip_verification', false, $form_type, wp_get_current_user() ) ) {
			return true;
		}

		/**
		 * Fires before CAPTCHA verification begins.
		 *
		 * @since 1.0.0
		 * @param string $form_type The form identifier.
		 */
		do_action( 'cfwc_before_verify', $form_type );

		// Get provider and verify.
		$provider = $this->provider();
		if ( ! $provider ) {
			// No provider configured - check if honeypot fallback is enabled.
			if ( 'yes' === $this->settings->get( 'enable_honeypot' ) ) {
				$honeypot = new Providers\Honeypot();
				return $honeypot->verify();
			}
			return true;
		}

		$result = $provider->verify();

		// Log the result if debug logging is enabled.
		if ( 'yes' === $this->settings->get( 'enable_debug_logging' ) ) {
			Logger::log_verification( $form_type, $result );
		}

		// Fire appropriate action based on result.
		if ( is_wp_error( $result ) ) {
			/**
			 * Fires when CAPTCHA verification fails.
			 *
			 * @since 1.0.0
			 * @param string    $form_type The form identifier.
			 * @param \WP_Error $result    The error object.
			 */
			do_action( 'cfwc_failed', $form_type, $result );
		} else {
			/**
			 * Fires when CAPTCHA verification succeeds.
			 *
			 * @since 1.0.0
			 * @param string $form_type The form identifier.
			 * @param mixed  $result    The verification response.
			 */
			do_action( 'cfwc_verified', $form_type, $result );
		}

		return $result;
	}

	/**
	 * Check if CAPTCHA should be skipped for the current user.
	 *
	 * Evaluates whitelist rules to determine if the current user
	 * or their IP address should skip CAPTCHA verification.
	 *
	 * @since 1.0.0
	 * @return bool True if CAPTCHA should be skipped.
	 */
	private function should_skip_for_user() {
		// Check if logged-in users should be skipped.
		if ( is_user_logged_in() && 'yes' === $this->settings->get( 'whitelist_logged_in' ) ) {
			return true;
		}

		// Check if user's role should be skipped.
		if ( is_user_logged_in() ) {
			$whitelist_roles = $this->settings->get( 'whitelist_roles', array() );
			$user            = wp_get_current_user();

			if ( ! empty( $whitelist_roles ) && array_intersect( $user->roles, $whitelist_roles ) ) {
				return true;
			}
		}

		// Check if IP is whitelisted.
		$whitelist_ips = $this->settings->get( 'whitelist_ips', '' );
		if ( ! empty( $whitelist_ips ) ) {
			$client_ip = $this->get_client_ip();
			$ip_list   = array_map( 'trim', explode( "\n", $whitelist_ips ) );

			if ( in_array( $client_ip, $ip_list, true ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Get the client's IP address.
	 *
	 * Attempts to determine the real client IP, accounting for
	 * proxies and load balancers.
	 *
	 * @since 1.0.0
	 * @return string The client IP address.
	 */
	private function get_client_ip() {
		$ip = '';

		// Check for forwarded IP (behind proxy/load balancer).
		if ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
			$forwarded_ips = explode( ',', sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) );
			$ip            = trim( $forwarded_ips[0] );
		} elseif ( ! empty( $_SERVER['HTTP_X_REAL_IP'] ) ) {
			$ip = sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_REAL_IP'] ) );
		} elseif ( ! empty( $_SERVER['REMOTE_ADDR'] ) ) {
			$ip = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );
		}

		return $ip;
	}
}
