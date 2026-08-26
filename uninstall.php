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
global $wpdb;
$wpdb->query(
	"DELETE FROM {$wpdb->options}
	 WHERE option_name LIKE '\_transient\_pinglet\_test\_result\_%'
	    OR option_name LIKE '\_transient\_timeout\_pinglet\_test\_result\_%'"
);
