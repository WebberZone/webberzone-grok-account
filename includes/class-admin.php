<?php
/**
 * AJAX handlers for the Connectors card.
 *
 * @package WebberZone\Grok_Account
 */

namespace WebberZone\Grok_Account;

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * AJAX endpoints used by the Connectors card, plus the Plugins screen link.
 *
 * @since 1.0.0
 */
class Admin {


	/**
	 * Nonce action shared by all AJAX requests.
	 *
	 * @var string
	 */
	const NONCE = 'wzgka_admin';

	/**
	 * Transient prefix, followed by the user ID, flagging that the last disconnect could not revoke the sign-in.
	 *
	 * @since 1.0.4
	 *
	 * @var string
	 */
	const REVOKE_FAILED = 'wzgka_revoke_failed_';

	/**
	 * Registers the hooks.
	 *
	 * @since 1.0.0
	 */
	public static function init() {
		add_action( 'wp_ajax_wzgka_start', array( __CLASS__, 'ajax_start' ) );
		add_action( 'wp_ajax_wzgka_poll', array( __CLASS__, 'ajax_poll' ) );
		add_action( 'wp_ajax_wzgka_cancel', array( __CLASS__, 'ajax_cancel' ) );
		add_action( 'wp_ajax_wzgka_disconnect', array( __CLASS__, 'ajax_disconnect' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( WZGKA_PLUGIN_FILE ), array( __CLASS__, 'action_links' ) );
		add_action( 'admin_notices', array( __CLASS__, 'salt_notice' ) );
		add_action( 'admin_notices', array( __CLASS__, 'revoke_notice' ) );
	}

	/**
	 * Adds a Settings link to Settings → Connectors on the Plugins screen.
	 *
	 * @since 1.0.0
	 *
	 * @param  array $links Action links.
	 * @return array
	 */
	public static function action_links( $links ) {
		array_unshift( $links, '<a href="' . esc_url( admin_url( 'options-connectors.php' ) ) . '">' . esc_html__( 'Settings', 'webberzone-grok-account' ) . '</a>' );
		return $links;
	}

	/**
	 * AJAX: starts the device-code flow.
	 *
	 * @since 1.0.0
	 */
	public static function ajax_start() {
		self::verify();
		try {
			wp_send_json_success( OAuth::start_device_flow() );
		} catch ( \Exception $e ) {
			wp_send_json_error( html_entity_decode( $e->getMessage(), ENT_QUOTES ) );
		}
	}

	/**
	 * AJAX: checks whether the device code was approved.
	 *
	 * @since 1.0.0
	 */
	public static function ajax_poll() {
		self::verify();
		try {
			wp_send_json_success( OAuth::poll_device_flow() );
		} catch ( \Exception $e ) {
			wp_send_json_error(
				array(
					'message'   => html_entity_decode( $e->getMessage(), ENT_QUOTES ),
					'retryable' => in_array( $e->getCode(), array( 408, 429 ), true ) || $e->getCode() >= 500,
				)
			);
		}
	}

	/**
	 * AJAX: abandons the pending device code.
	 *
	 * @since 1.0.0
	 */
	public static function ajax_cancel() {
		self::verify();
		try {
			OAuth::cancel_device_flow();
			wp_send_json_success();
		} catch ( \Exception $e ) {
			wp_send_json_error( html_entity_decode( $e->getMessage(), ENT_QUOTES ) );
		}
	}

	/**
	 * AJAX: disconnects the account.
	 *
	 * @since 1.0.0
	 */
	public static function ajax_disconnect() {
		self::verify();
		try {
			if ( ! OAuth::disconnect() ) {
				set_transient( self::REVOKE_FAILED . get_current_user_id(), 1, 5 * MINUTE_IN_SECONDS );
			}
			wp_send_json_success();
		} catch ( \Exception $e ) {
			wp_send_json_error( html_entity_decode( $e->getMessage(), ENT_QUOTES ) );
		}
	}

	/**
	 * Verifies the AJAX nonce and capability.
	 *
	 * @since 1.0.0
	 */
	private static function verify() {
		check_ajax_referer( self::NONCE );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Sorry, you are not allowed to do that.', 'webberzone-grok-account' ), 403 );
		}
	}

	/**
	 * Warns on Settings → Connectors when the authentication salts live in the database rather than wp-config.php.
	 *
	 * @since 1.0.3
	 */
	public static function salt_notice() {
		if ( ! self::on_connectors_screen() || self::salts_in_config() ) {
			return;
		}
		printf(
			'<div class="notice notice-warning"><p>%s</p></div>',
			esc_html(
				sprintf(
					/* translators: %s: provider label. */
					__( 'The key that encrypts the stored %s sign-in is derived from AUTH_KEY and AUTH_SALT. Define them in wp-config.php so the key is not stored in the database alongside the tokens. Changing them disconnects the account, so sign in again afterwards.', 'webberzone-grok-account' ),
					Config::label()
				)
			)
		);
	}

	/**
	 * Warns on Settings → Connectors, once, when the last disconnect could not revoke the sign-in with the provider.
	 *
	 * @since 1.0.4
	 */
	public static function revoke_notice() {
		$key = self::REVOKE_FAILED . get_current_user_id();
		if ( ! self::on_connectors_screen() || ! get_transient( $key ) ) {
			return;
		}
		delete_transient( $key );
		printf(
			'<div class="notice notice-warning is-dismissible"><p>%s</p></div>',
			esc_html(
				sprintf(
					/* translators: %s: service name. */
					__( 'The account was disconnected from this site, but %s did not confirm that the sign-in was revoked. To make sure it can no longer be used, sign out of all sessions in your account settings.', 'webberzone-grok-account' ),
					Config::VENDOR
				)
			)
		);
	}

	/**
	 * Whether the current screen is Settings → Connectors and the user can manage it.
	 *
	 * @since 1.0.4
	 *
	 * @return bool
	 */
	private static function on_connectors_screen() {
		$screen = get_current_screen();
		return $screen && 'options-connectors' === $screen->id && current_user_can( 'manage_options' );
	}

	/**
	 * Whether wp_salt( 'auth' ) is backed by constants in wp-config.php.
	 *
	 * @since 1.0.3
	 *
	 * @return bool
	 */
	private static function salts_in_config() {
		$salt = wp_salt( 'auth' );
		foreach ( array( 'auth_key', 'auth_salt' ) as $option ) {
			$value = get_site_option( $option );
			if ( is_string( $value ) && '' !== $value && false !== strpos( $salt, $value ) ) {
				return false;
			}
		}
		return true;
	}
}
