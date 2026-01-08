<?php
/**
 * Google reCAPTCHA v2 Provider.
 *
 * Implementation of Google reCAPTCHA v2 (checkbox) CAPTCHA service.
 * The classic "I'm not a robot" checkbox that most users recognize.
 *
 * @package Captcha_For_WooCommerce
 * @since   1.0.0
 */

namespace CFWC\Providers;

// Prevent direct file access.
defined( 'ABSPATH' ) || exit;

/**
 * Recaptcha_V2 class.
 *
 * Implements Google reCAPTCHA v2 checkbox provider.
 *
 * @since 1.0.0
 */
class Recaptcha_V2 extends Abstract_Provider {

	/**
	 * Provider identifier.
	 *
	 * @var string
	 */
	protected $id = 'recaptcha_v2';

	/**
	 * Provider name.
	 *
	 * @var string
	 */
	protected $name = 'Google reCAPTCHA v2';

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
		return __( 'Classic "I\'m not a robot" checkbox. Most recognized, 1M free requests/month.', 'captcha-for-woocommerce' );
	}

	/**
	 * Render the reCAPTCHA v2 widget.
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

		// Adjust theme for auto setting.
		if ( 'auto' === $theme ) {
			$theme = 'light';
		}

		$widget_id = 'cfwc-recaptcha-' . esc_attr( $form_type );
		?>
		<div id="<?php echo esc_attr( $widget_id ); ?>"
			 class="g-recaptcha"
			 data-sitekey="<?php echo esc_attr( $site_key ); ?>"
			 data-theme="<?php echo esc_attr( $theme ); ?>"
			 data-size="<?php echo esc_attr( $size ); ?>"
			 data-callback="cfwRecaptchaCallback"
			 data-expired-callback="cfwRecaptchaExpired"
			 aria-describedby="cfwc-description-<?php echo esc_attr( $form_type ); ?>">
		</div>
		<?php
	}

	/**
	 * Verify the reCAPTCHA v2 response.
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
			'missing-input-response' => __( 'Please complete the CAPTCHA verification.', 'captcha-for-woocommerce' ),
			'invalid-input-response' => __( 'CAPTCHA verification failed. Please try again.', 'captcha-for-woocommerce' ),
			'bad-request'            => __( 'Invalid request. Please try again.', 'captcha-for-woocommerce' ),
			'timeout-or-duplicate'   => __( 'CAPTCHA expired. Please complete the verification again.', 'captcha-for-woocommerce' ),
		);

		$message = isset( $messages[ $code ] ) ? $messages[ $code ] : $messages['invalid-input-response'];

		return apply_filters( 'cfwc_error_message', $message, $code, $this->id );
	}
}
