<?php
/**
 * Encrypted storage for the Grok OAuth tokens.
 *
 * @package WebberZone\Grok_Account
 */

namespace WebberZone\Grok_Account;

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Stores the Grok tokens in a single autoload-off option, encrypted with libsodium.
 *
 * The key is derived from the site's auth salt, so changing the salts in wp-config.php disconnects the account.
 *
 * @since 1.0.0
 */
class Token_Store {


	/**
	 * Option that holds the encrypted token payload.
	 *
	 * @var string
	 */
	const OPTION = 'wzgka_tokens';

	/**
	 * Returns the decrypted token data, or null when not connected.
	 *
	 * @since 1.0.0
	 *
	 * @return array|null Token data with access_token, refresh_token, id_token, account_id, email, plan and updated_at.
	 */
	public static function get() {
		$raw = get_option( self::OPTION, '' );
		if ( ! is_string( $raw ) || '' === $raw ) {
			return null;
		}
		$decoded = base64_decode( $raw, true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- Decoding our own ciphertext envelope.
		if ( false === $decoded || strlen( $decoded ) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES ) {
			return null;
		}
		$plain = sodium_crypto_secretbox_open(
			substr( $decoded, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES ),
			substr( $decoded, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES ),
			self::key()
		);
		if ( false === $plain ) {
			return null;
		}
		$data = json_decode( $plain, true );
		return is_array( $data ) && ! empty( $data['access_token'] ) ? $data : null;
	}

	/**
	 * Encrypts and saves the token data.
	 *
	 * @since 1.0.0
	 *
	 * @param array $data Token data.
	 */
	public static function save( array $data ) {
		$data['updated_at'] = time();
		$nonce              = random_bytes( SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
		$cipher             = sodium_crypto_secretbox( (string) wp_json_encode( $data ), $nonce, self::key() );
		update_option( self::OPTION, base64_encode( $nonce . $cipher ), false ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Storing binary ciphertext as text.
	}

	/**
	 * Deletes the stored tokens and the cached model list.
	 *
	 * @since 1.0.0
	 */
	public static function clear() {
		delete_option( self::OPTION );
		delete_transient( Model_Directory::CACHE_KEY );
	}

	/**
	 * Whether a Grok account is connected.
	 *
	 * @since 1.0.0
	 *
	 * @return bool
	 */
	public static function is_connected() {
		return null !== self::get();
	}

	/**
	 * Derives the encryption key from the site's auth salt.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	private static function key() {
		return sodium_crypto_generichash( wp_salt( 'auth' ) . '|webberzone-grok-account', '', SODIUM_CRYPTO_SECRETBOX_KEYBYTES );
	}
}
