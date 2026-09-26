<?php
/**
 * Provider availability.
 *
 * @package WebberZone\Grok_Account
 */

namespace WebberZone\Grok_Account;

use WordPress\AiClient\Providers\Contracts\ProviderAvailabilityInterface;

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Reports the provider as configured when an account is connected.
 *
 * @since 1.0.0
 */
class Availability implements ProviderAvailabilityInterface {


	/**
	 * {@inheritDoc}
	 *
	 * @since 1.0.0
	 */
	public function isConfigured(): bool {
		return Token_Store::is_connected();
	}
}
