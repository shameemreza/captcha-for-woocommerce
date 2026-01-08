<?php
/**
 * Self-Hosted Honeypot Provider.
 *
 * Implementation of a self-hosted honeypot bot detection system.
 * No external API required - perfect for GDPR-strict environments.
 *
 * @package Captcha_For_WooCommerce
 * @since   1.0.0
 */

namespace CFWC\Providers;

use CFWC\Plugin;

// Prevent direct file access.
defined( 'ABSPATH' ) || exit;

/**
 * Honeypot class.
 *
 * Implements self-hosted bot detection without external dependencies.
 * Uses hidden fields that bots typically fill out, combined with
 * timing analysis and JavaScript verification.
 *
 * @since 1.0.0
 */
class Honeypot extends Abstract_Provider {

	/**
	 * Provider identifier.
	 *
	 * @var string
	 */
	protected $id = 'honeypot';

	/**
	 * Provider name.
	 *
	 * @var string
	 */
	protected $name = 'Self-Hosted Honeypot';

	/**
	 * Whether API keys are required.
	 *
	 * @var bool
	 */
	protected $requires_keys = false;

	/**
	 * Token field name - used for the time check.
	 *
	 * @var string
	 */
	protected $token_field = 'cfwc_hp_token';

	/**
	 * Decoy field names that attract bots.
	 *
	 * These look like legitimate fields that bots will try to fill.
	 *
	 * @var array
	 */
	private $decoy_fields = array(
		'website_url',
		'company_website',
		'phone_number',
		'fax_number',
		'address_line2',
	);

	/**
	 * Get the provider description.
	 *
	 * Returns a translated description for the admin UI.
	 *
	 * @since 1.0.0
	 * @return string Provider description.
	 */
	public function get_description() {
		return __( 'No API keys required. Zero external dependencies, maximum privacy. Perfect for GDPR compliance.', 'captcha-for-woocommerce' );
	}

	/**
	 * Render the honeypot fields.
	 *
	 * Creates invisible fields that bots will try to fill, plus
	 * a timing check to detect automated submissions.
	 *
	 * @since 1.0.0
	 * @param string $form_type The form identifier.
	 * @param array  $args      Additional arguments.
	 * @return void
	 */
	protected function render_widget( $form_type, $args ) {
		// Select a random decoy field for this form.
		$field_name = $this->decoy_fields[ array_rand( $this->decoy_fields ) ];

		// Generate tokens for verification.
		$timestamp = time();
		$nonce     = wp_create_nonce( 'cfwc_honeypot_' . $field_name . '_' . $timestamp );

		// The container is hidden visually but accessible to bots.
		?>
		<div class="cfwc-hp-container" aria-hidden="true" style="position:absolute;top:-9999px;left:-9999px;opacity:0;visibility:hidden;pointer-events:none;">
			<label for="cfwc-hp-<?php echo esc_attr( $field_name ); ?>">
				<?php
				/* translators: Screen reader text for honeypot field */
				esc_html_e( 'Leave this field empty if you are human', 'captcha-for-woocommerce' );
				?>
			</label>
			<input type="text"
				   name="<?php echo esc_attr( $field_name ); ?>"
				   id="cfwc-hp-<?php echo esc_attr( $field_name ); ?>"
				   value=""
				   tabindex="-1"
				   autocomplete="new-password">
		</div>
		<input type="hidden" name="cfwc_hp_field" value="<?php echo esc_attr( $field_name ); ?>">
		<input type="hidden" name="cfwc_hp_nonce" value="<?php echo esc_attr( $nonce ); ?>">
		<input type="hidden" name="cfwc_hp_time" value="<?php echo esc_attr( $timestamp ); ?>">
		<?php
	}

	/**
	 * Verify the honeypot submission.
	 *
	 * Checks that:
	 * 1. The honeypot field is empty (bots typically fill it)
	 * 2. The submission wasn't too fast (humans take time to fill forms)
	 * 3. The nonce is valid (prevents replay attacks)
	 *
	 * @since 1.0.0
	 * @param string $token Unused for honeypot.
	 * @return bool|\WP_Error True on success, WP_Error on failure.
	 */
	public function verify( $token = '' ) {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- Nonce is verified within this method.

		// Get submitted values.
		$field_name = isset( $_POST['cfwc_hp_field'] ) ? sanitize_text_field( wp_unslash( $_POST['cfwc_hp_field'] ) ) : '';
		$nonce      = isset( $_POST['cfwc_hp_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['cfwc_hp_nonce'] ) ) : '';
		$timestamp  = isset( $_POST['cfwc_hp_time'] ) ? absint( $_POST['cfwc_hp_time'] ) : 0;

		// Verify the field name is one of our decoy fields.
		if ( ! in_array( $field_name, $this->decoy_fields, true ) ) {
			return $this->create_error(
				'invalid_field',
				__( 'Security verification failed. Please try again.', 'captcha-for-woocommerce' )
			);
		}

		// Verify the nonce.
		if ( ! wp_verify_nonce( $nonce, 'cfwc_honeypot_' . $field_name . '_' . $timestamp ) ) {
			return $this->create_error(
				'invalid_nonce',
				__( 'Security verification expired. Please refresh and try again.', 'captcha-for-woocommerce' )
			);
		}

		// Check if the honeypot field was filled (indicates a bot).
		$honeypot_value = isset( $_POST[ $field_name ] ) ? sanitize_text_field( wp_unslash( $_POST[ $field_name ] ) ) : '';
		if ( ! empty( $honeypot_value ) ) {
			// This is almost certainly a bot.
			return $this->create_error(
				'honeypot_filled',
				__( 'Spam detected. Please try again.', 'captcha-for-woocommerce' )
			);
		}

		// Check the time taken to submit the form.
		$min_time = $this->get_minimum_time();
		$elapsed  = time() - $timestamp;

		if ( $elapsed < $min_time ) {
			return $this->create_error(
				'too_fast',
				__( 'Form submitted too quickly. Please take your time.', 'captcha-for-woocommerce' )
			);
		}

		// phpcs:enable

		return true;
	}

	/**
	 * Get minimum time for form submission.
	 *
	 * @since 1.0.0
	 * @return int Minimum time in seconds.
	 */
	private function get_minimum_time() {
		$min_time = Plugin::instance()->settings()->get( 'honeypot_min_time', 3 );

		/**
		 * Filter the minimum time for honeypot verification.
		 *
		 * @since 1.0.0
		 * @param int $min_time Minimum time in seconds.
		 */
		return (int) apply_filters( 'cfwc_honeypot_min_time', $min_time );
	}

	/**
	 * Test the connection.
	 *
	 * Honeypot doesn't need API keys, so this always succeeds.
	 *
	 * @since 1.0.0
	 * @param string $site_key   Unused.
	 * @param string $secret_key Unused.
	 * @return bool Always true.
	 */
	public function test_connection( $site_key, $secret_key ) {
		return true;
	}
}
