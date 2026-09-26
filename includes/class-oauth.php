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
class OAuth extends OAuth_Client {


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
	 * {@inheritDoc}
	 *
	 * @since 1.0.0
	 *
	 * @throws RuntimeException When xAI rejects the request.
	 */
	public static function start_device_flow() {
		return static::with_lock(
			static function () {
				$response = static::post_form(
					self::ISSUER . '/oauth2/device/code',
					array(
						'client_id' => self::CLIENT_ID,
						'scope'     => self::SCOPE,
					)
				);
				$body     = static::decode( $response );

				if ( empty( $body['device_code'] ) || empty( $body['user_code'] ) || empty( $body['verification_uri'] ) ) {
					throw new RuntimeException( esc_html__( 'Unexpected response when requesting a device code.', 'webberzone-grok-account' ) );
				}

				$ttl  = min( static::FLOW_TTL, max( 60, (int) ( $body['expires_in'] ?? static::FLOW_TTL ) ) );
				$flow = array(
					'flow_id'     => wp_generate_uuid4(),
					'device_code' => (string) $body['device_code'],
					'interval'    => max( 5, (int) ( $body['interval'] ?? 5 ) ),
					'expires_at'  => time() + $ttl,
					'last_poll'   => 0,
				);
				set_transient( static::flow_key(), $flow, $ttl );

				return array(
					'user_code'        => (string) $body['user_code'],
					'verification_url' => (string) ( $body['verification_uri_complete'] ?? $body['verification_uri'] ),
					'interval'         => $flow['interval'],
					'expires_at'       => $flow['expires_at'],
				);
			}
		);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.0.0
	 *
	 * @throws RuntimeException When the flow expired, was denied, or xAI returned an error.
	 */
	public static function poll_device_flow() {
		$flow = get_transient( static::flow_key() );
		if ( ! is_array( $flow ) || $flow['expires_at'] < time() ) {
			delete_transient( static::flow_key() );
			throw new RuntimeException( esc_html__( 'The sign-in code expired. Start again.', 'webberzone-grok-account' ) );
		}
		if ( time() - $flow['last_poll'] < $flow['interval'] ) {
			return 'pending';
		}
		$flow['last_poll'] = time();

		$response = static::post_form(
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
			static::with_lock(
				static function () use ( $flow, $error ) {
					$current = static::fresh_flow();
					if ( ! is_array( $current ) || ! isset( $current['flow_id'] ) || $current['flow_id'] !== $flow['flow_id'] ) {
						return;
					}
					if ( 'slow_down' === $error ) {
						$current['interval'] += 5;
					}
					$current['last_poll'] = time();
					set_transient( static::flow_key(), $current, max( 1, $current['expires_at'] - time() ) );
				}
			);
			return 'pending';
		}

		if ( 'access_denied' === $error ) {
			static::discard_flow( $flow );
			throw new RuntimeException( esc_html__( 'The sign-in was declined.', 'webberzone-grok-account' ) );
		}
		if ( 'expired_token' === $error ) {
			static::discard_flow( $flow );
			throw new RuntimeException( esc_html__( 'The sign-in code expired. Start again.', 'webberzone-grok-account' ) );
		}

		$tokens = static::decode( $response, $status );
		static::with_lock(
			static function () use ( $flow, $tokens ) {
				if ( ! static::flow_is_current( $flow ) ) {
					throw new RuntimeException( esc_html__( 'The sign-in was cancelled or replaced.', 'webberzone-grok-account' ) );
				}
				delete_transient( static::flow_key() );
				static::store_tokens( $tokens );
			}
		);
		return 'connected';
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.0.0
	 *
	 * @param array $tokens Current token data.
	 */
	protected static function request_refresh( array $tokens ) {
		return static::post_form(
			self::ISSUER . '/oauth2/token',
			array(
				'grant_type'    => 'refresh_token',
				'refresh_token' => $tokens['refresh_token'],
				'client_id'     => self::CLIENT_ID,
			)
		);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.0.0
	 *
	 * @param  array $body     Token endpoint response.
	 * @param  array $previous Previously stored tokens, used for fields the response omits.
	 * @throws RuntimeException When required fields are missing.
	 */
	protected static function store_tokens( array $body, array $previous = array() ) {
		if ( empty( $body['access_token'] ) ) {
			throw new RuntimeException( esc_html__( 'The token response was missing an access token.', 'webberzone-grok-account' ) );
		}
		$id_token = (string) ( $body['id_token'] ?? ( $previous['id_token'] ?? '' ) );
		$claims   = static::jwt_claims( $id_token );

		$tokens = array(
			'access_token'  => (string) $body['access_token'],
			'refresh_token' => (string) ( $body['refresh_token'] ?? ( $previous['refresh_token'] ?? '' ) ),
			'id_token'      => $id_token,
			'expires_at'    => isset( $body['expires_in'] ) ? time() + (int) $body['expires_in'] : (int) ( static::jwt_claims( $body['access_token'] )['exp'] ?? 0 ),
			'email'         => (string) ( $claims['email'] ?? ( $previous['email'] ?? '' ) ),
			'name'          => (string) ( $claims['name'] ?? ( $previous['name'] ?? '' ) ),
		);
		if ( '' === $tokens['refresh_token'] ) {
			throw new RuntimeException( esc_html__( 'The token response did not include a refresh token.', 'webberzone-grok-account' ) );
		}
		Token_Store::save( $tokens );
		return Token_Store::get() ?? $tokens;
	}
}
