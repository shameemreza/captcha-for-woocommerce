<?php
/**
 * PayPal Payments Compatibility.
 *
 * Handles compatibility with WooCommerce PayPal Payments plugin,
 * which has its own reCAPTCHA fraud protection module.
 *
 * @package Captcha_For_WooCommerce
 * @since   1.0.0
 */

namespace CFWC\Compatibility;

// Prevent direct file access.
defined( 'ABSPATH' ) || exit;

/**
 * PayPal_Payments class.
 *
 * Detects PayPal Payments reCAPTCHA and coordinates to avoid conflicts.
 *
 * @since 1.0.0
 */
class PayPal_Payments {

	/**
	 * PayPal payment method IDs protected by their reCAPTCHA.
	 *
	 * @var array
	 */
	private $paypal_methods = array(
		'ppcp-gateway',
		'ppcp-credit-card-gateway',
		'ppcp-card-button-gateway',
	);

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		// Register filter to skip CAPTCHA for PayPal methods.
		add_filter( 'cfwc_skip_for_payment_method', array( $this, 'maybe_skip_for_paypal' ), 10, 2 );
		add_filter( 'cfwc_skip_verification', array( $this, 'skip_checkout_verification' ), 10, 3 );

		// Display admin notice about PayPal compatibility.
		add_action( 'admin_notices', array( $this, 'display_compatibility_notice' ) );
	}

	/**
	 * Check if PayPal Payments reCAPTCHA is enabled.
	 *
	 * @since 1.0.0
	 * @return bool True if PayPal reCAPTCHA is configured and enabled.
	 */
	public static function is_paypal_recaptcha_enabled() {
		$settings = get_option( 'woocommerce_ppcp-recaptcha_settings', array() );

		if ( empty( $settings['enabled'] ) || 'yes' !== $settings['enabled'] ) {
			return false;
		}

		// Check if all required keys are configured.
		$has_v3 = ! empty( $settings['site_key_v3'] ) && ! empty( $settings['secret_key_v3'] );
		$has_v2 = ! empty( $settings['site_key_v2'] ) && ! empty( $settings['secret_key_v2'] );

		return $has_v3 && $has_v2;
	}

	/**
	 * Get PayPal payment method IDs.
	 *
	 * @since 1.0.0
	 * @return array Array of payment method IDs.
	 */
	public static function get_paypal_protected_methods() {
		/**
		 * Filter PayPal protected payment methods.
		 *
		 * @since 1.0.0
		 * @param array $methods Payment method IDs.
		 */
		return apply_filters(
			'cfwc_paypal_protected_methods',
			array(
				'ppcp-gateway',
				'ppcp-credit-card-gateway',
				'ppcp-card-button-gateway',
			)
		);
	}

	/**
	 * Maybe skip CAPTCHA for PayPal payment methods.
	 *
	 * @since 1.0.0
	 * @param bool   $skip           Whether to skip CAPTCHA.
	 * @param string $payment_method The payment method ID.
	 * @return bool Whether to skip.
	 */
	public function maybe_skip_for_paypal( $skip, $payment_method ) {
		// If already skipping, don't override.
		if ( $skip ) {
			return $skip;
		}

		// Check if PayPal reCAPTCHA is handling this.
		if ( self::is_paypal_recaptcha_enabled() ) {
			$paypal_methods = self::get_paypal_protected_methods();

			if ( in_array( $payment_method, $paypal_methods, true ) ) {
				return true;
			}
		}

		return $skip;
	}

	/**
	 * Skip checkout verification for PayPal methods.
	 *
	 * @since 1.0.0
	 * @param bool   $skip      Whether to skip verification.
	 * @param string $form_type The form type.
	 * @param mixed  $context   Additional context.
	 * @return bool Whether to skip.
	 */
	public function skip_checkout_verification( $skip, $form_type, $context ) {
		// Only apply to checkout forms.
		if ( ! in_array( $form_type, array( 'wc_checkout_classic', 'wc_checkout_block' ), true ) ) {
			return $skip;
		}

		// Get chosen payment method.
		$payment_method = '';

		if ( WC()->session ) {
			$payment_method = WC()->session->get( 'chosen_payment_method' );
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- WooCommerce handles nonce verification in checkout process.
		if ( empty( $payment_method ) && isset( $_POST['payment_method'] ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- WooCommerce handles nonce verification in checkout process.
			$payment_method = sanitize_text_field( wp_unslash( $_POST['payment_method'] ) );
		}

		if ( empty( $payment_method ) ) {
			return $skip;
		}

		return $this->maybe_skip_for_paypal( $skip, $payment_method );
	}

	/**
	 * Display compatibility notice in admin.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function display_compatibility_notice() {
		// Only show on our settings page.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! isset( $_GET['tab'] ) || 'cfwc_captcha' !== $_GET['tab'] ) {
			return;
		}

		if ( ! self::is_paypal_recaptcha_enabled() ) {
			return;
		}

		?>
		<div class="notice notice-info">
			<p>
				<strong><?php esc_html_e( 'PayPal Payments Integration:', 'captcha-for-woocommerce' ); ?></strong>
				<?php esc_html_e( 'PayPal Payments has its own reCAPTCHA enabled. Our CAPTCHA will automatically skip PayPal payment methods to avoid double verification.', 'captcha-for-woocommerce' ); ?>
			</p>
		</div>
		<?php
	}
}
