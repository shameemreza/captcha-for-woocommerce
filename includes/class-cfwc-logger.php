<?php
/**
 * Debug Logger.
 *
 * Handles logging of CAPTCHA verification attempts and errors
 * using WooCommerce's logging system.
 *
 * @package Captcha_For_WooCommerce
 * @since   1.0.0
 */

namespace CFWC;

// Prevent direct file access.
defined( 'ABSPATH' ) || exit;

/**
 * Logger class.
 *
 * Provides logging functionality for debugging CAPTCHA issues.
 * Uses WooCommerce's WC_Logger when available.
 *
 * @since 1.0.0
 */
class Logger {

	/**
	 * Log source identifier.
	 *
	 * @var string
	 */
	const LOG_SOURCE = 'captcha-for-woocommerce';

	/**
	 * Log a message.
	 *
	 * @since 1.0.0
	 * @param string $message The message to log.
	 * @param array  $context Additional context data.
	 * @param string $level   Log level (debug, info, notice, warning, error).
	 * @return void
	 */
	public static function log( $message, $context = array(), $level = 'info' ) {
		// Check if debug logging is enabled.
		if ( 'yes' !== Plugin::instance()->settings()->get( 'enable_debug_logging' ) ) {
			return;
		}

		// Use WooCommerce logger if available.
		if ( function_exists( 'wc_get_logger' ) ) {
			$logger = wc_get_logger();

			$logger->log(
				$level,
				$message,
				array_merge(
					$context,
					array( 'source' => self::LOG_SOURCE )
				)
			);
		} else {
			// Fallback to error_log.
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( sprintf( '[%s] [%s] %s', self::LOG_SOURCE, $level, $message ) );
		}
	}

	/**
	 * Log a verification attempt.
	 *
	 * @since 1.0.0
	 * @param string         $form_type The form being verified.
	 * @param bool|\WP_Error $result    Verification result.
	 * @param array          $response  Optional. Provider response data.
	 * @return void
	 */
	public static function log_verification( $form_type, $result, $response = array() ) {
		$is_success = ! is_wp_error( $result );

		$message = sprintf(
			'CAPTCHA verification for %s: %s',
			$form_type,
			$is_success ? 'SUCCESS' : $result->get_error_message()
		);

		$context = array(
			'form_type'  => $form_type,
			'ip'         => self::get_client_ip(),
			'user_id'    => get_current_user_id(),
			'user_agent' => isset( $_SERVER['HTTP_USER_AGENT'] )
				? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) )
				: '',
		);

		if ( ! empty( $response ) ) {
			$context['response'] = $response;
		}

		$level = $is_success ? 'info' : 'warning';

		self::log( $message, $context, $level );
	}

	/**
	 * Log a provider error.
	 *
	 * @since 1.0.0
	 * @param string $provider_id Provider identifier.
	 * @param string $error       Error message.
	 * @param array  $data        Additional error data.
	 * @return void
	 */
	public static function log_provider_error( $provider_id, $error, $data = array() ) {
		$message = sprintf( 'Provider error (%s): %s', $provider_id, $error );

		self::log( $message, $data, 'error' );
	}

	/**
	 * Log a failsafe event.
	 *
	 * @since 1.0.0
	 * @param string $reason      Reason for failsafe activation.
	 * @param string $action      Action taken (block, honeypot, allow).
	 * @param string $form_type   The form involved.
	 * @return void
	 */
	public static function log_failsafe( $reason, $action, $form_type ) {
		$message = sprintf(
			'Failsafe activated for %s: %s. Action: %s',
			$form_type,
			$reason,
			$action
		);

		self::log( $message, array(), 'notice' );
	}

	/**
	 * Get the client IP address.
	 *
	 * @since 1.0.0
	 * @return string Client IP.
	 */
	private static function get_client_ip() {
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
}
