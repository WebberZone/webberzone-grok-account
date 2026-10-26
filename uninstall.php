<?php
/**
 * Uninstall routine: revokes the sign-in and removes the stored tokens and caches on every site.
 *
 * @package WebberZone\Grok_Account
 */

// If uninstall is not called from WordPress, exit.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

foreach ( array( 'config', 'token-store', 'oauth-client', 'oauth' ) as $wzgka_class ) {
	require_once __DIR__ . '/includes/class-' . $wzgka_class . '.php';
}

$wzgka_uninstall_site = static function () {
	global $wpdb;

	try {
		\WebberZone\Grok_Account\OAuth::disconnect();
	} catch ( \Exception $e ) {
		// The data below is deleted even if the lock times out.
		unset( $e );
	}

	delete_option( 'wzgka_tokens' );
	delete_option( 'wzgka_refresh_lock' );
	delete_transient( 'wzgka_models' );
	delete_transient( 'wzgka_models_failed' );
	// Pending sign-ins and notices; with an external object cache they expire on their own within 15 minutes.
	$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->prepare(
			"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
			$wpdb->esc_like( '_transient_wzgka_' ) . '%',
			$wpdb->esc_like( '_transient_timeout_wzgka_' ) . '%'
		)
	);
};

if ( is_multisite() ) {
	foreach ( get_sites(
		array(
			'fields' => 'ids',
			'number' => 0,
		)
	) as $wzgka_site_id ) {
		switch_to_blog( (int) $wzgka_site_id );
		$wzgka_uninstall_site();
		restore_current_blog();
	}
} else {
	$wzgka_uninstall_site();
}
