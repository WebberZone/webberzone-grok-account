<?php
/**
 * Model metadata directory.
 *
 * @package WebberZone\Grok_Account
 */

namespace WebberZone\Grok_Account;

use WordPress\AiClient\Common\Exception\InvalidArgumentException;
use WordPress\AiClient\Files\Enums\FileTypeEnum;
use WordPress\AiClient\Messages\Enums\ModalityEnum;
use WordPress\AiClient\Providers\Contracts\ModelMetadataDirectoryInterface;
use WordPress\AiClient\Providers\Models\DTO\ModelMetadata;
use WordPress\AiClient\Providers\Models\DTO\SupportedOption;
use WordPress\AiClient\Providers\Models\Enums\CapabilityEnum;
use WordPress\AiClient\Providers\Models\Enums\OptionEnum;

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Lists the Grok models available to the connected account, from the xAI /models endpoint (cached) with a fallback list.
 *
 * @since 1.0.0
 */
class Model_Directory implements ModelMetadataDirectoryInterface {


	/**
	 * Transient that caches the live model list.
	 *
	 * @var string
	 */
	const CACHE_KEY = 'wzgka_models';

	/**
	 * Transient set when the live model list could not be fetched, to avoid retrying on every request.
	 *
	 * @var string
	 */
	const FAILED_KEY = 'wzgka_models_failed';

	/**
	 * Models used when the live list is unavailable, as ID => type.
	 *
	 * @var array<string, string>
	 */
	const FALLBACK_MODELS = array(
		'grok-4.3'           => 'text',
		'grok-imagine-image' => 'image',
	);

	/**
	 * Model metadata keyed by model ID.
	 *
	 * @var array<string, ModelMetadata>|null
	 */
	private $models = null;

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.0.0
	 */
	public function listModelMetadata(): array {
		if ( null === $this->models ) {
			$this->models = array();
			foreach ( $this->model_ids() as $id => $type ) {
				$this->models[ $id ] = 'image' === $type ? self::image_metadata( $id ) : self::text_metadata( $id );
			}
		}
		return array_values( $this->models );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.0.0
	 *
	 * @param string $model_id Model ID.
	 */
	public function hasModelMetadata( string $model_id ): bool {
		$this->listModelMetadata();
		return isset( $this->models[ $model_id ] );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.0.0
	 *
	 * @param  string $model_id Model ID.
	 * @throws InvalidArgumentException When the model is unknown.
	 */
	public function getModelMetadata( string $model_id ): ModelMetadata {
		if ( ! $this->hasModelMetadata( $model_id ) ) {
			throw new InvalidArgumentException( esc_html( sprintf( 'No Grok Account model with ID %s.', $model_id ) ) );
		}
		return $this->models[ $model_id ];
	}

	/**
	 * Returns the models as ID => type ('text' or 'image').
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, string>
	 */
	private function model_ids() {
		$cached = get_transient( self::CACHE_KEY );
		if ( is_array( $cached ) && $cached ) {
			return $cached;
		}
		$models = get_transient( self::FAILED_KEY ) ? array() : $this->fetch_live_models();
		if ( $models ) {
			set_transient( self::CACHE_KEY, $models, 12 * HOUR_IN_SECONDS );
			return $models;
		}
		if ( Token_Store::is_connected() ) {
			set_transient( self::FAILED_KEY, 1, 10 * MINUTE_IN_SECONDS );
		}

		/**
		 * Filters the models offered when the live model list cannot be fetched.
		 *
		 * @since 1.0.0
		 *
		 * @param array<string, string> $models Model ID => 'text' or 'image'.
		 */
		return (array) apply_filters( 'wzgka_fallback_models', self::FALLBACK_MODELS );
	}

	/**
	 * Fetches the models from the xAI API.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, string>
	 */
	private function fetch_live_models() {
		if ( ! Token_Store::is_connected() ) {
			return array();
		}
		try {
			$tokens = OAuth::get_valid_tokens();
		} catch ( \Exception $e ) {
			return array();
		}
		$response = wp_remote_get(
			Provider::url( 'models' ),
			array(
				'timeout' => 15,
				'headers' => array( 'Authorization' => 'Bearer ' . $tokens['access_token'] ),
			)
		);
		if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
			return array();
		}
		$data = json_decode( (string) wp_remote_retrieve_body( $response ), true );

		$models = array();
		foreach ( (array) ( $data['data'] ?? array() ) as $entry ) {
			$id   = is_array( $entry ) && is_string( $entry['id'] ?? null ) ? $entry['id'] : '';
			$type = self::model_type( $id );
			if ( null !== $type ) {
				$models[ $id ] = $type;
			}
		}
		uksort( $models, array( __CLASS__, 'sort_models' ) );
		return $models;
	}

	/**
	 * Classifies a model ID.
	 *
	 * @since 1.0.0
	 *
	 * @param  string $id Model ID.
	 * @return string|null 'text', 'image', or null for unsupported models (video, speech, embeddings).
	 */
	private static function model_type( $id ) {
		if ( 0 !== strpos( $id, 'grok-' ) ) {
			return null;
		}
		if ( preg_match( '/imagine-image|-image(-|$)/', $id ) ) {
			return 'image';
		}
		if ( preg_match( '/video|tts|speech|audio|transcri|embed/', $id ) ) {
			return null;
		}
		return 'text';
	}

	/**
	 * Sorts models newest first, with plain names (e.g. grok-4.7) ahead of their variants.
	 *
	 * Versions are compared as decimals because xAI's minor numbers are not semantic: grok-4.20 is older than grok-4.7.
	 *
	 * @since 1.0.0
	 *
	 * @param string $a Model ID.
	 * @param string $b Model ID.
	 * @return int
	 */
	private static function sort_models( $a, $b ) {
		$by_version = self::version( $b ) <=> self::version( $a );
		if ( 0 !== $by_version ) {
			return $by_version;
		}
		$by_length = strlen( $a ) <=> strlen( $b );
		return 0 !== $by_length ? $by_length : strcmp( $a, $b );
	}

	/**
	 * Extracts the numeric version from a model ID, e.g. 4.3 from grok-4.3-fast.
	 *
	 * @since 1.0.0
	 *
	 * @param string $id Model ID.
	 * @return float
	 */
	private static function version( $id ) {
		return preg_match( '/^grok-(\d+(?:\.\d+)?)/', $id, $m ) ? (float) $m[1] : 0.0;
	}

	/**
	 * Builds metadata for a text model.
	 *
	 * @since 1.0.0
	 *
	 * @param  string $id Model ID.
	 * @return ModelMetadata
	 */
	private static function text_metadata( $id ) {
		$options = array(
			new SupportedOption( OptionEnum::systemInstruction() ),
			new SupportedOption( OptionEnum::candidateCount() ),
			new SupportedOption( OptionEnum::maxTokens() ),
			new SupportedOption( OptionEnum::temperature() ),
			new SupportedOption( OptionEnum::topP() ),
			new SupportedOption( OptionEnum::outputMimeType(), array( 'text/plain', 'application/json' ) ),
			new SupportedOption( OptionEnum::outputSchema() ),
			new SupportedOption( OptionEnum::functionDeclarations() ),
			new SupportedOption( OptionEnum::customOptions() ),
			new SupportedOption(
				OptionEnum::inputModalities(),
				array(
					array( ModalityEnum::text() ),
					array( ModalityEnum::text(), ModalityEnum::image() ),
				)
			),
			new SupportedOption( OptionEnum::outputModalities(), array( array( ModalityEnum::text() ) ) ),
		);
		return new ModelMetadata( $id, $id, array( CapabilityEnum::textGeneration(), CapabilityEnum::chatHistory() ), $options );
	}

	/**
	 * Builds metadata for an image model.
	 *
	 * @since 1.0.0
	 *
	 * @param  string $id Model ID.
	 * @return ModelMetadata
	 */
	private static function image_metadata( $id ) {
		$options = array(
			new SupportedOption( OptionEnum::inputModalities(), array( array( ModalityEnum::text() ) ) ),
			new SupportedOption( OptionEnum::outputModalities(), array( array( ModalityEnum::image() ) ) ),
			new SupportedOption( OptionEnum::candidateCount() ),
			new SupportedOption( OptionEnum::outputMimeType(), array( 'image/jpeg', 'image/png' ) ),
			new SupportedOption( OptionEnum::outputFileType(), array( FileTypeEnum::inline() ) ),
			new SupportedOption( OptionEnum::customOptions() ),
		);
		return new ModelMetadata( $id, $id, array( CapabilityEnum::imageGeneration() ), $options );
	}
}
