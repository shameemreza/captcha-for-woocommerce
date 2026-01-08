<?php
/**
 * Google reCAPTCHA v3 Provider.
 *
 * Implementation of Google reCAPTCHA v3 (invisible) CAPTCHA service.
 * Runs in the background and scores user behavior without interruption.
 *
 * @package Captcha_For_WooCommerce
 * @since   1.0.0
 */

namespace CFWC\Providers;

use CFWC\Plugin;

// Prevent direct file access.
defined( 'ABSPATH' ) || exit;

/**
 * Recaptcha_V3 class.
 *
 * Implements Google reCAPTCHA v3 invisible provider.
 *
 * @since 1.0.0
 */
class Recaptcha_V3 extends Abstract_Provider {

	/**
	 * Provider identifier.
	 *
	 * @var string
	 */
	protected $id = 'recaptcha_v3';

	/**
	 * Provider name.
	 *
	 * @var string
	 */
	protected $name = 'Google reCAPTCHA v3';

	/**
	 * Token field name.
	 *
	 * @var string
	 */
	protected $token_field = 'g-recaptcha-response';

	/**
	 * Verification endpoint.
	 *
	 * @var string
	 */
	protected $verify_endpoint = 'https://www.google.com/recaptcha/api/siteverify';

	/**
	 * API key URL.
	 *
	 * @var string
	 */
	protected $api_key_url = 'https://www.google.com/recaptcha/admin/create';

	/**
	 * Get the provider description.
	 *
	 * Returns a translated description for the admin UI.
	 *
	 * @since 1.0.0
	 * @return string Provider description.
	 */
	public function get_description() {
		return __( 'Score-based, runs invisibly in background. Best for seamless user experience.', 'captcha-for-woocommerce' );
	}

	/**
	 * Render the reCAPTCHA v3 widget.
	 *
	 * v3 is invisible, so we render a hidden input that will
	 * be populated with the token via JavaScript.
	 *
	 * @since 1.0.0
	 * @param string $form_type The form identifier.
	 * @param array  $args      Additional arguments.
	 * @return void
	 */
	protected function render_widget( $form_type, $args ) {
		$site_key  = $this->get_site_key();
		$widget_id = 'cfwc-recaptcha-v3-' . esc_attr( $form_type );
		?>
		<div id="<?php echo esc_attr( $widget_id ); ?>"
			 class="cfwc-recaptcha-v3"
			 data-sitekey="<?php echo esc_attr( $site_key ); ?>"
			 data-action="<?php echo esc_attr( $form_type ); ?>"
			 aria-hidden="true">
			<input type="hidden"
				   name="<?php echo esc_attr( $this->token_field ); ?>"
				   id="cfwc-recaptcha-token-<?php echo esc_attr( $form_type ); ?>"
				   value="">
		</div>
		<?php
	}

	/**
	 * Verify the reCAPTCHA v3 response.
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
				__( 'CAPTCHA verification token missing. Please refresh and try again.', 'captcha-for-woocommerce' )
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

		// Check the score for v3.
		$threshold = $this->get_score_threshold();
		$score     = isset( $response['score'] ) ? floatval( $response['score'] ) : 0;

		if ( $score < $threshold ) {
			return $this->create_error(
				'low_score',
				__( 'Verification failed. Please try again.', 'captcha-for-woocommerce' ),
				array( 'score' => $score )
			);
		}

		return true;
	}

	/**
	 * Get the score threshold from settings.
	 *
	 * @since 1.0.0
	 * @return float The score threshold (0.0 to 1.0).
	 */
	private function get_score_threshold() {
		$threshold = Plugin::instance()->settings()->get( 'score_threshold', 0.5 );

		/**
		 * Filter the reCAPTCHA v3 score threshold.
		 *
		 * @since 1.0.0
		 * @param float $threshold The score threshold.
		 */
		return (float) apply_filters( 'cfwc_recaptcha_v3_threshold', $threshold );
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
		if ( empty( $site_key ) || empty( $secret_key ) ) {
			return $this->create_error(
				'missing_keys',
				__( 'Both site key and secret key are required.', 'captcha-for-woocommerce' )
			);
		}

		// Basic format validation.
		if ( strlen( $site_key ) < 20 || strlen( $secret_key ) < 20 ) {
			return $this->create_error(
				'invalid_keys',
				__( 'Invalid key format. Please check your API keys.', 'captcha-for-woocommerce' )
			);
		}

		return true;
	}

	/**
	 * Get human-readable error message.
	 *
	 * @since 1.0.0
	 * @param array $error_codes Array of error codes.
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
			'missing-input-response' => __( 'Verification token missing. Please refresh and try again.', 'captcha-for-woocommerce' ),
			'invalid-input-response' => __( 'CAPTCHA verification failed. Please try again.', 'captcha-for-woocommerce' ),
			'bad-request'            => __( 'Invalid request. Please try again.', 'captcha-for-woocommerce' ),
			'timeout-or-duplicate'   => __( 'CAPTCHA expired. Please refresh and try again.', 'captcha-for-woocommerce' ),
		);

		$message = isset( $messages[ $code ] ) ? $messages[ $code ] : $messages['invalid-input-response'];

		return apply_filters( 'cfwc_error_message', $message, $code, $this->id );
	}
}
