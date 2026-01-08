<?php
/**
 * Dashboard Widget.
 *
 * Displays bot protection statistics in the WordPress admin dashboard.
 * Provides a quick overview of blocked attempts and lockouts.
 *
 * @package Captcha_For_WooCommerce
 * @since   1.0.0
 */

namespace CFWC\Admin;

use CFWC\Plugin;
use CFWC\Protection\Rate_Limiter;

// Prevent direct file access.
defined( 'ABSPATH' ) || exit;

/**
 * Dashboard_Widget class.
 *
 * Adds a widget to the WordPress admin dashboard showing
 * CAPTCHA protection statistics.
 *
 * @since 1.0.0
 */
class Dashboard_Widget {

	/**
	 * Option name for storing statistics.
	 *
	 * @var string
	 */
	const STATS_OPTION = 'cfwc_protection_stats';

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		add_action( 'wp_dashboard_setup', array( $this, 'register_widget' ) );
		add_action( 'cfwc_failed', array( $this, 'record_blocked_attempt' ), 10, 2 );
	}

	/**
	 * Register the dashboard widget.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function register_widget() {
		// Only show to users who can manage WooCommerce.
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		wp_add_dashboard_widget(
			'cfwc_protection_widget',
			__( 'Bot Protection', 'captcha-for-woocommerce' ) . ' <span class="cfwc-badge">CAPTCHA</span>',
			array( $this, 'render_widget' )
		);
	}

	/**
	 * Render the dashboard widget content.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function render_widget() {
		$stats    = $this->get_stats();
		$settings = Plugin::instance()->settings();
		$provider = $settings->get( 'provider' );

		// Get active provider name.
		$provider_names = array(
			'recaptcha_v2' => 'reCAPTCHA v2',
			'recaptcha_v3' => 'reCAPTCHA v3',
			'turnstile'    => 'Cloudflare Turnstile',
			'hcaptcha'     => 'hCaptcha',
			'honeypot'     => 'Honeypot',
		);
		$provider_name = isset( $provider_names[ $provider ] ) ? $provider_names[ $provider ] : __( 'Not configured', 'captcha-for-woocommerce' );

		// Get lockout info.
		$lockouts      = get_option( Rate_Limiter::LOCKOUTS_OPTION, array() );
		$active_locks  = 0;
		$current_time  = time();

		if ( is_array( $lockouts ) ) {
			foreach ( $lockouts as $ip => $lockout_time ) {
				if ( $lockout_time > $current_time ) {
					$active_locks++;
				}
			}
		}

		?>
		<div class="cfwc-widget-grid">
			<div class="cfwc-stat-box">
				<div class="cfwc-stat-number"><?php echo esc_html( number_format_i18n( $stats['today'] ) ); ?></div>
				<div class="cfwc-stat-label"><?php esc_html_e( 'Today', 'captcha-for-woocommerce' ); ?></div>
			</div>
			<div class="cfwc-stat-box">
				<div class="cfwc-stat-number"><?php echo esc_html( number_format_i18n( $stats['week'] ) ); ?></div>
				<div class="cfwc-stat-label"><?php esc_html_e( 'This Week', 'captcha-for-woocommerce' ); ?></div>
			</div>
			<div class="cfwc-stat-box <?php echo $active_locks > 0 ? 'warning' : ''; ?>">
				<div class="cfwc-stat-number"><?php echo esc_html( number_format_i18n( $active_locks ) ); ?></div>
				<div class="cfwc-stat-label"><?php esc_html_e( 'Locked IPs', 'captcha-for-woocommerce' ); ?></div>
			</div>
		</div>

		<?php
		// Get honeypot-specific stats.
		$hp_stats = get_option( 'cfwc_honeypot_stats', array( 'total' => 0, 'today' => array( 'date' => '', 'count' => 0 ) ) );
		$hp_blocked = isset( $hp_stats['total'] ) ? $hp_stats['total'] : 0;
		$hp_today = ( isset( $hp_stats['today']['date'] ) && $hp_stats['today']['date'] === gmdate( 'Y-m-d' ) ) ? $hp_stats['today']['count'] : 0;
		?>

		<div class="cfwc-status-rows">
			<div class="cfwc-status-row">
				<span class="cfwc-status-label"><?php esc_html_e( 'Provider', 'captcha-for-woocommerce' ); ?></span>
				<span class="cfwc-status-value <?php echo $provider ? 'active' : 'inactive'; ?>">
					<?php echo esc_html( $provider_name ); ?>
				</span>
			</div>
			<div class="cfwc-status-row">
				<span class="cfwc-status-label"><?php esc_html_e( 'Rate Limiting', 'captcha-for-woocommerce' ); ?></span>
				<span class="cfwc-status-value <?php echo 'yes' === $settings->get( 'enable_rate_limiting' ) ? 'active' : 'inactive'; ?>">
					<?php echo 'yes' === $settings->get( 'enable_rate_limiting' ) ? esc_html__( 'Active', 'captcha-for-woocommerce' ) : esc_html__( 'Off', 'captcha-for-woocommerce' ); ?>
				</span>
			</div>
			<div class="cfwc-status-row">
				<span class="cfwc-status-label"><?php esc_html_e( 'Honeypot', 'captcha-for-woocommerce' ); ?></span>
				<span class="cfwc-status-value <?php echo ( 'honeypot' === $provider || 'yes' === $settings->get( 'enable_honeypot' ) ) ? 'active' : 'inactive'; ?>">
					<?php
					if ( 'honeypot' === $provider || 'yes' === $settings->get( 'enable_honeypot' ) ) {
						if ( $hp_blocked > 0 ) {
							printf(
								/* translators: %d: number of spam blocked */
								esc_html__( 'Active (%d blocked)', 'captcha-for-woocommerce' ),
								absint( $hp_blocked )
							);
						} else {
							esc_html_e( 'Active', 'captcha-for-woocommerce' );
						}
					} else {
						esc_html_e( 'Off', 'captcha-for-woocommerce' );
					}
					?>
				</span>
			</div>
			<div class="cfwc-status-row">
				<span class="cfwc-status-label"><?php esc_html_e( 'Total Blocked', 'captcha-for-woocommerce' ); ?></span>
				<span class="cfwc-status-value">
					<?php echo esc_html( number_format_i18n( $stats['total'] + $hp_blocked ) ); ?>
				</span>
			</div>
		</div>

		<div class="cfwc-widget-footer">
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=wc-settings&tab=cfwc_captcha' ) ); ?>" class="button button-secondary">
				<?php esc_html_e( 'Configure Protection', 'captcha-for-woocommerce' ); ?>
			</a>
		</div>
		<?php
	}

	/**
	 * Record a blocked attempt.
	 *
	 * Called when CAPTCHA verification fails.
	 *
	 * @since 1.0.0
	 * @param string    $form_type The form that was protected.
	 * @param \WP_Error $error     The error that occurred.
	 * @return void
	 */
	public function record_blocked_attempt( $form_type, $error ) {
		$stats = $this->get_stats();

		// Increment counters.
		$stats['total']++;
		$stats['today']++;
		$stats['week']++;

		// Track by form type.
		if ( ! isset( $stats['by_form'][ $form_type ] ) ) {
			$stats['by_form'][ $form_type ] = 0;
		}
		$stats['by_form'][ $form_type ]++;

		// Update date tracking.
		$stats['last_today'] = gmdate( 'Y-m-d' );
		$stats['last_week']  = gmdate( 'W-Y' );

		update_option( self::STATS_OPTION, $stats );
	}

	/**
	 * Get protection statistics.
	 *
	 * @since 1.0.0
	 * @return array Statistics array.
	 */
	private function get_stats() {
		$defaults = array(
			'total'      => 0,
			'today'      => 0,
			'week'       => 0,
			'last_today' => '',
			'last_week'  => '',
			'by_form'    => array(),
		);

		$stats = get_option( self::STATS_OPTION, $defaults );
		$stats = wp_parse_args( $stats, $defaults );

		// Reset daily counter if date changed.
		$today = gmdate( 'Y-m-d' );
		if ( $stats['last_today'] !== $today ) {
			$stats['today']      = 0;
			$stats['last_today'] = $today;
		}

		// Reset weekly counter if week changed.
		$week = gmdate( 'W-Y' );
		if ( $stats['last_week'] !== $week ) {
			$stats['week']      = 0;
			$stats['last_week'] = $week;
		}

		return $stats;
	}
}
