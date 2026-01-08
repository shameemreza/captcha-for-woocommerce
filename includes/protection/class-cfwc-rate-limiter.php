<?php
/**
 * Rate Limiter.
 *
 * Provides anti-bruteforce protection by limiting failed CAPTCHA attempts.
 * Tracks attempts by IP address and implements configurable lockout periods.
 *
 * @package Captcha_For_WooCommerce
 * @since   1.0.0
 */

namespace CFWC\Protection;

use CFWC\Plugin;

// Prevent direct file access.
defined( 'ABSPATH' ) || exit;

/**
 * Rate_Limiter class.
 *
 * Implements rate limiting for CAPTCHA validation to prevent bruteforce attacks.
 * Tracks failed attempts per IP and applies configurable lockout periods.
 *
 * @since 1.0.0
 */
class Rate_Limiter {

	/**
	 * Option name for storing failed attempts.
	 *
	 * @var string
	 */
	const ATTEMPTS_OPTION = 'cfwc_failed_attempts';

	/**
	 * Option name for storing lockouts.
	 *
	 * @var string
	 */
	const LOCKOUTS_OPTION = 'cfwc_lockouts';

	/**
	 * Option name for storing attempt timestamps.
	 *
	 * @var string
	 */
	const TIMESTAMPS_OPTION = 'cfwc_attempt_timestamps';

	/**
	 * Singleton instance.
	 *
	 * @var Rate_Limiter|null
	 */
	private static $instance = null;

	/**
	 * Get singleton instance.
	 *
	 * @since 1.0.0
	 * @return Rate_Limiter
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 */
	private function __construct() {
		// Cleanup old entries periodically.
		add_action( 'cfwc_cleanup', array( $this, 'cleanup_old_entries' ) );
	}

	/**
	 * Check if rate limiting is enabled.
	 *
	 * @since 1.0.0
	 * @return bool True if enabled.
	 */
	public function is_enabled() {
		$settings = Plugin::instance()->settings();
		return 'yes' === $settings->get( 'enable_rate_limiting' );
	}

	/**
	 * Check if IP is currently locked out.
	 *
	 * @since 1.0.0
	 * @param string|null $ip IP address to check. Defaults to current visitor IP.
	 * @return bool True if locked out.
	 */
	public function is_locked_out( $ip = null ) {
		if ( ! $this->is_enabled() ) {
			return false;
		}

		$ip       = $ip ?? $this->get_client_ip();
		$lockouts = get_option( self::LOCKOUTS_OPTION, array() );

		if ( ! is_array( $lockouts ) || ! isset( $lockouts[ $ip ] ) ) {
			return false;
		}

		$lockout_time = $lockouts[ $ip ];
		$current_time = time();

		// Check if lockout has expired.
		if ( $current_time >= $lockout_time ) {
			// Remove expired lockout.
			unset( $lockouts[ $ip ] );
			update_option( self::LOCKOUTS_OPTION, $lockouts );
			return false;
		}

		return true;
	}

	/**
	 * Get remaining lockout time in seconds.
	 *
	 * @since 1.0.0
	 * @param string|null $ip IP address to check. Defaults to current visitor IP.
	 * @return int Remaining seconds, 0 if not locked out.
	 */
	public function get_lockout_remaining( $ip = null ) {
		if ( ! $this->is_enabled() ) {
			return 0;
		}

		$ip       = $ip ?? $this->get_client_ip();
		$lockouts = get_option( self::LOCKOUTS_OPTION, array() );

		if ( ! is_array( $lockouts ) || ! isset( $lockouts[ $ip ] ) ) {
			return 0;
		}

		$remaining = $lockouts[ $ip ] - time();
		return max( 0, $remaining );
	}

	/**
	 * Get human-readable lockout message.
	 *
	 * @since 1.0.0
	 * @param string|null $ip IP address to check. Defaults to current visitor IP.
	 * @return string Lockout message or empty string if not locked.
	 */
	public function get_lockout_message( $ip = null ) {
		$remaining = $this->get_lockout_remaining( $ip );

		if ( $remaining <= 0 ) {
			return '';
		}

		$minutes = ceil( $remaining / 60 );

		if ( $minutes > 60 ) {
			$hours = ceil( $minutes / 60 );
			return sprintf(
				/* translators: %d: number of hours */
				_n(
					'Too many failed attempts. Please try again in %d hour.',
					'Too many failed attempts. Please try again in %d hours.',
					$hours,
					'captcha-for-woocommerce'
				),
				$hours
			);
		}

		return sprintf(
			/* translators: %d: number of minutes */
			_n(
				'Too many failed attempts. Please try again in %d minute.',
				'Too many failed attempts. Please try again in %d minutes.',
				$minutes,
				'captcha-for-woocommerce'
			),
			$minutes
		);
	}

	/**
	 * Record a failed CAPTCHA attempt.
	 *
	 * @since 1.0.0
	 * @param string|null $ip IP address. Defaults to current visitor IP.
	 * @return void
	 */
	public function record_failure( $ip = null ) {
		if ( ! $this->is_enabled() ) {
			return;
		}

		$ip = $ip ?? $this->get_client_ip();

		// Check if IP is whitelisted.
		if ( $this->is_ip_whitelisted( $ip ) ) {
			return;
		}

		// Get current attempts.
		$attempts   = get_option( self::ATTEMPTS_OPTION, array() );
		$timestamps = get_option( self::TIMESTAMPS_OPTION, array() );

		if ( ! is_array( $attempts ) ) {
			$attempts = array();
		}
		if ( ! is_array( $timestamps ) ) {
			$timestamps = array();
		}

		// Get settings.
		$settings           = Plugin::instance()->settings();
		$max_attempts       = absint( $settings->get( 'rate_limit_requests' ) );
		$lockout_duration   = absint( $settings->get( 'rate_limit_lockout' ) );
		$window_minutes     = absint( $settings->get( 'rate_limit_window' ) );

		// Use defaults if settings are empty or zero.
		$max_attempts     = $max_attempts > 0 ? $max_attempts : 5;
		$lockout_duration = $lockout_duration > 0 ? $lockout_duration : 15;
		$window_minutes   = $window_minutes > 0 ? $window_minutes : 60;

		$current_time  = time();
		$window_start  = $current_time - ( $window_minutes * 60 );

		// Initialize or increment attempt count.
		if ( ! isset( $attempts[ $ip ] ) || ! isset( $timestamps[ $ip ] ) || $timestamps[ $ip ] < $window_start ) {
			// Reset if outside window.
			$attempts[ $ip ]   = 1;
			$timestamps[ $ip ] = $current_time;
		} else {
			$attempts[ $ip ]++;
		}

		// Check if limit reached.
		if ( $attempts[ $ip ] >= $max_attempts ) {
			// Apply lockout.
			$lockouts = get_option( self::LOCKOUTS_OPTION, array() );
			if ( ! is_array( $lockouts ) ) {
				$lockouts = array();
			}

			$lockouts[ $ip ] = $current_time + ( $lockout_duration * 60 );
			update_option( self::LOCKOUTS_OPTION, $lockouts );

			// Reset attempts after lockout.
			unset( $attempts[ $ip ] );
			unset( $timestamps[ $ip ] );

			// Log the lockout.
			$this->log_lockout( $ip, $lockout_duration );
		}

		// Save updated attempts.
		update_option( self::ATTEMPTS_OPTION, $attempts );
		update_option( self::TIMESTAMPS_OPTION, $timestamps );
	}

	/**
	 * Record a successful CAPTCHA verification.
	 *
	 * Clears failed attempts for the IP on success.
	 *
	 * @since 1.0.0
	 * @param string|null $ip IP address. Defaults to current visitor IP.
	 * @return void
	 */
	public function record_success( $ip = null ) {
		if ( ! $this->is_enabled() ) {
			return;
		}

		$ip = $ip ?? $this->get_client_ip();

		// Clear attempts on successful verification.
		$attempts   = get_option( self::ATTEMPTS_OPTION, array() );
		$timestamps = get_option( self::TIMESTAMPS_OPTION, array() );

		if ( is_array( $attempts ) && isset( $attempts[ $ip ] ) ) {
			unset( $attempts[ $ip ] );
			update_option( self::ATTEMPTS_OPTION, $attempts );
		}

		if ( is_array( $timestamps ) && isset( $timestamps[ $ip ] ) ) {
			unset( $timestamps[ $ip ] );
			update_option( self::TIMESTAMPS_OPTION, $timestamps );
		}
	}

	/**
	 * Get remaining attempts for an IP.
	 *
	 * @since 1.0.0
	 * @param string|null $ip IP address. Defaults to current visitor IP.
	 * @return int Remaining attempts.
	 */
	public function get_remaining_attempts( $ip = null ) {
		if ( ! $this->is_enabled() ) {
			return PHP_INT_MAX;
		}

		$ip       = $ip ?? $this->get_client_ip();
		$settings = Plugin::instance()->settings();

		$max_attempts = absint( $settings->get( 'rate_limit_requests' ) );
		$max_attempts = $max_attempts > 0 ? $max_attempts : 5;

		$attempts = get_option( self::ATTEMPTS_OPTION, array() );

		if ( ! is_array( $attempts ) || ! isset( $attempts[ $ip ] ) ) {
			return $max_attempts;
		}

		return max( 0, $max_attempts - $attempts[ $ip ] );
	}

	/**
	 * Check if an IP is whitelisted.
	 *
	 * @since 1.0.0
	 * @param string $ip IP address to check.
	 * @return bool True if whitelisted.
	 */
	public function is_ip_whitelisted( $ip ) {
		$settings      = Plugin::instance()->settings();
		$whitelist_ips = $settings->get( 'whitelist_ips' );

		if ( empty( $whitelist_ips ) ) {
			return false;
		}

		$whitelist = array_map( 'trim', explode( "\n", $whitelist_ips ) );
		$whitelist = array_filter( $whitelist );

		foreach ( $whitelist as $entry ) {
			// Support CIDR notation.
			if ( strpos( $entry, '/' ) !== false ) {
				if ( $this->ip_in_cidr( $ip, $entry ) ) {
					return true;
				}
			} elseif ( $ip === $entry ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Check if an IP matches a CIDR range.
	 *
	 * @since 1.0.0
	 * @param string $ip   IP address to check.
	 * @param string $cidr CIDR notation (e.g., 192.168.1.0/24).
	 * @return bool True if IP is in range.
	 */
	private function ip_in_cidr( $ip, $cidr ) {
		list( $subnet, $mask ) = explode( '/', $cidr );

		if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 ) ) {
			$ip_long     = ip2long( $ip );
			$subnet_long = ip2long( $subnet );
			$mask_long   = -1 << ( 32 - (int) $mask );

			return ( $ip_long & $mask_long ) === ( $subnet_long & $mask_long );
		}

		return false;
	}

	/**
	 * Get the client's IP address.
	 *
	 * @since 1.0.0
	 * @return string IP address.
	 */
	public function get_client_ip() {
		$ip = '';

		// Check for various headers (in order of reliability).
		$headers = array(
			'HTTP_CF_CONNECTING_IP', // Cloudflare.
			'HTTP_X_REAL_IP',        // Nginx proxy.
			'HTTP_X_FORWARDED_FOR',  // Load balancers.
			'REMOTE_ADDR',           // Direct connection.
		);

		foreach ( $headers as $header ) {
			if ( ! empty( $_SERVER[ $header ] ) ) {
				$ip = sanitize_text_field( wp_unslash( $_SERVER[ $header ] ) );

				// X-Forwarded-For can contain multiple IPs, take the first.
				if ( strpos( $ip, ',' ) !== false ) {
					$ips = explode( ',', $ip );
					$ip  = trim( $ips[0] );
				}

				// Validate IP.
				if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
					break;
				}

				$ip = '';
			}
		}

		// Fallback to REMOTE_ADDR.
		if ( empty( $ip ) && isset( $_SERVER['REMOTE_ADDR'] ) ) {
			$ip = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );
		}

		return $ip ?: '0.0.0.0';
	}

	/**
	 * Log a lockout event.
	 *
	 * @since 1.0.0
	 * @param string $ip       Locked out IP address.
	 * @param int    $duration Lockout duration in minutes.
	 * @return void
	 */
	private function log_lockout( $ip, $duration ) {
		$settings = Plugin::instance()->settings();

		if ( 'yes' !== $settings->get( 'enable_debug_logging' ) ) {
			return;
		}

		if ( class_exists( '\CFWC\Logger' ) ) {
			\CFWC\Logger::log(
				sprintf(
					'IP %s locked out for %d minutes after exceeding failed attempt limit.',
					$ip,
					$duration
				),
				'warning'
			);
		}
	}

	/**
	 * Cleanup old entries from the database.
	 *
	 * Called via scheduled event.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function cleanup_old_entries() {
		$current_time = time();

		// Clean up expired lockouts.
		$lockouts = get_option( self::LOCKOUTS_OPTION, array() );
		if ( is_array( $lockouts ) ) {
			foreach ( $lockouts as $ip => $lockout_time ) {
				if ( $current_time >= $lockout_time ) {
					unset( $lockouts[ $ip ] );
				}
			}
			update_option( self::LOCKOUTS_OPTION, $lockouts );
		}

		// Clean up old attempts (older than 24 hours).
		$timestamps = get_option( self::TIMESTAMPS_OPTION, array() );
		$attempts   = get_option( self::ATTEMPTS_OPTION, array() );
		$day_ago    = $current_time - DAY_IN_SECONDS;

		if ( is_array( $timestamps ) && is_array( $attempts ) ) {
			foreach ( $timestamps as $ip => $timestamp ) {
				if ( $timestamp < $day_ago ) {
					unset( $timestamps[ $ip ] );
					unset( $attempts[ $ip ] );
				}
			}
			update_option( self::TIMESTAMPS_OPTION, $timestamps );
			update_option( self::ATTEMPTS_OPTION, $attempts );
		}
	}
}
