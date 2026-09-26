<?php
/**
 * Device-code sign-in with xAI, and token refresh.
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
 * Implements the OAuth 2.0 device authorization grant (RFC 8628) against auth.x.ai, and token refresh.
 *
 * @since 1.0.0
 */
class OAuth {


	/**
	 * The xAI auth issuer.
	 *
	 * @var string
	 */
	const ISSUER = 'https://auth.x.ai';

	/**
	 * Public OAuth client ID used by Grok CLI-style agents.
	 *
	 * @var string
	 */
	const CLIENT_ID = 'b1a00492-073a-47ea-816f-4c329264a828';

	/**
	 * Requested scopes.
	 *
	 * @var string
	 */
	const SCOPE = 'openid profile email offline_access grok-cli:access api:access';

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
	 * Refresh access tokens this many seconds before they expire (they last about six hours).
	 *
	 * @var int
	 */
	const REFRESH_MARGIN = 3600;

	/**
	 * Device-code grant type.
	 *
	 * @var string
	 */
	const DEVICE_GRANT = 'urn:ietf:params:oauth:grant-type:device_code';

	/**
	 * Requests a device code and stores the pending flow for the current user.
	 *
	 * @since 1.0.0
	 *
	 * @return array User code, verification URL, polling interval and expiry timestamp.
	 * @throws RuntimeException When xAI rejects the request.
	 */
	public static function start_device_flow() {
		$response = self::post_form(
			self::ISSUER . '/oauth2/device/code',
			array(
				'client_id' => self::CLIENT_ID,
				'scope'     => self::SCOPE,
			)
		);
		$body     = self::decode( $response );

		if ( empty( $body['device_code'] ) || empty( $body['user_code'] ) || empty( $body['verification_uri'] ) ) {
			throw new RuntimeException( esc_html__( 'Unexpected response when requesting a device code.', 'webberzone-grok-account' ) );
		}

		$ttl  = min( self::FLOW_TTL, max( 60, (int) ( $body['expires_in'] ?? self::FLOW_TTL ) ) );
		$flow = array(
			'device_code' => (string) $body['device_code'],
			'interval'    => max( 5, (int) ( $body['interval'] ?? 5 ) ),
			'expires_at'  => time() + $ttl,
			'last_poll'   => 0,
		);
		set_transient( self::flow_key(), $flow, $ttl );

		return array(
			'user_code'        => (string) $body['user_code'],
			'verification_url' => (string) ( $body['verification_uri_complete'] ?? $body['verification_uri'] ),
			'interval'         => $flow['interval'],
			'expires_at'       => $flow['expires_at'],
		);
	}

	/**
	 * Checks once whether the user approved the pending device code, and stores the tokens if so.
	 *
	 * @since 1.0.0
	 *
	 * @return string 'pending' or 'connected'.
	 * @throws RuntimeException When the flow expired, was denied, or xAI returned an error.
	 */
	public static function poll_device_flow() {
		$flow = get_transient( self::flow_key() );
		if ( ! is_array( $flow ) || $flow['expires_at'] < time() ) {
			delete_transient( self::flow_key() );
			throw new RuntimeException( esc_html__( 'The sign-in code expired. Start again.', 'webberzone-grok-account' ) );
		}
		if ( time() - $flow['last_poll'] < $flow['interval'] ) {
			return 'pending';
		}
		$flow['last_poll'] = time();

		$response = self::post_form(
			self::ISSUER . '/oauth2/token',
			array(
				'grant_type'  => self::DEVICE_GRANT,
				'device_code' => $flow['device_code'],
				'client_id'   => self::CLIENT_ID,
			)
		);
		$status   = (int) wp_remote_retrieve_response_code( $response );
		$body     = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		$error    = is_array( $body ) && is_string( $body['error'] ?? null ) ? $body['error'] : '';

		if ( 'authorization_pending' === $error || 'slow_down' === $error ) {
			if ( 'slow_down' === $error ) {
				$flow['interval'] += 5;
			}
			set_transient( self::flow_key(), $flow, max( 1, $flow['expires_at'] - time() ) );
			return 'pending';
		}

		delete_transient( self::flow_key() );
		if ( 'access_denied' === $error ) {
			throw new RuntimeException( esc_html__( 'The sign-in was declined.', 'webberzone-grok-account' ) );
		}
		if ( 'expired_token' === $error ) {
			throw new RuntimeException( esc_html__( 'The sign-in code expired. Start again.', 'webberzone-grok-account' ) );
		}

		self::store_tokens( self::decode( $response, $status ) );
		return 'connected';
	}

	/**
	 * Abandons the current user's pending device code.
	 *
	 * @since 1.0.0
	 */
	public static function cancel_device_flow() {
		delete_transient( self::flow_key() );
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
			throw new RuntimeException( esc_html__( 'Grok Account is not connected. Sign in under Settings → Connectors.', 'webberzone-grok-account' ) );
		}
		if ( self::is_expiring( $tokens ) ) {
			$tokens = self::refresh( false );
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
		$before = Token_Store::get();
		if ( null === $before ) {
			throw new RuntimeException( esc_html__( 'Grok Account is not connected.', 'webberzone-grok-account' ) );
		}

		if ( ! self::acquire_lock() ) {
			for ( $i = 0; $i < 40; $i++ ) {
				usleep( 250000 );
				$current = Token_Store::get();
				if ( null !== $current && $current['access_token'] !== $before['access_token'] ) {
					return $current;
				}
				if ( ! get_option( self::LOCK_OPTION ) ) {
					break;
				}
			}
			if ( ! self::acquire_lock() ) {
				throw new RuntimeException( esc_html__( 'Timed out waiting for a Grok token refresh.', 'webberzone-grok-account' ) );
			}
		}

		try {
			$tokens = Token_Store::get();
			if ( null === $tokens ) {
				throw new RuntimeException( esc_html__( 'Grok Account is not connected.', 'webberzone-grok-account' ) );
			}
			if ( $tokens['access_token'] !== $before['access_token'] || ( ! $force && ! self::is_expiring( $tokens ) ) ) {
				return $tokens;
			}

			$response = self::post_form(
				self::ISSUER . '/oauth2/token',
				array(
					'grant_type'    => 'refresh_token',
					'refresh_token' => $tokens['refresh_token'],
					'client_id'     => self::CLIENT_ID,
				)
			);
			$status   = (int) wp_remote_retrieve_response_code( $response );
			if ( 400 === $status || 401 === $status ) {
				throw new RuntimeException( esc_html__( 'Your Grok session has expired or was revoked. Sign in again under Settings → Connectors.', 'webberzone-grok-account' ) );
			}
			return self::store_tokens( self::decode( $response, $status ), $tokens );
		} finally {
			delete_option( self::LOCK_OPTION );
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
	 * Saves a token response.
	 *
	 * @since 1.0.0
	 *
	 * @param  array $body     Token endpoint response.
	 * @param  array $previous Previously stored tokens, used for fields the response omits.
	 * @return array Stored token data.
	 * @throws RuntimeException When required fields are missing.
	 */
	private static function store_tokens( array $body, array $previous = array() ) {
		if ( empty( $body['access_token'] ) ) {
			throw new RuntimeException( esc_html__( 'The token response was missing an access token.', 'webberzone-grok-account' ) );
		}
		$id_token = (string) ( $body['id_token'] ?? ( $previous['id_token'] ?? '' ) );
		$claims   = self::jwt_claims( $id_token );

		$tokens = array(
			'access_token'  => (string) $body['access_token'],
			'refresh_token' => (string) ( $body['refresh_token'] ?? ( $previous['refresh_token'] ?? '' ) ),
			'id_token'      => $id_token,
			'expires_at'    => isset( $body['expires_in'] ) ? time() + (int) $body['expires_in'] : (int) ( self::jwt_claims( $body['access_token'] )['exp'] ?? 0 ),
			'email'         => (string) ( $claims['email'] ?? ( $previous['email'] ?? '' ) ),
			'name'          => (string) ( $claims['name'] ?? ( $previous['name'] ?? '' ) ),
		);
		if ( '' === $tokens['refresh_token'] ) {
			throw new RuntimeException( esc_html__( 'The token response did not include a refresh token.', 'webberzone-grok-account' ) );
		}
		Token_Store::save( $tokens );
		return Token_Store::get() ?? $tokens;
	}

	/**
	 * Whether the access token expires within the refresh margin.
	 *
	 * @since 1.0.0
	 *
	 * @param  array $tokens Token data.
	 * @return bool
	 */
	private static function is_expiring( array $tokens ) {
		$exp = (int) ( $tokens['expires_at'] ?? 0 );
		return $exp > 0 && $exp - self::REFRESH_MARGIN <= time();
	}

	/**
	 * Takes the refresh lock. add_option() is atomic because option_name is unique.
	 *
	 * @since 1.0.0
	 *
	 * @phpstan-impure
	 *
	 * @return bool Whether the lock was acquired.
	 */
	private static function acquire_lock() {
		if ( add_option( self::LOCK_OPTION, time() + 30, '', false ) ) {
			return true;
		}
		if ( (int) get_option( self::LOCK_OPTION ) < time() ) {
			delete_option( self::LOCK_OPTION );
			return add_option( self::LOCK_OPTION, time() + 30, '', false );
		}
		return false;
	}

	/**
	 * Transient key for the current user's pending device code.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	private static function flow_key() {
		return 'wzgka_flow_' . get_current_user_id();
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
	private static function post_form( $url, array $data ) {
		$response = wp_remote_post(
			$url,
			array(
				'timeout' => 20,
				'headers' => array( 'Accept' => 'application/json' ),
				'body'    => $data,
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
	private static function decode( $response, $status = null ) {
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
					/* translators: 1: HTTP status code, 2: error detail. */
						__( 'xAI returned HTTP %1$d. %2$s', 'webberzone-grok-account' ),
						$status,
						'' !== $detail ? $detail : substr( wp_strip_all_tags( $raw ), 0, 200 )
					)
				)
			);
		}
		if ( ! is_array( $body ) ) {
			throw new RuntimeException( esc_html__( 'xAI returned an invalid response.', 'webberzone-grok-account' ) );
		}
		return $body;
	}
}
