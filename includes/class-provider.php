<?php
/**
 * Grok Account AI Client provider.
 *
 * @package WebberZone\Grok_Account
 */

namespace WebberZone\Grok_Account;

use WordPress\AiClient\Common\Exception\RuntimeException;
use WordPress\AiClient\Providers\ApiBasedImplementation\AbstractApiProvider;
use WordPress\AiClient\Providers\Contracts\ModelMetadataDirectoryInterface;
use WordPress\AiClient\Providers\Contracts\ProviderAvailabilityInterface;
use WordPress\AiClient\Providers\DTO\ProviderMetadata;
use WordPress\AiClient\Providers\Enums\ProviderTypeEnum;
use WordPress\AiClient\Providers\Http\Enums\RequestAuthenticationMethod;
use WordPress\AiClient\Providers\Models\Contracts\ModelInterface;
use WordPress\AiClient\Providers\Models\DTO\ModelMetadata;

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Provider for the xAI API, authenticated with a SuperGrok or X Premium account.
 *
 * @since 1.0.0
 */
class Provider extends AbstractApiProvider {


	/**
	 * {@inheritDoc}
	 *
	 * @since 1.0.0
	 */
	protected static function baseUrl(): string {
		return 'https://api.x.ai/v1';
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.0.0
	 *
	 * @param  ModelMetadata    $model_metadata    Model metadata.
	 * @param  ProviderMetadata $provider_metadata Provider metadata.
	 * @return ModelInterface
	 * @throws RuntimeException When the model has no supported capability.
	 */
	protected static function createModel( ModelMetadata $model_metadata, ProviderMetadata $provider_metadata ): ModelInterface {
		foreach ( $model_metadata->getSupportedCapabilities() as $capability ) {
			if ( $capability->isTextGeneration() ) {
				return new Text_Generation_Model( $model_metadata, $provider_metadata );
			}
			if ( $capability->isImageGeneration() ) {
				return new Image_Generation_Model( $model_metadata, $provider_metadata );
			}
		}
		throw new RuntimeException( esc_html( 'Unsupported Grok Account model: ' . $model_metadata->getId() ) );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.0.0
	 */
	protected static function createProviderMetadata(): ProviderMetadata {
		return new ProviderMetadata(
			PROVIDER_ID,
			__( 'Grok Account', 'webberzone-grok-account' ),
			ProviderTypeEnum::cloud(),
			admin_url( 'options-general.php?page=' . Admin::PAGE ),
			RequestAuthenticationMethod::apiKey(),
			__( 'Text and image generation using your SuperGrok or X Premium subscription. Sign in with xAI; no API key needed.', 'webberzone-grok-account' ),
			WZGKA_PLUGIN_DIR . 'assets/images/grok.svg'
		);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.0.0
	 */
	protected static function createProviderAvailability(): ProviderAvailabilityInterface {
		return new Availability();
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.0.0
	 */
	protected static function createModelMetadataDirectory(): ModelMetadataDirectoryInterface {
		return new Model_Directory();
	}
}
