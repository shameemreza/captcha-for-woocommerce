<?php
/**
 * Uninstall handler for Captcha for WooCommerce.
 *
 * Runs when the plugin is deleted from WordPress.
 * Only cleans up data if the "Delete Data on Uninstall" option is enabled.
 *
 * @package Captcha_For_WooCommerce
 * @since   1.0.0
 */

// Exit if accessed directly or not through WordPress uninstall.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

/**
 * Check if we should delete data.
 *
 * Only proceed if the admin has enabled the "Delete Data on Uninstall" option.
 * This respects user choice and prevents accidental data loss.
 */
$cfwc_delete_data = get_option( 'cfwc_delete_data_on_uninstall', 'no' );

// Exit early if data deletion is not enabled.
if ( 'yes' !== $cfwc_delete_data ) {
	return;
}

/**
 * Clean up plugin data on uninstall.
 *
 * Removes all options, transients, and statistics created by the plugin.
 */

// Delete main settings option.
delete_option( 'cfwc_settings' );

// Delete individual WooCommerce settings options.
$cfwc_wc_options = array(
	'cfwc_provider',
	'cfwc_site_key',
	'cfwc_secret_key',
	'cfwc_theme',
	'cfwc_size',
	'cfwc_score_threshold',
	'cfwc_forms',
	'cfwc_whitelist_logged_in',
	'cfwc_whitelist_roles',
	'cfwc_whitelist_ips',
	'cfwc_blocklist_ips',
	'cfwc_enable_honeypot',
	'cfwc_honeypot_min_time',
	'cfwc_failsafe_mode',
	'cfwc_enable_debug_logging',
	'cfwc_delete_data_on_uninstall',
	'cfwc_enable_rate_limiting',
	'cfwc_rate_limit_requests',
	'cfwc_rate_limit_lockout',
	'cfwc_rate_limit_window',
);

foreach ( $cfwc_wc_options as $cfwc_option ) {
	delete_option( $cfwc_option );
}

// Delete version and status options.
delete_option( 'cfwc_version' );
delete_option( 'cfwc_welcome_notice_dismissed' );
delete_option( 'cfwc_config_notice_dismissed' );

// Delete rate limiter data.
delete_option( 'cfwc_failed_attempts' );
delete_option( 'cfwc_lockouts' );
delete_option( 'cfwc_attempt_timestamps' );

// Delete statistics.
delete_option( 'cfwc_protection_stats' );

// Delete transients.
delete_transient( 'cfwc_connection_test' );

// Clean up any scheduled events.
wp_clear_scheduled_hook( 'cfwc_cleanup' );

// For multisite, clean up each site.
if ( is_multisite() ) {
	global $wpdb;

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Required for multisite cleanup during uninstall.
	$cfwc_blog_ids = $wpdb->get_col( "SELECT blog_id FROM {$wpdb->blogs}" );

	foreach ( $cfwc_blog_ids as $cfwc_blog_id ) {
		switch_to_blog( $cfwc_blog_id );

		// Delete options for this site.
		delete_option( 'cfwc_settings' );
		delete_option( 'cfwc_version' );
		delete_option( 'cfwc_welcome_notice_dismissed' );
		delete_option( 'cfwc_config_notice_dismissed' );
		delete_option( 'cfwc_failed_attempts' );
		delete_option( 'cfwc_lockouts' );
		delete_option( 'cfwc_attempt_timestamps' );
		delete_option( 'cfwc_protection_stats' );

		foreach ( $cfwc_wc_options as $cfwc_option ) {
			delete_option( $cfwc_option );
		}

		delete_transient( 'cfwc_connection_test' );
		wp_clear_scheduled_hook( 'cfwc_cleanup' );

		restore_current_blog();
	}
}
