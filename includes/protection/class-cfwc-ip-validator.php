<?php
/**
 * IP Validator.
 *
 * Validates IP addresses against whitelist and blocklist settings.
 * Supports individual IPs and CIDR notation for range matching.
 *
 * @package Captcha_For_WooCommerce
 * @since   1.0.0
 */

namespace CFWC\Protection;

use CFWC\Plugin;

// Prevent direct file access.
defined( 'ABSPATH' ) || exit;

/**
 * IP_Validator class.
 *
 * Handles IP-based access control for CAPTCHA validation.
 *
 * @since 1.0.0
 */
class IP_Validator {

	/**
	 * Singleton instance.
	 *
	 * @var IP_Validator|null
	 */
	private static $instance = null;

	/**
	 * Get singleton instance.
	 *
	 * @since 1.0.0
	 * @return IP_Validator
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Private constructor.
	 *
	 * @since 1.0.0
	 */
	private function __construct() {}

	/**
	 * Check if CAPTCHA should be skipped for the current visitor.
	 *
	 * Returns true if:
	 * - IP is whitelisted
	 * - Logged-in users are whitelisted and user is logged in
	 * - User has a whitelisted role
	 *
	 * @since 1.0.0
	 * @return bool True if CAPTCHA should be skipped.
	 */
	public function should_skip_captcha() {
		$settings = Plugin::instance()->settings();

		// Check whitelist for logged-in users.
		if ( is_user_logged_in() ) {
			if ( 'yes' === $settings->get( 'whitelist_logged_in' ) ) {
				return true;
			}

			// Check whitelisted roles.
			$whitelist_roles = $settings->get( 'whitelist_roles' );
			if ( ! empty( $whitelist_roles ) && is_array( $whitelist_roles ) ) {
				$user = wp_get_current_user();
				if ( array_intersect( $user->roles, $whitelist_roles ) ) {
					return true;
				}
			}
		}

		// Check IP whitelist.
		$ip = $this->get_client_ip();
		if ( $this->is_ip_whitelisted( $ip ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Check if the current visitor should be blocked.
	 *
	 * Returns true if IP is in the blocklist.
	 *
	 * @since 1.0.0
	 * @return bool|string False if not blocked, error message if blocked.
	 */
	public function is_blocked() {
		$ip = $this->get_client_ip();

		if ( $this->is_ip_blocklisted( $ip ) ) {
			/**
			 * Filter the blocked IP error message.
			 *
			 * @since 1.0.0
			 * @param string $message The error message.
			 * @param string $ip      The blocked IP address.
			 */
			return apply_filters(
				'cfwc_blocked_ip_message',
				__( 'Your IP address has been blocked. Please contact the site administrator.', 'captcha-for-woocommerce' ),
				$ip
			);
		}

		return false;
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

		return $this->ip_matches_list( $ip, $whitelist_ips );
	}

	/**
	 * Check if an IP is blocklisted.
	 *
	 * @since 1.0.0
	 * @param string $ip IP address to check.
	 * @return bool True if blocklisted.
	 */
	public function is_ip_blocklisted( $ip ) {
		$settings      = Plugin::instance()->settings();
		$blocklist_ips = $settings->get( 'blocklist_ips' );

		return $this->ip_matches_list( $ip, $blocklist_ips );
	}

	/**
	 * Check if an IP matches any entry in a list.
	 *
	 * Supports:
	 * - Individual IPs (e.g., 192.168.1.1)
	 * - CIDR notation (e.g., 192.168.1.0/24)
	 * - Wildcards (e.g., 192.168.1.*)
	 *
	 * @since 1.0.0
	 * @param string $ip   IP address to check.
	 * @param string $list Newline-separated list of IPs/CIDRs/patterns.
	 * @return bool True if IP matches any entry.
	 */
	private function ip_matches_list( $ip, $list ) {
		if ( empty( $list ) || empty( $ip ) ) {
			return false;
		}

		// Validate the IP address.
		if ( ! filter_var( $ip, FILTER_VALIDATE_IP ) ) {
			return false;
		}

		// Parse the list.
		$entries = array_map( 'trim', explode( "\n", $list ) );
		$entries = array_filter( $entries );

		foreach ( $entries as $entry ) {
			// Skip comments.
			if ( strpos( $entry, '#' ) === 0 ) {
				continue;
			}

			// Remove inline comments.
			$entry = trim( explode( '#', $entry )[0] );

			if ( empty( $entry ) ) {
				continue;
			}

			// Check for CIDR notation.
			if ( strpos( $entry, '/' ) !== false ) {
				if ( $this->ip_in_cidr( $ip, $entry ) ) {
					return true;
				}
				continue;
			}

			// Check for wildcard.
			if ( strpos( $entry, '*' ) !== false ) {
				if ( $this->ip_matches_wildcard( $ip, $entry ) ) {
					return true;
				}
				continue;
			}

			// Exact match.
			if ( $ip === $entry ) {
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
		$parts = explode( '/', $cidr );
		if ( count( $parts ) !== 2 ) {
			return false;
		}

		list( $subnet, $mask ) = $parts;
		$mask = (int) $mask;

		// Handle IPv4.
		if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 ) && filter_var( $subnet, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 ) ) {
			if ( $mask < 0 || $mask > 32 ) {
				return false;
			}

			$ip_long     = ip2long( $ip );
			$subnet_long = ip2long( $subnet );
			$mask_long   = $mask > 0 ? ( -1 << ( 32 - $mask ) ) : 0;

			return ( $ip_long & $mask_long ) === ( $subnet_long & $mask_long );
		}

		// Handle IPv6 (basic support).
		if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6 ) && filter_var( $subnet, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6 ) ) {
			if ( $mask < 0 || $mask > 128 ) {
				return false;
			}

			$ip_bin     = $this->inet_pton_binary( $ip );
			$subnet_bin = $this->inet_pton_binary( $subnet );

			if ( false === $ip_bin || false === $subnet_bin ) {
				return false;
			}

			$mask_bin = str_repeat( '1', $mask ) . str_repeat( '0', 128 - $mask );

			$ip_masked     = '';
			$subnet_masked = '';

			for ( $i = 0; $i < 128; $i++ ) {
				$ip_masked     .= $ip_bin[ $i ] & $mask_bin[ $i ];
				$subnet_masked .= $subnet_bin[ $i ] & $mask_bin[ $i ];
			}

			return $ip_masked === $subnet_masked;
		}

		return false;
	}

	/**
	 * Convert IP address to binary string.
	 *
	 * @since 1.0.0
	 * @param string $ip IP address.
	 * @return string|false Binary representation or false on error.
	 */
	private function inet_pton_binary( $ip ) {
		$packed = inet_pton( $ip );
		if ( false === $packed ) {
			return false;
		}

		$binary = '';
		foreach ( str_split( $packed ) as $char ) {
			$binary .= str_pad( decbin( ord( $char ) ), 8, '0', STR_PAD_LEFT );
		}

		return $binary;
	}

	/**
	 * Check if an IP matches a wildcard pattern.
	 *
	 * @since 1.0.0
	 * @param string $ip      IP address to check.
	 * @param string $pattern Wildcard pattern (e.g., 192.168.1.*).
	 * @return bool True if matches.
	 */
	private function ip_matches_wildcard( $ip, $pattern ) {
		// Convert wildcard to regex.
		$regex = str_replace(
			array( '.', '*' ),
			array( '\\.', '\\d+' ),
			$pattern
		);
		$regex = '/^' . $regex . '$/';

		return (bool) preg_match( $regex, $ip );
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
}
