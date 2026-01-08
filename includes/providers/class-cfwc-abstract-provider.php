<?php
/**
 * Abstract Provider.
 *
 * Base class for CAPTCHA providers. Implements common functionality
 * that is shared across all providers.
 *
 * @package Captcha_For_WooCommerce
 * @since   1.0.0
 */

namespace CFWC\Providers;

use CFWC\Plugin;

// Prevent direct file access.
defined( 'ABSPATH' ) || exit;

/**
 * Abstract Provider class.
 *
 * Provides base implementation for common provider functionality.
 * Individual providers extend this class and implement provider-specific logic.
 *
 * @since 1.0.0
 */
abstract class Abstract_Provider implements Provider_Interface {

	/**
	 * Provider identifier.
	 *
	 * @var string
	 */
	protected $id = '';

	/**
	 * Provider display name.
	 *
	 * @var string
	 */
	protected $name = '';

	/**
	 * Provider description.
	 *
	 * @var string
	 */
	protected $description = '';

	/**
	 * Whether API keys are required.
	 *
	 * @var bool
	 */
	protected $requires_keys = true;

	/**
	 * URL to get API keys.
	 *
	 * @var string
	 */
	protected $api_key_url = '';

	/**
	 * Token field name in form submission.
	 *
	 * @var string
	 */
	protected $token_field = '';

	/**
	 * Verification API endpoint.
	 *
	 * @var string
	 */
	protected $verify_endpoint = '';

	/**
	 * Get the provider identifier.
	 *
	 * @since 1.0.0
	 * @return string Provider identifier.
	 */
	public function get_id() {
		return $this->id;
	}

	/**
	 * Get the provider display name.
	 *
	 * @since 1.0.0
	 * @return string Provider name.
	 */
	public function get_name() {
		return $this->name;
	}

	/**
	 * Get the provider description.
	 *
	 * @since 1.0.0
	 * @return string Provider description.
	 */
	public function get_description() {
		return $this->description;
	}

	/**
	 * Check if API keys are required.
	 *
	 * @since 1.0.0
	 * @return bool True if keys are required.
	 */
	public function requires_api_keys() {
		return $this->requires_keys;
	}

	/**
	 * Get URL for API keys.
	 *
	 * @since 1.0.0
	 * @return string API key URL.
	 */
	public function get_api_key_url() {
		return $this->api_key_url;
	}

	/**
	 * Get the token field name.
	 *
	 * @since 1.0.0
	 * @return string Field name.
	 */
	public function get_token_field_name() {
		return $this->token_field;
	}

	/**
	 * Get the site key from settings.
	 *
	 * @since 1.0.0
	 * @return string Site key.
	 */
	protected function get_site_key() {
		return Plugin::instance()->settings()->get( 'site_key', '' );
	}

	/**
	 * Get the secret key from settings.
	 *
	 * @since 1.0.0
	 * @return string Secret key.
	 */
	protected function get_secret_key() {
		return Plugin::instance()->settings()->get( 'secret_key', '' );
	}

	/**
	 * Get widget theme setting.
	 *
	 * @since 1.0.0
	 * @return string Theme (light, dark, auto).
	 */
	protected function get_theme() {
		return Plugin::instance()->settings()->get( 'theme', 'auto' );
	}

	/**
	 * Get widget size setting.
	 *
	 * @since 1.0.0
	 * @return string Size (normal, compact).
	 */
	protected function get_size() {
		return Plugin::instance()->settings()->get( 'size', 'normal' );
	}

	/**
	 * Render the CAPTCHA widget container.
	 *
	 * Outputs the HTML wrapper for the CAPTCHA widget with proper
	 * accessibility attributes.
	 *
	 * @since 1.0.0
	 * @param string $form_type The form identifier.
	 * @param array  $args      Optional. Additional arguments.
	 * @return void
	 */
	public function render( $form_type, $args = array() ) {
		$defaults = array(
			'container_class' => 'cfwc-captcha-field',
			'container_id'    => 'cfwc-captcha-' . esc_attr( $form_type ),
		);

		$args = wp_parse_args( $args, $defaults );

		/**
		 * Filter the container class for the CAPTCHA widget.
		 *
		 * @since 1.0.0
		 * @param string $class     The container class.
		 * @param string $form_type The form identifier.
		 */
		$container_class = apply_filters( 'cfwc_widget_container_class', $args['container_class'], $form_type );
		?>
		<div class="<?php echo esc_attr( $container_class ); ?>"
			 role="group"
			 aria-labelledby="cfwc-label-<?php echo esc_attr( $form_type ); ?>">

			<label id="cfwc-label-<?php echo esc_attr( $form_type ); ?>" class="screen-reader-text">
				<?php esc_html_e( 'Security verification', 'captcha-for-woocommerce' ); ?>
			</label>

			<?php $this->render_widget( $form_type, $args ); ?>

			<p id="cfwc-description-<?php echo esc_attr( $form_type ); ?>" class="screen-reader-text">
				<?php esc_html_e( 'Please complete this security check to continue.', 'captcha-for-woocommerce' ); ?>
			</p>

		</div>
		<?php
	}

	/**
	 * Render the provider-specific widget.
	 *
	 * Must be implemented by each provider to output their specific widget HTML.
	 *
	 * @since 1.0.0
	 * @param string $form_type The form identifier.
	 * @param array  $args      Additional arguments.
	 * @return void
	 */
	abstract protected function render_widget( $form_type, $args );

	/**
	 * Get the CAPTCHA token from the request.
	 *
	 * Retrieves the response token from POST data.
	 *
	 * @since 1.0.0
	 * @param string $token Optional. Token to use instead of reading from POST.
	 * @return string The token value.
	 */
	protected function get_token( $token = '' ) {
		if ( ! empty( $token ) ) {
			return sanitize_text_field( $token );
		}

		$field = $this->get_token_field_name();

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- CAPTCHA token validated server-side against provider API.
		if ( isset( $_POST[ $field ] ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- CAPTCHA token validated server-side against provider API.
			return sanitize_text_field( wp_unslash( $_POST[ $field ] ) );
		}

		return '';
	}

	/**
	 * Get the client IP address.
	 *
	 * Used for verification requests to the provider API.
	 *
	 * @since 1.0.0
	 * @return string Client IP address.
	 */
	protected function get_client_ip() {
		$ip = '';

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

	/**
	 * Make verification request to provider API.
	 *
	 * Sends the token to the provider's verification endpoint.
	 *
	 * @since 1.0.0
	 * @param string $token      The response token to verify.
	 * @param string $secret_key Optional. Secret key to use.
	 * @return array|\WP_Error Response array or error.
	 */
	protected function make_verification_request( $token, $secret_key = '' ) {
		if ( empty( $secret_key ) ) {
			$secret_key = $this->get_secret_key();
		}

		$body = array(
			'secret'   => $secret_key,
			'response' => $token,
			'remoteip' => $this->get_client_ip(),
		);

		/**
		 * Filter the verification request body.
		 *
		 * @since 1.0.0
		 * @param array  $body       Request body parameters.
		 * @param string $provider   Provider identifier.
		 */
		$body = apply_filters( 'cfwc_verification_request_body', $body, $this->get_id() );

		$response = wp_remote_post(
			$this->verify_endpoint,
			array(
				'body'    => $body,
				'timeout' => apply_filters( 'cfwc_verification_timeout', 30, $this->get_id() ),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( null === $data ) {
			return new \WP_Error(
				'cfwc_invalid_response',
				__( 'Invalid response from CAPTCHA service.', 'captcha-for-woocommerce' )
			);
		}

		return $data;
	}

	/**
	 * Create standardized error response.
	 *
	 * @since 1.0.0
	 * @param string $code    Error code.
	 * @param string $message Error message.
	 * @param array  $data    Optional. Additional error data.
	 * @return \WP_Error Error object.
	 */
	protected function create_error( $code, $message, $data = array() ) {
		return new \WP_Error( 'cfwc_' . $code, $message, $data );
	}
}
