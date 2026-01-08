<?php
/**
 * Provider Manager.
 *
 * Manages registration and retrieval of CAPTCHA providers.
 * Acts as a factory for provider instances.
 *
 * @package Captcha_For_WooCommerce
 * @since   1.0.0
 */

namespace CFWC\Providers;

// Prevent direct file access.
defined( 'ABSPATH' ) || exit;

/**
 * Manager class.
 *
 * Handles the registration and management of all available CAPTCHA providers.
 *
 * @since 1.0.0
 */
class Manager {

	/**
	 * Registered providers.
	 *
	 * @var array
	 */
	private $providers = array();

	/**
	 * Constructor.
	 *
	 * Registers all built-in CAPTCHA providers.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		$this->register_default_providers();

		/**
		 * Fires after default providers are registered.
		 *
		 * Use this hook to register custom CAPTCHA providers.
		 *
		 * @since 1.0.0
		 * @param Manager $manager The provider manager instance.
		 */
		do_action( 'cfwc_register_providers', $this );
	}

	/**
	 * Register default CAPTCHA providers.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private function register_default_providers() {
		// Cloudflare Turnstile - recommended default.
		$this->register( new Turnstile() );

		// Google reCAPTCHA v3 (invisible).
		$this->register( new Recaptcha_V3() );

		// Google reCAPTCHA v2 (checkbox).
		$this->register( new Recaptcha_V2() );

		// hCaptcha.
		$this->register( new Hcaptcha() );

		// Self-hosted honeypot.
		$this->register( new Honeypot() );
	}

	/**
	 * Register a CAPTCHA provider.
	 *
	 * @since 1.0.0
	 * @param Provider_Interface $provider The provider instance.
	 * @return void
	 */
	public function register( Provider_Interface $provider ) {
		$this->providers[ $provider->get_id() ] = $provider;
	}

	/**
	 * Unregister a provider.
	 *
	 * @since 1.0.0
	 * @param string $provider_id The provider identifier.
	 * @return void
	 */
	public function unregister( $provider_id ) {
		unset( $this->providers[ $provider_id ] );
	}

	/**
	 * Get a provider by ID.
	 *
	 * @since 1.0.0
	 * @param string $provider_id The provider identifier.
	 * @return Provider_Interface|null The provider or null if not found.
	 */
	public function get_provider( $provider_id ) {
		if ( isset( $this->providers[ $provider_id ] ) ) {
			return $this->providers[ $provider_id ];
		}

		return null;
	}

	/**
	 * Get all registered providers.
	 *
	 * @since 1.0.0
	 * @return array Array of provider instances.
	 */
	public function get_all() {
		/**
		 * Filter the list of available providers.
		 *
		 * @since 1.0.0
		 * @param array $providers Array of provider instances.
		 */
		return apply_filters( 'cfwc_providers', $this->providers );
	}

	/**
	 * Get providers as options for select field.
	 *
	 * Returns an array suitable for use in settings dropdowns.
	 *
	 * @since 1.0.0
	 * @return array Array of provider ID => name pairs.
	 */
	public function get_options() {
		$options = array(
			'' => __( 'Select a provider...', 'captcha-for-woocommerce' ),
		);

		foreach ( $this->providers as $id => $provider ) {
			$options[ $id ] = $provider->get_name();
		}

		return $options;
	}

	/**
	 * Check if a provider exists.
	 *
	 * @since 1.0.0
	 * @param string $provider_id The provider identifier.
	 * @return bool True if provider exists.
	 */
	public function exists( $provider_id ) {
		return isset( $this->providers[ $provider_id ] );
	}

	/**
	 * Get provider count.
	 *
	 * @since 1.0.0
	 * @return int Number of registered providers.
	 */
	public function count() {
		return count( $this->providers );
	}
}
