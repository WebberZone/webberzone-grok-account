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
			wp_send_json_error( html_entity_decode( $e->getMessage(), ENT_QUOTES ) );
		}
	}

	/**
	 * AJAX: abandons the pending device code.
	 *
	 * @since 1.0.0
	 */
	public static function ajax_cancel() {
		self::verify();
		OAuth::cancel_device_flow();
		wp_send_json_success();
	}

	/**
	 * AJAX: disconnects the account.
	 *
	 * @since 1.0.0
	 */
	public static function ajax_disconnect() {
		self::verify();
		OAuth::cancel_device_flow();
		Token_Store::clear();
		wp_send_json_success();
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
}
