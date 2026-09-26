<?php
/**
 * Shared OAuth plumbing: token refresh with locking, pending device codes and HTTP helpers.
 *
 * @package WebberZone\Grok_Account
 */

namespace WebberZone\Grok_Account;

use RuntimeException;

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Base class for the provider's OAuth implementation.
 *
 * Subclasses implement the provider-specific device-code flow, the refresh request and how token
 * responses are stored. Everything else, including the refresh lock, lives here.
 *
 * @since 1.0.0
 */
abstract class OAuth_Client {


	/**
	 * Fallback lifetime of a device code, in seconds.
	 *
	 * @var int
	 */
	const FLOW_TTL = 900;

	/**
	 * Option used as a refresh lock.
	 *
	 * @var string
	 */
	const LOCK_OPTION = 'wzgka_refresh_lock';

	/**
	 * Refresh access tokens this many seconds before they expire. Subclasses override this.
	 *
	 * @var int
	 */
	const REFRESH_MARGIN = 300;

	/**
	 * Requests a device code and stores the pending flow for the current user.
	 *
	 * @since 1.0.0
	 *
	 * @return array User code, verification URL, polling interval and expiry timestamp.
	 * @throws RuntimeException When the provider rejects the request.
	 */
	abstract public static function start_device_flow();

	/**
	 * Checks once whether the user approved the pending device code, and stores the tokens if so.
	 *
	 * @since 1.0.0
	 *
	 * @return string 'pending' or 'connected'.
	 * @throws RuntimeException When the flow expired, was denied, or the provider returned an error.
	 */
	abstract public static function poll_device_flow();

	/**
	 * Sends the refresh-token request.
	 *
	 * @since 1.0.0
	 *
	 * @param  array $tokens Current token data.
	 * @return array HTTP response.
	 * @throws RuntimeException On transport errors.
	 */
	abstract protected static function request_refresh( array $tokens );

	/**
	 * Saves a token response.
	 *
	 * @since 1.0.0
	 *
	 * @param  array $body     Token endpoint response.
	 * @param  array $previous Previously stored tokens, used for fields the response omits.
	 * @return array Stored token data.
	 * @throws RuntimeException When required fields are missing.
	 */
	abstract protected static function store_tokens( array $body, array $previous = array() );

	/**
	 * Abandons the current user's pending device code.
	 *
	 * @since 1.0.0
	 */
	public static function cancel_device_flow() {
		delete_transient( static::flow_key() );
	}

	/**
	 * Returns tokens that are valid for a while longer, refreshing if needed.
	 *
	 * @since 1.0.0
	 *
	 * @return array Token data.
	 * @throws RuntimeException When not connected or the refresh fails.
	 */
	public static function get_valid_tokens() {
		$tokens = Token_Store::get();
		if ( null === $tokens ) {
			throw new RuntimeException(
				esc_html(
					sprintf(
					/* translators: %s: provider label. */
						__( '%s is not connected. Sign in under Settings → Connectors.', 'webberzone-grok-account' ),
						Config::label()
					)
				)
			);
		}
		if ( static::is_expiring( $tokens ) ) {
			$tokens = static::refresh( false );
		}
		return $tokens;
	}

	/**
	 * Refreshes the access token.
	 *
	 * Refresh tokens may rotate, so only one request may refresh at a time; others wait for its result.
	 *
	 * @since 1.0.0
	 *
	 * @param  bool $force Refresh even if the current token is not about to expire.
	 * @return array Token data.
	 * @throws RuntimeException When not connected or the refresh fails.
	 */
	public static function refresh( $force ) {
		$before = static::fresh_tokens();
		if ( null === $before ) {
			throw new RuntimeException( esc_html( self::not_connected_message() ) );
		}

		if ( ! static::acquire_lock() ) {
			for ( $i = 0; $i < 40; $i++ ) {
				usleep( 250000 );
				$current = static::fresh_tokens();
				if ( null !== $current && $current['access_token'] !== $before['access_token'] ) {
					return $current;
				}
				if ( ! static::lock_held() ) {
					break;
				}
			}
			if ( ! static::acquire_lock() ) {
				throw new RuntimeException( esc_html__( 'Timed out waiting for a token refresh.', 'webberzone-grok-account' ) );
			}
		}

		try {
			$tokens = static::fresh_tokens();
			if ( null === $tokens ) {
				throw new RuntimeException( esc_html( self::not_connected_message() ) );
			}
			if ( $tokens['access_token'] !== $before['access_token'] || ( ! $force && ! static::is_expiring( $tokens ) ) ) {
				return $tokens;
			}

			$response = static::request_refresh( $tokens );
			$status   = (int) wp_remote_retrieve_response_code( $response );
			if ( 400 === $status || 401 === $status ) {
				Token_Store::clear();
				throw new RuntimeException(
					esc_html(
						sprintf(
						/* translators: %s: provider label. */
							__( 'Your %s session has expired or was revoked. Sign in again under Settings → Connectors.', 'webberzone-grok-account' ),
							Config::label()
						)
					)
				);
			}
			return static::store_tokens( static::decode( $response, $status ), $tokens );
		} finally {
			static::release_lock();
		}
	}

	/**
	 * Decodes the claims of a JWT without verifying it.
	 *
	 * @since 1.0.0
	 *
	 * @param  string $jwt JSON Web Token.
	 * @return array Claims.
	 */
	public static function jwt_claims( $jwt ) {
		$parts = explode( '.', (string) $jwt );
		if ( count( $parts ) < 2 ) {
			return array();
		}
		$json   = base64_decode( strtr( $parts[1], '-_', '+/' ) . str_repeat( '=', ( 4 - strlen( $parts[1] ) % 4 ) % 4 ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- JWT payload.
		$claims = json_decode( (string) $json, true );
		return is_array( $claims ) ? $claims : array();
	}

	/**
	 * Whether the access token expires within the refresh margin.
	 *
	 * Uses the stored expiry when there is one, otherwise the access token's own exp claim.
	 *
	 * @since 1.0.0
	 *
	 * @param  array $tokens Token data.
	 * @return bool
	 */
	protected static function is_expiring( array $tokens ) {
		$exp = (int) ( $tokens['expires_at'] ?? 0 );
		if ( $exp <= 0 ) {
			$exp = (int) ( static::jwt_claims( $tokens['access_token'] )['exp'] ?? 0 );
		}
		return $exp > 0 && $exp - static::REFRESH_MARGIN <= time();
	}

	/**
	 * Takes the refresh lock.
	 *
	 * Uses INSERT IGNORE directly: add_option() upserts and trusts the per-request option cache, so it is not atomic.
	 * An expired lock is removed first, so a crashed request cannot hold it for more than 30 seconds.
	 *
	 * @since 1.0.0
	 *
	 * @phpstan-impure
	 *
	 * @return bool Whether the lock was acquired.
	 */
	protected static function acquire_lock() {
		global $wpdb;
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name = %s AND option_value < %d", static::LOCK_OPTION, time() ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$acquired = (bool) $wpdb->query( $wpdb->prepare( "INSERT IGNORE INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, %s, 'off')", static::LOCK_OPTION, (string) ( time() + 30 ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		wp_cache_delete( static::LOCK_OPTION, 'options' );
		return $acquired;
	}

	/**
	 * Whether another request holds the refresh lock, read from the database rather than the option cache.
	 *
	 * @since 1.0.0
	 *
	 * @phpstan-impure
	 *
	 * @return bool
	 */
	protected static function lock_held() {
		global $wpdb;
		return null !== $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", static::LOCK_OPTION ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}

	/**
	 * Releases the refresh lock.
	 *
	 * @since 1.0.0
	 */
	protected static function release_lock() {
		global $wpdb;
		$wpdb->delete( $wpdb->options, array( 'option_name' => static::LOCK_OPTION ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		wp_cache_delete( static::LOCK_OPTION, 'options' );
	}

	/**
	 * Reads the tokens from the database, bypassing this request's option cache, which another request may have made stale.
	 *
	 * @since 1.0.0
	 *
	 * @return array|null
	 */
	protected static function fresh_tokens() {
		wp_cache_delete( Token_Store::OPTION, 'options' );
		return Token_Store::get();
	}

	/**
	 * Transient key for the current user's pending device code.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	protected static function flow_key() {
		return 'wzgka_flow_' . get_current_user_id();
	}

	/**
	 * Sends a JSON POST request.
	 *
	 * @since 1.0.0
	 *
	 * @param  string $url  URL.
	 * @param  array  $data Request body.
	 * @return array HTTP response.
	 * @throws RuntimeException On transport errors.
	 */
	protected static function post_json( $url, array $data ) {
		return static::post(
			$url,
			array(
				'Content-Type' => 'application/json',
				'Accept'       => 'application/json',
			),
			(string) wp_json_encode( $data )
		);
	}

	/**
	 * Sends a form-encoded POST request.
	 *
	 * @since 1.0.0
	 *
	 * @param  string $url  URL.
	 * @param  array  $data Form fields.
	 * @return array HTTP response.
	 * @throws RuntimeException On transport errors.
	 */
	protected static function post_form( $url, array $data ) {
		return static::post( $url, array( 'Accept' => 'application/json' ), $data );
	}

	/**
	 * Sends a POST request.
	 *
	 * @since 1.0.0
	 *
	 * @param  string       $url     URL.
	 * @param  array        $headers Request headers.
	 * @param  array|string $body    Request body.
	 * @return array HTTP response.
	 * @throws RuntimeException On transport errors.
	 */
	protected static function post( $url, array $headers, $body ) {
		$response = wp_remote_post(
			$url,
			array(
				'timeout' => 20,
				'headers' => $headers,
				'body'    => $body,
			)
		);
		if ( is_wp_error( $response ) ) {
			throw new RuntimeException( esc_html( $response->get_error_message() ) );
		}
		return $response;
	}

	/**
	 * Decodes a JSON response, turning HTTP errors into exceptions.
	 *
	 * @since 1.0.0
	 *
	 * @param  array    $response HTTP response.
	 * @param  int|null $status   HTTP status code; read from the response when null.
	 * @return array Decoded body.
	 * @throws RuntimeException On HTTP or decoding errors.
	 */
	protected static function decode( $response, $status = null ) {
		$status = null === $status ? (int) wp_remote_retrieve_response_code( $response ) : $status;
		$raw    = (string) wp_remote_retrieve_body( $response );
		$body   = json_decode( $raw, true );
		if ( $status < 200 || $status >= 300 ) {
			$code   = is_array( $body ) && is_string( $body['error'] ?? null ) ? $body['error'] : '';
			$desc   = is_array( $body ) ? (string) ( $body['error_description'] ?? ( $body['error']['message'] ?? '' ) ) : '';
			$detail = trim( $code . ' ' . $desc, " \n\r\t\v\0" );
			throw new RuntimeException(
				esc_html(
					sprintf(
					/* translators: 1: service name, 2: HTTP status code, 3: error detail. */
						__( '%1$s returned HTTP %2$d. %3$s', 'webberzone-grok-account' ),
						Config::VENDOR,
						$status,
						'' !== $detail ? $detail : substr( wp_strip_all_tags( $raw ), 0, 200 )
					)
				)
			);
		}
		if ( ! is_array( $body ) ) {
			throw new RuntimeException(
				esc_html(
					sprintf(
					/* translators: %s: service name. */
						__( '%s returned an invalid response.', 'webberzone-grok-account' ),
						Config::VENDOR
					)
				)
			);
		}
		return $body;
	}

	/**
	 * Message for when no account is connected.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	private static function not_connected_message() {
		/* translators: %s: provider label. */
		return sprintf( __( '%s is not connected.', 'webberzone-grok-account' ), Config::label() );
	}
}
