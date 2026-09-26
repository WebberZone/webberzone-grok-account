<?php
/**
 * WebberZone Grok Account.
 *
 * Adds a "Grok Account" provider to the WordPress AI Client that signs in with your SuperGrok or X Premium subscription instead of an xAI API key.
 *
 * @package   WebberZone\Grok_Account
 * @author    WebberZone
 * @license   GPL-2.0+
 * @link      https://webberzone.com
 * @copyright 2026 WebberZone
 *
 * @wordpress-plugin
 * Plugin Name: WebberZone Grok Account
 * Plugin URI:  https://github.com/WebberZone/webberzone-grok-account/
 * Description: Use your SuperGrok or X Premium subscription for Grok text and image generation in the WordPress AI Client, signing in with a device code instead of an xAI API key.
 * Version:     1.0.0
 * Author:      WebberZone
 * Author URI:  https://webberzone.com
 * License:     GPL-2.0+
 * License URI: https://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: webberzone-grok-account
 * Domain Path: /languages
 * Requires PHP: 7.4
 * Requires at least: 7.0
 * GitHub Plugin URI: https://github.com/WebberZone/webberzone-grok-account/
 */

namespace WebberZone\Grok_Account;

use WordPress\AiClient\AiClient;

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Holds the plugin version.
 *
 * @since 1.0.0
 */
if ( ! defined( 'WZGKA_VERSION' ) ) {
	define( 'WZGKA_VERSION', '1.0.0' );
}

/**
 * Holds the filesystem directory path (with trailing slash) for this plugin.
 *
 * @since 1.0.0
 */
if ( ! defined( 'WZGKA_PLUGIN_DIR' ) ) {
	define( 'WZGKA_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
}

/**
 * Holds the URL (with trailing slash) for this plugin.
 *
 * @since 1.0.0
 */
if ( ! defined( 'WZGKA_PLUGIN_URL' ) ) {
	define( 'WZGKA_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
}

/**
 * Holds the main plugin file path.
 *
 * @since 1.0.0
 */
if ( ! defined( 'WZGKA_PLUGIN_FILE' ) ) {
	define( 'WZGKA_PLUGIN_FILE', __FILE__ );
}

/**
 * AI Client provider ID. Also drives the connector slug and setting names in core.
 *
 * @since 1.0.0
 */
const PROVIDER_ID = 'grok-account';

/**
 * Autoloads the plugin classes from includes/class-*.php.
 *
 * @since 1.0.0
 *
 * @param string $class_name Fully qualified class name.
 */
function autoload( $class_name ) {
	$prefix = __NAMESPACE__ . '\\';
	if ( 0 !== strpos( $class_name, $prefix ) ) {
		return;
	}
	$file = WZGKA_PLUGIN_DIR . 'includes/class-' . str_replace( '_', '-', strtolower( substr( $class_name, strlen( $prefix ) ) ) ) . '.php';
	if ( is_readable( $file ) ) {
		include_once $file;
	}
}
spl_autoload_register( __NAMESPACE__ . '\autoload' );

/**
 * Checks that the WordPress AI Client and the base classes this plugin extends are available.
 *
 * The models subclass the AI Client's OpenAI-compatible base classes, so a missing class, or a hook
 * method that was renamed or made final, would be an uncatchable fatal error when our classes load.
 * Everything that touches those subclasses is gated behind this check.
 *
 * @since 1.0.0
 *
 * @return bool True if all dependencies are available and compatible.
 */
function is_ready() {
	static $ready = null;
	if ( null !== $ready ) {
		return $ready;
	}

	$base     = 'WordPress\\AiClient\\Providers\\OpenAiCompatibleImplementation\\';
	$required = array(
		$base . 'AbstractOpenAiCompatibleTextGenerationModel'  => array( 'createRequest', 'prepareGenerateTextParams' ),
		$base . 'AbstractOpenAiCompatibleImageGenerationModel' => array( 'createRequest', 'prepareGenerateImageParams' ),
	);

	$ready = class_exists( AiClient::class );
	foreach ( $required as $class_name => $methods ) {
		if ( ! $ready || ! class_exists( $class_name ) ) {
			$ready = false;
			break;
		}
		foreach ( $methods as $method ) {
			if ( ! method_exists( $class_name, $method ) || ( new \ReflectionMethod( $class_name, $method ) )->isFinal() ) {
				$ready = false;
				break 2;
			}
		}
	}

	return $ready;
}

/**
 * Registers the provider with the AI Client.
 *
 * @since 1.0.0
 */
function register_provider() {
	if ( ! is_ready() ) {
		return;
	}
	$registry = AiClient::defaultRegistry();
	if ( ! $registry->hasProvider( Provider::class ) ) {
		$registry->registerProvider( Provider::class );
	}
}
add_action( 'init', __NAMESPACE__ . '\register_provider', 6 );

/**
 * Attaches the Grok account authentication to the provider.
 *
 * Runs after core's init:20 connector wiring, which would otherwise install a plain API-key authentication.
 *
 * @since 1.0.0
 */
function attach_authentication() {
	if ( ! is_ready() ) {
		return;
	}
	try {
		AiClient::defaultRegistry()->setProviderRequestAuthentication( PROVIDER_ID, new Account_Authentication() );
	} catch ( \Exception $e ) {
		wp_trigger_error( __FUNCTION__, $e->getMessage() );
	}
}
add_action( 'init', __NAMESPACE__ . '\attach_authentication', 30 );

/**
 * Tells the AI plugin that credentials are available when a Grok account is connected.
 *
 * @since 1.0.0
 *
 * @param  bool $has_support Whether credentials or image support are already available.
 * @return bool
 */
function report_connected( $has_support ) {
	return $has_support || ( is_ready() && Token_Store::is_connected() );
}
add_filter( 'wpai_has_ai_credentials', __NAMESPACE__ . '\report_connected' );
add_filter( 'wpai_has_image_generation_support', __NAMESPACE__ . '\report_connected' );

/**
 * Warn in wp-admin if the dependencies are missing.
 *
 * @since 1.0.0
 */
function admin_notice_missing_dependencies() {
	if ( is_ready() || ! current_user_can( 'activate_plugins' ) ) {
		return;
	}

	printf(
		'<div class="notice notice-warning"><p>%s</p></div>',
		esc_html__( 'WebberZone Grok Account is inactive: it needs WordPress 7.0 or later, or a compatible version of the WordPress AI Client.', 'webberzone-grok-account' )
	);
}
add_action( 'admin_notices', __NAMESPACE__ . '\admin_notice_missing_dependencies' );

if ( is_admin() ) {
	Admin::init();
	Connectors::init();
}
