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
