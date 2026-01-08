<?php
/**
 * Admin Notices Handler.
 *
 * Manages admin notices for plugin activation, configuration reminders,
 * and important updates.
 *
 * @package Captcha_For_WooCommerce
 * @since   1.0.0
 */

namespace CFWC\Admin;

use CFWC\Plugin;

// Prevent direct file access.
defined( 'ABSPATH' ) || exit;

/**
 * Admin_Notices class.
 *
 * Handles the display and dismissal of admin notices throughout
 * the WordPress admin area.
 *
 * @since 1.0.0
 */
class Admin_Notices {

	/**
	 * Tracks whether notice has been rendered in current request.
	 *
	 * @var bool
	 */
	private static $notice_shown = false;

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		add_action( 'admin_notices', array( $this, 'show_notices' ) );
		add_action( 'wp_ajax_cfwc_dismiss_welcome_notice', array( $this, 'ajax_dismiss_welcome_notice' ) );
		add_action( 'admin_footer', array( $this, 'output_dismiss_script' ) );
	}

	/**
	 * Display admin notices.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function show_notices() {
		if ( self::$notice_shown ) {
			return;
		}

		$this->show_welcome_notice();
		$this->show_configuration_notice();
	}

	/**
	 * Display welcome notice for new installations.
	 *
	 * Shows a helpful getting-started message on the plugins page
	 * after initial activation.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private function show_welcome_notice() {
		// Skip if already dismissed.
		if ( get_option( 'cfwc_welcome_notice_dismissed' ) ) {
			return;
		}

		// Only show on plugins page.
		$screen = get_current_screen();
		if ( ! $screen || 'plugins' !== $screen->id ) {
			return;
		}

		self::$notice_shown = true;

		$settings_url = admin_url( 'admin.php?page=wc-settings&tab=cfwc_captcha' );
		?>
		<div class="notice notice-info is-dismissible cfwc-welcome-notice" data-notice="cfwc-welcome">
			<p>
				<strong><?php esc_html_e( 'Captcha for WooCommerce', 'captcha-for-woocommerce' ); ?></strong> &ndash;
				<?php esc_html_e( 'Thank you for installing! Configure your CAPTCHA provider to start protecting your store.', 'captcha-for-woocommerce' ); ?>
			</p>
			<p>
				<a href="<?php echo esc_url( $settings_url ); ?>" class="button button-primary">
					<?php esc_html_e( 'Configure Settings', 'captcha-for-woocommerce' ); ?>
				</a>
				<a href="https://wordpress.org/plugins/captcha-for-woocommerce/" target="_blank" rel="noopener noreferrer" class="button button-secondary">
					<?php esc_html_e( 'Documentation', 'captcha-for-woocommerce' ); ?>
				</a>
			</p>
		</div>
		<?php
	}

	/**
	 * Display configuration notice on WooCommerce pages.
	 *
	 * Reminds administrators to configure the plugin if no
	 * CAPTCHA provider has been set up.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private function show_configuration_notice() {
		// Only show if plugin is not yet configured.
		$settings = get_option( 'cfwc_settings', array() );
		if ( ! empty( $settings['provider'] ) && ! empty( $settings['site_key'] ) ) {
			return;
		}

		// Skip on our settings page (we show a different notice there).
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( isset( $_GET['tab'] ) && 'cfwc_captcha' === $_GET['tab'] ) {
			return;
		}

		// Only show on WooCommerce pages.
		$screen = get_current_screen();
		if ( ! $screen || 0 !== strpos( $screen->id, 'woocommerce' ) ) {
			return;
		}

		// Skip if dismissed within the last week.
		$dismissed_time = get_option( 'cfwc_config_notice_dismissed' );
		if ( $dismissed_time && ( time() - $dismissed_time ) < WEEK_IN_SECONDS ) {
			return;
		}

		$settings_url = admin_url( 'admin.php?page=wc-settings&tab=cfwc_captcha' );
		?>
		<div class="notice notice-warning is-dismissible cfwc-config-notice" data-notice="cfwc-config">
			<p>
				<strong><?php esc_html_e( 'Captcha for WooCommerce:', 'captcha-for-woocommerce' ); ?></strong>
				<?php esc_html_e( 'Your store is not yet protected. Please configure your CAPTCHA provider.', 'captcha-for-woocommerce' ); ?>
				<a href="<?php echo esc_url( $settings_url ); ?>">
					<?php esc_html_e( 'Configure now', 'captcha-for-woocommerce' ); ?>
				</a>
			</p>
		</div>
		<?php
	}

	/**
	 * Enqueue scripts for notice dismissal.
	 *
	 * Uses proper WordPress enqueue to handle notice dismissal via AJAX.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function output_dismiss_script() {
		$screen = get_current_screen();
		if ( ! $screen ) {
			return;
		}

		// Only output on relevant pages.
		if ( 'plugins' !== $screen->id && 0 !== strpos( $screen->id, 'woocommerce' ) ) {
			return;
		}

		// Enqueue inline script properly.
		$script = sprintf(
			'(function($) {
				$(".cfwc-welcome-notice, .cfwc-config-notice").on("click", ".notice-dismiss", function() {
					var notice = $(this).closest(".notice").data("notice");
					$.post(ajaxurl, {
						action: "cfwc_dismiss_welcome_notice",
						notice: notice,
						nonce: "%s"
					});
				});
			})(jQuery);',
			wp_create_nonce( 'cfwc_dismiss_notice' )
		);

		wp_add_inline_script( 'jquery', $script );
	}

	/**
	 * Handle AJAX request to dismiss notices.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function ajax_dismiss_welcome_notice() {
		// Verify nonce.
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['nonce'] ), 'cfwc_dismiss_notice' ) ) {
			wp_die( -1, 403 );
		}

		// Check capability.
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( -1, 403 );
		}

		$notice = isset( $_POST['notice'] ) ? sanitize_key( $_POST['notice'] ) : '';

		switch ( $notice ) {
			case 'cfwc-welcome':
				update_option( 'cfwc_welcome_notice_dismissed', true );
				break;
			case 'cfwc-config':
				update_option( 'cfwc_config_notice_dismissed', time() );
				break;
		}

		wp_die();
	}
}
