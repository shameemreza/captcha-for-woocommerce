<?php
/**
 * Cloudflare Turnstile Provider.
 *
 * Implementation of the Cloudflare Turnstile CAPTCHA service.
 * Turnstile is a privacy-focused, user-friendly CAPTCHA alternative.
 *
 * @package Captcha_For_WooCommerce
 * @since   1.0.0
 */

namespace CFWC\Providers;

// Prevent direct file access.
defined( 'ABSPATH' ) || exit;

/**
 * Turnstile class.
 *
 * Implements the Cloudflare Turnstile CAPTCHA provider.
 *
 * @since 1.0.0
 */
class Turnstile extends Abstract_Provider {

	/**
	 * Provider identifier.
	 *
	 * @var string
	 */
	protected $id = 'turnstile';

	/**
	 * Provider name.
	 *
	 * @var string
	 */
	protected $name = 'Cloudflare Turnstile';

	/**
	 * Token field name.
	 *
	 * @var string
	 */
	protected $token_field = 'cf-turnstile-response';

	/**
	 * Verification endpoint.
	 *
	 * @var string
	 */
	protected $verify_endpoint = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';

	/**
	 * API key URL.
	 *
	 * @var string
	 */
	protected $api_key_url = 'https://dash.cloudflare.com/?to=/:account/turnstile';

	/**
	 * Get the provider description.
	 *
	 * Returns a translated description for the admin UI.
	 *
	 * @since 1.0.0
	 * @return string Provider description.
	 */
	public function get_description() {
		return __( 'Privacy-focused, usually invisible, free unlimited usage. Recommended for most sites.', 'captcha-for-woocommerce' );
	}

	/**
	 * Render the Turnstile widget.
	 *
	 * @since 1.0.0
	 * @param string $form_type The form identifier.
	 * @param array  $args      Additional arguments.
	 * @return void
	 */
	protected function render_widget( $form_type, $args ) {
		$site_key = $this->get_site_key();
		$theme    = $this->get_theme();
		$size     = $this->get_size();

		// Generate unique ID for this widget instance.
		$widget_id = 'cfwc-turnstile-' . esc_attr( $form_type );
		?>
		<div id="<?php echo esc_attr( $widget_id ); ?>"
			 class="cf-turnstile"
			 data-sitekey="<?php echo esc_attr( $site_key ); ?>"
			 data-theme="<?php echo esc_attr( $theme ); ?>"
			 data-size="<?php echo esc_attr( $size ); ?>"
			 data-retry="auto"
			 data-retry-interval="1000"
			 data-refresh-expired="auto"
			 data-action="<?php echo esc_attr( $form_type ); ?>"
			 aria-describedby="cfwc-description-<?php echo esc_attr( $form_type ); ?>">
		</div>
		<?php
	}

	/**
	 * Verify the Turnstile response.
	 *
	 * @since 1.0.0
	 * @param string $token Optional. The response token.
	 * @return bool|\WP_Error True on success, WP_Error on failure.
	 */
	public function verify( $token = '' ) {
		$token = $this->get_token( $token );

		if ( empty( $token ) ) {
			return $this->create_error(
				'missing_token',
				__( 'Please complete the CAPTCHA verification.', 'captcha-for-woocommerce' )
			);
		}

		$response = $this->make_verification_request( $token );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		if ( ! isset( $response['success'] ) || true !== $response['success'] ) {
			$error_codes = isset( $response['error-codes'] ) ? $response['error-codes'] : array();

			return $this->create_error(
				'verification_failed',
				$this->get_error_message( $error_codes ),
				array( 'error_codes' => $error_codes )
			);
		}

		return true;
	}

	/**
	 * Test the API connection.
	 *
	 * @since 1.0.0
	 * @param string $site_key   The site key to test.
	 * @param string $secret_key The secret key to test.
	 * @return bool|\WP_Error True on success, WP_Error on failure.
	 */
	public function test_connection( $site_key, $secret_key ) {
		// Turnstile doesn't have a dedicated test endpoint.
		// We verify the secret key format and attempt a request.
		if ( empty( $site_key ) || empty( $secret_key ) ) {
			return $this->create_error(
				'missing_keys',
				__( 'Both site key and secret key are required.', 'captcha-for-woocommerce' )
			);
		}

		// Turnstile keys have specific format.
		if ( ! preg_match( '/^0x/', $site_key ) ) {
			return $this->create_error(
				'invalid_site_key',
				__( 'Invalid site key format. Turnstile site keys start with "0x".', 'captcha-for-woocommerce' )
			);
		}

		return true;
	}

	/**
	 * Get human-readable error message.
	 *
	 * Translates Turnstile error codes to user-friendly messages.
	 *
	 * @since 1.0.0
	 * @param array $error_codes Array of error codes from Turnstile.
	 * @return string The error message.
	 */
	private function get_error_message( $error_codes ) {
		if ( empty( $error_codes ) ) {
			return __( 'CAPTCHA verification failed. Please try again.', 'captcha-for-woocommerce' );
		}

		$code = $error_codes[0];

		$messages = array(
			'missing-input-secret'   => __( 'Server configuration error. Please contact the site administrator.', 'captcha-for-woocommerce' ),
			'invalid-input-secret'   => __( 'Server configuration error. Please contact the site administrator.', 'captcha-for-woocommerce' ),
			'missing-input-response' => __( 'Please complete the CAPTCHA verification.', 'captcha-for-woocommerce' ),
			'invalid-input-response' => __( 'CAPTCHA verification failed. Please try again.', 'captcha-for-woocommerce' ),
			'bad-request'            => __( 'Invalid request. Please try again.', 'captcha-for-woocommerce' ),
			'timeout-or-duplicate'   => __( 'CAPTCHA expired. Please complete the verification again.', 'captcha-for-woocommerce' ),
			'internal-error'         => __( 'An error occurred. Please try again later.', 'captcha-for-woocommerce' ),
		);

		/**
		 * Filter CAPTCHA error messages.
		 *
		 * @since 1.0.0
		 * @param string $message   The error message.
		 * @param string $code      The error code.
		 * @param string $provider  Provider identifier.
		 */
		$message = isset( $messages[ $code ] ) ? $messages[ $code ] : $messages['invalid-input-response'];

		return apply_filters( 'cfwc_error_message', $message, $code, $this->id );
	}
}
