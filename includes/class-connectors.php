<?php
/**
 * Settings → Connectors integration.
 *
 * @package WebberZone\Grok_Account
 */

namespace WebberZone\Grok_Account;

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Replaces core's API-key card for this provider on Settings → Connectors with a sign-in card.
 *
 * @since 1.0.0
 */
class Connectors {


	/**
	 * Script module ID.
	 *
	 * @var string
	 */
	const MODULE = 'wzgka-connectors';

	/**
	 * Registers the hooks.
	 *
	 * @since 1.0.0
	 */
	public static function init() {
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
		add_filter( 'script_module_data_' . self::MODULE, array( __CLASS__, 'module_data' ) );
	}

	/**
	 * Enqueues the script module on Settings → Connectors.
	 *
	 * The page's boot dependencies are only preloaded, not executed, so the module is enqueued directly.
	 * It registers into the same @wordpress/connectors store, and core keeps an existing custom render.
	 *
	 * @since 1.0.0
	 */
	public static function enqueue() {
		$screen = get_current_screen();
		if ( ! $screen || 'options-connectors' !== $screen->id || ! is_ready() || ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$min_suffix = ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) ? '' : '.min';
		wp_enqueue_script_module(
			self::MODULE,
			WZGKA_PLUGIN_URL . 'assets/js/connectors' . $min_suffix . '.js',
			array(
				array(
					'id'     => '@wordpress/connectors',
					'import' => 'static',
				),
			),
			WZGKA_VERSION
		);
	}

	/**
	 * Data passed to the script module, including all of its (translated) strings.
	 *
	 * @since 1.0.0
	 *
	 * @param  array $data Existing data.
	 * @return array
	 */
	public static function module_data( $data ) {
		$tokens = Token_Store::get();
		$note   = Config::note();
		return array_merge(
			(array) $data,
			array(
				'slug'      => PROVIDER_ID,
				'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
				'nonce'     => wp_create_nonce( Admin::NONCE ),
				'connected' => null !== $tokens,
				'account'   => null !== $tokens ? Config::account_summary( $tokens ) : '',
				'note'      => $note['text'],
				'noteUrl'   => $note['url'],
				'strings'   => array(
					'signIn'        => Config::sign_in_label(),
					'deviceStep'    => Config::device_step(),
					'enterCode'     => __( '2. Enter this code:', 'webberzone-grok-account' ),
					'requesting'    => __( 'Requesting a sign-in code…', 'webberzone-grok-account' ),
					'waiting'       => __( 'Waiting for you to approve the sign-in. The code expires in 15 minutes.', 'webberzone-grok-account' ),
					'copy'          => __( 'Copy', 'webberzone-grok-account' ),
					'copied'        => __( 'Copied', 'webberzone-grok-account' ),
					'tryAgain'      => __( 'Try again', 'webberzone-grok-account' ),
					'cancel'        => __( 'Cancel', 'webberzone-grok-account' ),
					'connected'     => __( 'Connected', 'webberzone-grok-account' ),
					'disconnect'    => __( 'Disconnect', 'webberzone-grok-account' ),
					'requestFailed' => __( 'Request failed.', 'webberzone-grok-account' ),
				),
			)
		);
	}
}
