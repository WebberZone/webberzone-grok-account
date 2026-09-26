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
 * Replaces core's API-key card for this provider on Settings → Connectors with a "Sign in with xAI" card.
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
		wp_enqueue_script_module(
			self::MODULE,
			WZGKA_PLUGIN_URL . 'assets/js/connectors.js',
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
	 * Data passed to the script module.
	 *
	 * @since 1.0.0
	 *
	 * @param  array $data Existing data.
	 * @return array
	 */
	public static function module_data( $data ) {
		$tokens = Token_Store::get();
		return array_merge(
			(array) $data,
			array(
				'slug'        => PROVIDER_ID,
				'ajaxUrl'     => admin_url( 'admin-ajax.php' ),
				'nonce'       => wp_create_nonce( Admin::NONCE ),
				'connected'   => null !== $tokens,
				'email'       => $tokens['email'] ?? '',
				'name'        => $tokens['name'] ?? '',
				'settingsUrl' => admin_url( 'options-general.php?page=' . Admin::PAGE ),
				'note'        => __( 'Sign in with an xAI account that has SuperGrok or X Premium.', 'webberzone-grok-account' ),
			)
		);
	}
}
