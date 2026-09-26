<?php
/**
 * Settings page and AJAX handlers.
 *
 * @package WebberZone\Grok_Account
 */

namespace WebberZone\Grok_Account;

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Settings page for the provider, plus the AJAX endpoints used by it and the Connectors card.
 *
 * @since 1.0.0
 */
class Admin {


	/**
	 * Settings page slug.
	 *
	 * @var string
	 */
	const PAGE = PROVIDER_ID;

	/**
	 * Nonce action shared by all AJAX and form requests.
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
		add_action( 'admin_menu', array( __CLASS__, 'add_page' ) );
		add_action( 'wp_ajax_wzgka_start', array( __CLASS__, 'ajax_start' ) );
		add_action( 'wp_ajax_wzgka_poll', array( __CLASS__, 'ajax_poll' ) );
		add_action( 'wp_ajax_wzgka_cancel', array( __CLASS__, 'ajax_cancel' ) );
		add_action( 'wp_ajax_wzgka_disconnect', array( __CLASS__, 'ajax_disconnect' ) );
		add_action( 'admin_post_wzgka_disconnect', array( __CLASS__, 'disconnect' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( WZGKA_PLUGIN_FILE ), array( __CLASS__, 'action_links' ) );
	}

	/**
	 * Adds the settings page.
	 *
	 * @since 1.0.0
	 */
	public static function add_page() {
		add_options_page( Config::label(), Config::label(), 'manage_options', self::PAGE, array( __CLASS__, 'render' ) );
	}

	/**
	 * Adds a Settings link on the Plugins screen.
	 *
	 * @since 1.0.0
	 *
	 * @param  array $links Action links.
	 * @return array
	 */
	public static function action_links( $links ) {
		array_unshift( $links, '<a href="' . esc_url( admin_url( 'options-general.php?page=' . self::PAGE ) ) . '">' . esc_html__( 'Settings', 'webberzone-grok-account' ) . '</a>' );
		return $links;
	}

	/**
	 * Escapes text and turns its <a></a> placeholder into a link to the given URL.
	 *
	 * @since 1.0.0
	 *
	 * @param  string $text Text with an optional <a></a> placeholder.
	 * @param  string $url  Link URL. Without one, the placeholder tags are dropped.
	 * @return string Safe HTML.
	 */
	public static function linked_text( $text, $url ) {
		$html = esc_html( $text );
		$open = '' !== $url ? '<a href="' . esc_url( $url ) . '" target="_blank" rel="noopener noreferrer">' : '';
		return str_replace( array( '&lt;a&gt;', '&lt;/a&gt;' ), array( $open, '' !== $url ? '</a>' : '' ), $html );
	}

	/**
	 * Renders the settings page.
	 *
	 * @since 1.0.0
	 */
	public static function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$tokens = Token_Store::get();
		$note   = Config::note();
		?>
		<div class="wrap">
			<h1><?php echo esc_html( Config::label() ); ?></h1>
			<p><?php echo esc_html( Config::intro() ); ?></p>

		<?php if ( ! is_ready() ) : ?>
				<div class="notice notice-error inline"><p><?php echo esc_html( Config::requirements() ); ?></p></div>
		<?php endif; ?>

		<?php if ( isset( $_GET['disconnected'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Display-only flag. ?>
				<div class="notice notice-success inline"><p><?php esc_html_e( 'Disconnected.', 'webberzone-grok-account' ); ?></p></div>
		<?php endif; ?>

		<?php if ( $tokens ) : ?>
				<table class="form-table" role="presentation">
					<tr><th scope="row"><?php esc_html_e( 'Status', 'webberzone-grok-account' ); ?></th><td><strong style="color:#008a20"><?php esc_html_e( 'Connected', 'webberzone-grok-account' ); ?></strong></td></tr>
			<?php foreach ( Config::account_rows( $tokens ) as $label => $value ) : ?>
						<tr><th scope="row"><?php echo esc_html( $label ); ?></th><td><?php echo esc_html( '' !== $value ? $value : '—' ); ?></td></tr>
			<?php endforeach; ?>
					<tr><th scope="row"><?php esc_html_e( 'Last token update', 'webberzone-grok-account' ); ?></th><td><?php echo esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), (int) $tokens['updated_at'] ) ); ?></td></tr>
				</table>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="wzgka_disconnect" />
			<?php wp_nonce_field( self::NONCE ); ?>
			<?php submit_button( __( 'Disconnect', 'webberzone-grok-account' ), 'secondary', 'submit', false ); ?>
				</form>
			<?php else : ?>
				<p><?php echo self::linked_text( $note['text'], $note['url'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in linked_text(). ?></p>
				<p><button type="button" class="button button-primary" id="wzgka-connect"><?php echo esc_html( Config::sign_in_label() ); ?></button></p>
				<div id="wzgka-flow" hidden>
					<p id="wzgka-step"></p>
					<p><?php esc_html_e( '2. Enter this code:', 'webberzone-grok-account' ); ?></p>
					<p><code id="wzgka-code" style="font-size:2em;padding:.4em .6em;letter-spacing:.1em;user-select:all"></code></p>
					<p id="wzgka-status" class="description"></p>
				</div>
				<div id="wzgka-error" class="notice notice-error inline" hidden><p></p></div>
				<script>
				( function () {
					const ajax = <?php echo wp_json_encode( admin_url( 'admin-ajax.php' ) ); ?>;
					const nonce = <?php echo wp_json_encode( wp_create_nonce( self::NONCE ) ); ?>;
					const stepText = <?php echo wp_json_encode( Config::device_step() ); ?>;
					const $ = ( id ) => document.getElementById( id );
					const call = ( action ) => fetch( ajax, {
						method: 'POST',
						credentials: 'same-origin',
						body: new URLSearchParams( { action, _ajax_nonce: nonce } ),
					} ).then( ( r ) => r.json() );
					const fail = ( msg ) => {
						$( 'wzgka-flow' ).hidden = true;
						$( 'wzgka-error' ).hidden = false;
						$( 'wzgka-error' ).querySelector( 'p' ).textContent = msg;
						$( 'wzgka-connect' ).disabled = false;
					};
					const poll = ( interval ) => setTimeout( () => {
						call( 'wzgka_poll' ).then( ( res ) => {
							if ( ! res.success ) return fail( res.data );
							if ( 'connected' === res.data ) {
								$( 'wzgka-status' ).textContent = <?php echo wp_json_encode( __( 'Connected. Reloading…', 'webberzone-grok-account' ) ); ?>;
								return location.reload();
							}
							poll( interval );
						} ).catch( () => poll( interval ) );
					}, interval * 1000 );

					$( 'wzgka-connect' ).addEventListener( 'click', () => {
						$( 'wzgka-connect' ).disabled = true;
						$( 'wzgka-error' ).hidden = true;
						call( 'wzgka_start' ).then( ( res ) => {
							if ( ! res.success ) return fail( res.data );
							const link = document.createElement( 'a' );
							link.href = res.data.verification_url;
							link.target = '_blank';
							link.rel = 'noopener noreferrer';
							const parts = stepText.split( /<\/?a>/ );
							link.textContent = parts[ 1 ] || res.data.verification_url;
							$( 'wzgka-step' ).replaceChildren( document.createTextNode( parts[ 0 ] || '' ), link, document.createTextNode( parts[ 2 ] || '' ) );
							$( 'wzgka-code' ).textContent = res.data.user_code;
							$( 'wzgka-status' ).textContent = <?php echo wp_json_encode( __( 'Waiting for you to approve the sign-in… (the code expires in 15 minutes)', 'webberzone-grok-account' ) ); ?>;
							$( 'wzgka-flow' ).hidden = false;
							poll( res.data.interval );
						} ).catch( ( e ) => fail( String( e ) ) );
					} );
				} )();
				</script>
			<?php endif; ?>
		</div>
		<?php
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
	 * Form handler: disconnects the account from the settings page.
	 *
	 * @since 1.0.0
	 */
	public static function disconnect() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to do that.', 'webberzone-grok-account' ), 403 );
		}
		check_admin_referer( self::NONCE );
		OAuth::cancel_device_flow();
		Token_Store::clear();
		wp_safe_redirect( admin_url( 'options-general.php?page=' . self::PAGE . '&disconnected=1' ) );
		exit;
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
