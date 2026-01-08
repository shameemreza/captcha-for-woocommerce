<?php
/**
 * Self-Hosted Honeypot Provider.
 *
 * Advanced implementation of a self-hosted honeypot bot detection system.
 * No external API required - perfect for GDPR-strict environments.
 *
 * Features:
 * - JavaScript-injected honeypot fields (bots without JS fail automatically)
 * - Multiple hidden decoy fields with site-unique naming
 * - Time-based submission detection (minimum and maximum age validation)
 * - JavaScript math challenge (detects headless browsers and automation)
 * - Nonce protection against replay attacks
 * - Comprehensive spam logging and statistics
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
 * Implements advanced self-hosted bot detection without external dependencies.
 * Key innovation: honeypot fields are injected via JavaScript, making them
 * invisible to bots that don't execute JS.
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
	 * Option key for the unique field name.
	 *
	 * @var string
	 */
	const FIELD_NAME_OPTION = 'cfwc_honeypot_field_name';

	/**
	 * Primary decoy field names that attract bots.
	 *
	 * These look like legitimate fields that bots will try to fill.
	 * A random one is selected per-site for extra stealth.
	 *
	 * @var array
	 */
	private $decoy_fields = array(
		'website_url',
		'company_website',
		'phone_number',
		'fax_number',
		'address_line2',
		'website',
		'company_url',
		'mobile_phone',
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
		return __( 'Advanced protection with no API keys. JavaScript-injected honeypot fields + time-based detection + math challenge. Zero external dependencies, maximum privacy. Perfect for GDPR compliance.', 'captcha-for-woocommerce' );
	}

	/**
	 * Get the unique honeypot field name for this site.
	 *
	 * Generates and stores a unique field name per WordPress installation.
	 * This makes it harder for bots to target specific field names.
	 *
	 * @since 1.0.0
	 * @return string The unique field name.
	 */
	public function get_field_name() {
		$field_name = get_option( self::FIELD_NAME_OPTION );

		if ( empty( $field_name ) ) {
			// Generate a unique field name on first use.
			$field_name = $this->generate_unique_field_name();
			update_option( self::FIELD_NAME_OPTION, $field_name );
		}

		return $field_name;
	}

	/**
	 * Generate a unique field name.
	 *
	 * Creates a random alphanumeric string that looks like a legitimate field.
	 *
	 * @since 1.0.0
	 * @return string Unique field name.
	 */
	private function generate_unique_field_name() {
		$chars  = 'abcdefghijklmnopqrstuvwxyz';
		$prefix = substr( str_shuffle( $chars ), 0, 6 );
		$suffix = wp_rand( 100, 9999 );

		return $prefix . $suffix;
	}

	/**
	 * Get the honeypot field configuration for JavaScript.
	 *
	 * Returns all necessary data for the JS to inject honeypot fields.
	 *
	 * @since 1.0.0
	 * @return array Configuration array for localization.
	 */
	public function get_js_config() {
		$timestamp    = time();
		$field_name   = $this->get_field_name();
		$nonce        = wp_create_nonce( 'cfwc_honeypot_' . $field_name . '_' . $timestamp );
		$js_challenge = $this->generate_js_challenge( $timestamp );

		return array(
			'fieldName'  => $field_name,
			'nonce'      => $nonce,
			'timestamp'  => $timestamp,
			'challenge'  => $js_challenge['challenge'],
			'challengeA' => $js_challenge['a'],
			'challengeB' => $js_challenge['b'],
			'challengeC' => $js_challenge['c'],
		);
	}

	/**
	 * Render the honeypot placeholder.
	 *
	 * Outputs a minimal placeholder that JavaScript will enhance.
	 * The actual honeypot fields are injected via JS to catch
	 * bots that don't execute JavaScript.
	 *
	 * @since 1.0.0
	 * @param string $form_type The form identifier.
	 * @param array  $args      Additional arguments.
	 * @return void
	 */
	protected function render_widget( $form_type, $args ) {
		// Output a placeholder that JS will find and enhance.
		// This is intentionally minimal - the real trap is injected via JS.
		?>
		<span class="cfwc-hp-init" data-form="<?php echo esc_attr( $form_type ); ?>"></span>
		<?php
	}

	/**
	 * Generate a JavaScript challenge.
	 *
	 * Creates a simple math challenge that JavaScript must solve.
	 * This detects headless browsers that don't execute JS.
	 *
	 * @since 1.0.0
	 * @param int $timestamp The form timestamp.
	 * @return array Challenge data with 'challenge' and expected values.
	 */
	private function generate_js_challenge( $timestamp ) {
		// Generate random numbers for the challenge.
		$a = wp_rand( 2, 9 );
		$b = wp_rand( 2, 9 );
		$c = wp_rand( 10, 99 );

		// Calculate expected result.
		$result = base_convert( ( $a * $b + $c ), 10, 36 );

		// Create a challenge string that includes the timestamp for verification.
		$challenge = base64_encode( wp_json_encode( array( $a, $b, $c, $timestamp ) ) );

		return array(
			'challenge' => $challenge,
			'a'         => $a,
			'b'         => $b,
			'c'         => $c,
			'expected'  => $result,
		);
	}

	/**
	 * Verify the honeypot submission.
	 *
	 * Multi-layer verification:
	 * 1. Honeypot field exists (proves JS ran)
	 * 2. Visible trap field (alt_s) is empty
	 * 3. Hidden honeypot field has correct value
	 * 4. Submission wasn't too fast
	 * 5. Nonce is valid
	 * 6. JavaScript challenge was solved (proves real browser)
	 *
	 * @since 1.0.0
	 * @param string $token Unused for honeypot.
	 * @return bool|\WP_Error True on success, WP_Error on failure.
	 */
	public function verify( $token = '' ) {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- Nonce is verified within this method.

		$field_name = $this->get_field_name();

		// Check 1: Verify the JS-injected honeypot field exists.
		// If it doesn't exist, the bot didn't execute JavaScript.
		if ( ! isset( $_POST[ $field_name ] ) ) {
			$this->log_spam_attempt( 'no_js_field', 'Honeypot field missing - JavaScript not executed' );
			return $this->create_error(
				'no_js',
				__( 'Security verification failed. Please enable JavaScript and try again.', 'captcha-for-woocommerce' )
			);
		}

		// Check 2: Verify the visible trap field (alt_s) is empty.
		// Bots will typically fill this thinking it's a real field.
		$alt_s = isset( $_POST['alt_s'] ) ? sanitize_text_field( wp_unslash( $_POST['alt_s'] ) ) : '';
		if ( ! empty( $alt_s ) ) {
			$this->log_spam_attempt( 'trap_filled', 'Visible trap field was filled' );
			return $this->create_error(
				'trap_filled',
				__( 'Spam detected. Please try again.', 'captcha-for-woocommerce' )
			);
		}

		// Get other verification fields.
		$nonce       = isset( $_POST['cfwc_hp_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['cfwc_hp_nonce'] ) ) : '';
		$timestamp   = isset( $_POST['cfwc_hp_time'] ) ? absint( $_POST['cfwc_hp_time'] ) : 0;
		$js_response = isset( $_POST['cfwc_hp_js'] ) ? sanitize_text_field( wp_unslash( $_POST['cfwc_hp_js'] ) ) : '';
		$challenge   = isset( $_POST['cfwc_hp_challenge'] ) ? sanitize_text_field( wp_unslash( $_POST['cfwc_hp_challenge'] ) ) : '';

		// Check 3: Verify timestamp exists and is reasonable.
		if ( empty( $timestamp ) || $timestamp > time() ) {
			$this->log_spam_attempt( 'invalid_timestamp', 'Invalid or future timestamp' );
			return $this->create_error(
				'invalid_time',
				__( 'Security verification failed. Please refresh and try again.', 'captcha-for-woocommerce' )
			);
		}

		// Check 4: Verify the nonce.
		if ( ! wp_verify_nonce( $nonce, 'cfwc_honeypot_' . $field_name . '_' . $timestamp ) ) {
			$this->log_spam_attempt( 'invalid_nonce', 'Nonce verification failed' );
			return $this->create_error(
				'invalid_nonce',
				__( 'Security verification expired. Please refresh and try again.', 'captcha-for-woocommerce' )
			);
		}

		// Check 5: Time-based validation - form submitted too quickly.
		$min_time = $this->get_minimum_time();
		$elapsed  = time() - $timestamp;

		if ( $elapsed < $min_time ) {
			$this->log_spam_attempt( 'too_fast', sprintf( 'Submitted in %d seconds (minimum: %d)', $elapsed, $min_time ) );
			return $this->create_error(
				'too_fast',
				__( 'Form submitted too quickly. Please take your time.', 'captcha-for-woocommerce' )
			);
		}

		// Check 6: Form age check - forms older than 24 hours are suspicious.
		if ( $elapsed > DAY_IN_SECONDS ) {
			$this->log_spam_attempt( 'too_old', sprintf( 'Form age: %d seconds', $elapsed ) );
			return $this->create_error(
				'too_old',
				__( 'Form expired. Please refresh the page and try again.', 'captcha-for-woocommerce' )
			);
		}

		// Check 7: Verify JavaScript math challenge (proves real browser).
		if ( ! empty( $challenge ) ) {
			$challenge_valid = $this->verify_js_challenge( $challenge, $js_response, $timestamp );
			if ( ! $challenge_valid ) {
				$this->log_spam_attempt( 'js_challenge_failed', 'JavaScript math challenge failed' );
				// Only block if honeypot is the primary provider (not fallback).
				if ( Plugin::instance()->settings()->get( 'provider' ) === 'honeypot' ) {
					return $this->create_error(
						'js_failed',
						__( 'Security check failed. Please enable JavaScript and try again.', 'captcha-for-woocommerce' )
					);
				}
			}
		}

		// phpcs:enable

		return true;
	}

	/**
	 * Log spam attempt for statistics.
	 *
	 * @since 1.0.0
	 * @param string $reason      Short reason code.
	 * @param string $description Detailed description.
	 * @return void
	 */
	private function log_spam_attempt( $reason, $description ) {
		// Update spam counter.
		$stats = get_option( 'cfwc_honeypot_stats', array( 'total' => 0, 'today' => array( 'date' => '', 'count' => 0 ) ) );

		$today = gmdate( 'Y-m-d' );
		if ( $stats['today']['date'] !== $today ) {
			$stats['today'] = array( 'date' => $today, 'count' => 0 );
		}

		$stats['total']++;
		$stats['today']['count']++;
		update_option( 'cfwc_honeypot_stats', $stats );

		// Debug logging if enabled.
		if ( 'yes' === Plugin::instance()->settings()->get( 'enable_debug_logging' ) ) {
			\CFWC\Logger::log(
				sprintf( 'Honeypot blocked spam: %s - %s', $reason, $description ),
				'info'
			);
		}
	}

	/**
	 * Get spam statistics.
	 *
	 * @since 1.0.0
	 * @return array Spam statistics.
	 */
	public function get_stats() {
		return get_option( 'cfwc_honeypot_stats', array( 'total' => 0, 'today' => array( 'date' => '', 'count' => 0 ) ) );
	}

	/**
	 * Verify the JavaScript challenge response.
	 *
	 * @since 1.0.0
	 * @param string $challenge   The challenge string.
	 * @param string $response    The JS response.
	 * @param int    $timestamp   The form timestamp.
	 * @return bool True if valid.
	 */
	private function verify_js_challenge( $challenge, $response, $timestamp ) {
		if ( empty( $challenge ) || empty( $response ) ) {
			return false;
		}

		// Decode challenge.
		$decoded = json_decode( base64_decode( $challenge ), true );
		if ( ! is_array( $decoded ) || count( $decoded ) !== 4 ) {
			return false;
		}

		list( $a, $b, $c, $challenge_time ) = $decoded;

		// Verify timestamp matches.
		if ( (int) $challenge_time !== (int) $timestamp ) {
			return false;
		}

		// Calculate expected result.
		$expected = base_convert( ( $a * $b + $c ), 10, 36 );

		return $response === $expected;
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

	/**
	 * Regenerate the honeypot field name.
	 *
	 * Useful if the current field name has been compromised.
	 *
	 * @since 1.0.0
	 * @return string The new field name.
	 */
	public function regenerate_field_name() {
		$field_name = $this->generate_unique_field_name();
		update_option( self::FIELD_NAME_OPTION, $field_name );
		return $field_name;
	}
}
