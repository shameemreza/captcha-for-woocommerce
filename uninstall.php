<?php
/**
 * Uninstall handler for Captcha for WooCommerce.
 *
 * Runs when the plugin is deleted from WordPress.
 * Cleans up all plugin data from the database.
 *
 * @package Captcha_For_WooCommerce
 * @since   1.0.0
 */

// Exit if accessed directly or not through WordPress uninstall.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

/**
 * Clean up plugin data on uninstall.
 *
 * Removes all options and transients created by the plugin.
 */

// Delete main settings option.
delete_option( 'cfwc_settings' );

// Delete individual WooCommerce settings options.
$cfwc_options = array(
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
	'cfwc_enable_honeypot',
	'cfwc_honeypot_min_time',
	'cfwc_failsafe_mode',
	'cfwc_enable_debug_logging',
);

foreach ( $cfwc_options as $cfwc_option ) {
	delete_option( $cfwc_option );
}

// Delete version option.
delete_option( 'cfwc_version' );

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

		foreach ( $cfwc_options as $cfwc_option ) {
			delete_option( $cfwc_option );
		}

		delete_transient( 'cfwc_connection_test' );

		restore_current_blog();
	}
}
