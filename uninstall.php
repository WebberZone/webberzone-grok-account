<?php
/**
 * Uninstall routine: removes the stored tokens and caches.
 *
 * @package WebberZone\Grok_Account
 */

// If uninstall is not called from WordPress, exit.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'wzgka_tokens' );
delete_option( 'wzgka_refresh_lock' );
delete_transient( 'wzgka_models' );
delete_transient( 'wzgka_models_failed' );

global $wpdb;
$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	$wpdb->prepare(
		"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
		$wpdb->esc_like( '_transient_wzgka_flow_' ) . '%',
		$wpdb->esc_like( '_transient_timeout_wzgka_flow_' ) . '%'
	)
);
