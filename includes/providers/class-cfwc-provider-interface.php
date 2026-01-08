<?php
/**
 * Provider Interface.
 *
 * Defines the contract that all CAPTCHA providers must implement.
 * This ensures consistent behavior across different providers.
 *
 * @package Captcha_For_WooCommerce
 * @since   1.0.0
 */

namespace CFWC\Providers;

// Prevent direct file access.
defined( 'ABSPATH' ) || exit;

/**
 * Provider Interface.
 *
 * All CAPTCHA provider implementations must implement this interface
 * to ensure they can be used interchangeably within the plugin.
 *
 * @since 1.0.0
 */
interface Provider_Interface {

	/**
	 * Get the provider identifier.
	 *
	 * Returns a unique string identifier for this provider.
	 * Used for settings storage and provider selection.
	 *
	 * @since 1.0.0
	 * @return string Provider identifier (e.g., 'turnstile', 'recaptcha_v2').
	 */
	public function get_id();

	/**
	 * Get the provider display name.
	 *
	 * Returns a human-readable name for display in the admin UI.
	 *
	 * @since 1.0.0
	 * @return string Provider name (e.g., 'Cloudflare Turnstile').
	 */
	public function get_name();

	/**
	 * Get the provider description.
	 *
	 * Returns a brief description of the provider for the settings page.
	 *
	 * @since 1.0.0
	 * @return string Provider description.
	 */
	public function get_description();

	/**
	 * Check if the provider requires API keys.
	 *
	 * Some providers (like honeypot) don't need external API keys.
	 *
	 * @since 1.0.0
	 * @return bool True if API keys are required.
	 */
	public function requires_api_keys();

	/**
	 * Get the URL for obtaining API keys.
	 *
	 * Returns the URL where users can get their API keys for this provider.
	 *
	 * @since 1.0.0
	 * @return string URL to the provider's key management page.
	 */
	public function get_api_key_url();

	/**
	 * Render the CAPTCHA widget.
	 *
	 * Outputs the HTML for the CAPTCHA widget in the form.
	 *
	 * @since 1.0.0
	 * @param string $form_type The form identifier where the widget is displayed.
	 * @param array  $args      Optional. Additional rendering arguments.
	 * @return void
	 */
	public function render( $form_type, $args = array() );

	/**
	 * Verify the CAPTCHA response.
	 *
	 * Validates the CAPTCHA response token submitted with the form.
	 *
	 * @since 1.0.0
	 * @param string $token Optional. The response token. If empty, reads from POST.
	 * @return bool|\WP_Error True on success, WP_Error on failure.
	 */
	public function verify( $token = '' );

	/**
	 * Get the token field name.
	 *
	 * Returns the name of the form field containing the CAPTCHA response token.
	 *
	 * @since 1.0.0
	 * @return string The field name.
	 */
	public function get_token_field_name();

	/**
	 * Test the API connection.
	 *
	 * Validates that the configured API keys work correctly.
	 *
	 * @since 1.0.0
	 * @param string $site_key   The site key to test.
	 * @param string $secret_key The secret key to test.
	 * @return bool|\WP_Error True on success, WP_Error on failure.
	 */
	public function test_connection( $site_key, $secret_key );
}
