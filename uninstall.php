<?php
/**
 * Clean up when the plugin is deleted.
 *
 * @package Pinglet
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'pinglet_settings' );

// Remove any leftover test-notification result transients.
// The transients are keyed per user, so there's no list of names to delete
// one by one; a one-off query on uninstall needs no caching.
global $wpdb;
$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	"DELETE FROM {$wpdb->options}
	 WHERE option_name LIKE '\_transient\_pinglet\_test\_result\_%'
	    OR option_name LIKE '\_transient\_timeout\_pinglet\_test\_result\_%'"
);
