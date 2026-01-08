<?php
/**
 * Settings Manager.
 *
 * Handles all plugin settings including retrieval, validation,
 * and sanitization. Provides a clean API for accessing configuration.
 *
 * @package Captcha_For_WooCommerce
 * @since   1.0.0
 */

namespace CFWC;

// Prevent direct file access.
defined( 'ABSPATH' ) || exit;

/**
 * Settings class.
 *
 * Manages plugin settings stored in WordPress options. Provides
 * methods for retrieving, validating, and updating settings.
 *
 * @since 1.0.0
 */
class Settings {

	/**
	 * Option name in database.
	 *
	 * @var string
	 */
	const OPTION_NAME = 'cfwc_settings';

	/**
	 * Cached settings array.
	 *
	 * @var array|null
	 */
	private $settings = null;

	/**
	 * Default settings values.
	 *
	 * @var array
	 */
	private $defaults = array(
		'provider'              => '',
		'site_key'              => '',
		'secret_key'            => '',
		'theme'                 => 'auto',
		'size'                  => 'normal',
		'score_threshold'       => 0.5,
		'forms'                 => array(),
		'whitelist_logged_in'   => 'no',
		'whitelist_roles'       => array(),
		'whitelist_ips'         => '',
		'blocklist_ips'         => '',
		'enable_honeypot'       => 'no',
		'honeypot_min_time'     => 3,
		'enable_rate_limiting'  => 'no',
		'rate_limit_requests'   => 5,
		'rate_limit_lockout'    => 15,
		'rate_limit_window'     => 60,
		'enable_debug_logging'       => 'no',
		'failsafe_mode'              => 'honeypot',
		'delete_data_on_uninstall'   => 'no',
	);

	/**
	 * List of supported forms with their labels.
	 *
	 * @var array|null
	 */
	private $supported_forms = null;

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		// Supported forms are lazy-loaded to avoid early translation issues.
	}

	/**
	 * Get the list of supported forms.
	 *
	 * Lazy-loads the form definitions to avoid loading translations
	 * too early (before init hook).
	 *
	 * @since 1.0.0
	 * @return array Array of supported forms.
	 */
	public function get_supported_forms() {
		if ( null === $this->supported_forms ) {
			$this->init_supported_forms();
		}
		return $this->supported_forms;
	}

	/**
	 * Initialize the list of supported forms.
	 *
	 * Defines all forms that can be protected with CAPTCHA,
	 * organized by category for easy display in the admin.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private function init_supported_forms() {
		$this->supported_forms = array(
			'wordpress' => array(
				'label' => __( 'WordPress Forms', 'captcha-for-woocommerce' ),
				'forms' => array(
					'wp_login'         => __( 'Login Form', 'captcha-for-woocommerce' ),
					'wp_register'      => __( 'Registration Form', 'captcha-for-woocommerce' ),
					'wp_lost_password' => __( 'Lost Password Form', 'captcha-for-woocommerce' ),
					'wp_comment'       => __( 'Comment Form', 'captcha-for-woocommerce' ),
				),
			),
			'woocommerce' => array(
				'label' => __( 'WooCommerce Forms', 'captcha-for-woocommerce' ),
				'forms' => array(
					'wc_login'            => __( 'My Account Login', 'captcha-for-woocommerce' ),
					'wc_register'         => __( 'My Account Registration', 'captcha-for-woocommerce' ),
					'wc_lost_password'    => __( 'My Account Lost Password', 'captcha-for-woocommerce' ),
					'wc_checkout_classic' => __( 'Checkout (Classic)', 'captcha-for-woocommerce' ),
					'wc_checkout_block'   => __( 'Checkout (Block)', 'captcha-for-woocommerce' ),
					'wc_pay_order'        => __( 'Pay for Order', 'captcha-for-woocommerce' ),
				),
			),
			'extensions' => array(
				'label' => __( 'WooCommerce Extensions', 'captcha-for-woocommerce' ),
				'forms' => array(
					'wcpv_registration'    => __( 'Product Vendors Registration', 'captcha-for-woocommerce' ),
					'wc_subscriptions'     => __( 'Subscriptions Checkout', 'captcha-for-woocommerce' ),
					'wc_memberships'       => __( 'Memberships Registration', 'captcha-for-woocommerce' ),
				),
			),
		);

		/**
		 * Filter supported forms.
		 *
		 * Allows developers to add custom forms to the list of
		 * supported forms that can be protected with CAPTCHA.
		 *
		 * @since 1.0.0
		 * @param array $supported_forms The array of supported forms.
		 */
		$this->supported_forms = apply_filters( 'cfwc_supported_forms', $this->supported_forms );
	}

	/**
	 * Get all settings.
	 *
	 * Retrieves all plugin settings from the database, merging
	 * with defaults for any missing values.
	 *
	 * @since 1.0.0
	 * @return array All plugin settings.
	 */
	public function get_all() {
		if ( is_null( $this->settings ) ) {
			$saved_settings = get_option( self::OPTION_NAME, array() );
			$this->settings = wp_parse_args( $saved_settings, $this->defaults );
		}

		return $this->settings;
	}

	/**
	 * Get a specific setting value.
	 *
	 * Retrieves a single setting value by key. Returns the default
	 * if the setting doesn't exist.
	 *
	 * @since 1.0.0
	 * @param string $key     The setting key to retrieve.
	 * @param mixed  $default Optional. Default value if not set.
	 * @return mixed The setting value.
	 */
	public function get( $key, $default = null ) {
		$settings = $this->get_all();

		if ( isset( $settings[ $key ] ) ) {
			return $settings[ $key ];
		}

		if ( ! is_null( $default ) ) {
			return $default;
		}

		return isset( $this->defaults[ $key ] ) ? $this->defaults[ $key ] : null;
	}

	/**
	 * Update a specific setting.
	 *
	 * Updates a single setting value in the database.
	 *
	 * @since 1.0.0
	 * @param string $key   The setting key to update.
	 * @param mixed  $value The new value.
	 * @return bool True if updated successfully.
	 */
	public function set( $key, $value ) {
		$settings         = $this->get_all();
		$settings[ $key ] = $value;

		// Clear cache.
		$this->settings = null;

		return update_option( self::OPTION_NAME, $settings );
	}

	/**
	 * Update all settings.
	 *
	 * Saves the entire settings array to the database.
	 *
	 * @since 1.0.0
	 * @param array $settings The settings array to save.
	 * @return bool True if updated successfully.
	 */
	public function save( $settings ) {
		// Clear cache.
		$this->settings = null;

		return update_option( self::OPTION_NAME, $settings );
	}

	/**
	 * Check if a specific form is enabled.
	 *
	 * Determines if CAPTCHA protection is enabled for the
	 * specified form type.
	 *
	 * @since 1.0.0
	 * @param string $form_type The form identifier.
	 * @return bool True if the form is enabled.
	 */
	public function is_form_enabled( $form_type ) {
		$forms = $this->get( 'forms', array() );

		/**
		 * Filter whether a specific form is enabled.
		 *
		 * @since 1.0.0
		 * @param bool   $enabled   Whether the form is enabled.
		 * @param string $form_type The form identifier.
		 */
		return apply_filters( 'cfwc_form_enabled', in_array( $form_type, $forms, true ), $form_type );
	}

	/**
	 * Get flat list of form IDs.
	 *
	 * Returns a simple array of all form IDs without categories.
	 *
	 * @since 1.0.0
	 * @return array Array of form IDs.
	 */
	public function get_form_ids() {
		$form_ids       = array();
		$supported      = $this->get_supported_forms();

		foreach ( $supported as $category ) {
			$form_ids = array_merge( $form_ids, array_keys( $category['forms'] ) );
		}

		return $form_ids;
	}

	/**
	 * Check if provider is configured.
	 *
	 * Determines if the selected CAPTCHA provider has the required
	 * API keys configured.
	 *
	 * @since 1.0.0
	 * @return bool True if provider is fully configured.
	 */
	public function is_provider_configured() {
		$provider = $this->get( 'provider' );

		// Honeypot doesn't require API keys.
		if ( 'honeypot' === $provider ) {
			return true;
		}

		// Other providers require site and secret keys.
		$site_key   = $this->get( 'site_key' );
		$secret_key = $this->get( 'secret_key' );

		return ! empty( $provider ) && ! empty( $site_key ) && ! empty( $secret_key );
	}

	/**
	 * Get default settings.
	 *
	 * Returns the array of default setting values.
	 *
	 * @since 1.0.0
	 * @return array Default settings.
	 */
	public function get_defaults() {
		return $this->defaults;
	}

	/**
	 * Reset settings to defaults.
	 *
	 * Removes all custom settings and restores defaults.
	 *
	 * @since 1.0.0
	 * @return bool True if reset successfully.
	 */
	public function reset() {
		$this->settings = null;
		return delete_option( self::OPTION_NAME );
	}

	/**
	 * Export settings as JSON.
	 *
	 * Returns current settings as a JSON string for export.
	 *
	 * @since 1.0.0
	 * @return string JSON encoded settings.
	 */
	public function export() {
		$settings = $this->get_all();

		// Remove sensitive data from export.
		unset( $settings['secret_key'] );

		return wp_json_encode( $settings, JSON_PRETTY_PRINT );
	}

	/**
	 * Import settings from JSON.
	 *
	 * Imports settings from a JSON string, validating the data.
	 *
	 * @since 1.0.0
	 * @param string $json The JSON string to import.
	 * @return bool|\WP_Error True on success, WP_Error on failure.
	 */
	public function import( $json ) {
		$settings = json_decode( $json, true );

		if ( json_last_error() !== JSON_ERROR_NONE ) {
			return new \WP_Error(
				'cfwc_import_error',
				__( 'Invalid JSON format.', 'captcha-for-woocommerce' )
			);
		}

		// Validate settings structure.
		if ( ! is_array( $settings ) ) {
			return new \WP_Error(
				'cfwc_import_error',
				__( 'Invalid settings format.', 'captcha-for-woocommerce' )
			);
		}

		// Merge with existing settings (preserves secret key).
		$current  = $this->get_all();
		$settings = wp_parse_args( $settings, $current );

		return $this->save( $settings );
	}
}
