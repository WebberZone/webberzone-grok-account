<?php
/**
 * Text generation model.
 *
 * @package WebberZone\Grok_Account
 */

namespace WebberZone\Grok_Account;

use WordPress\AiClient\Providers\Http\DTO\Request;
use WordPress\AiClient\Providers\Http\DTO\RequestOptions;
use WordPress\AiClient\Providers\Http\Enums\HttpMethodEnum;
use WordPress\AiClient\Providers\OpenAiCompatibleImplementation\AbstractOpenAiCompatibleTextGenerationModel;

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Generates text through the xAI chat completions endpoint.
 *
 * @since 1.0.0
 */
class Text_Generation_Model extends AbstractOpenAiCompatibleTextGenerationModel {


	/**
	 * Minimum request timeout in seconds for reasoning models, which can be slow.
	 *
	 * @var float
	 */
	const MIN_TIMEOUT = 180.0;

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.0.0
	 *
	 * @param HttpMethodEnum $method  HTTP method.
	 * @param string         $path    Endpoint path.
	 * @param array          $headers Request headers.
	 * @param mixed          $data    Request data.
	 */
	protected function createRequest( HttpMethodEnum $method, string $path, array $headers = array(), $data = null ): Request {
		return new Request( $method, Provider::url( $path ), $headers, $data, self::request_options( $this->getRequestOptions(), $this->metadata()->getId() ) );
	}

	/**
	 * {@inheritDoc}
	 *
	 * The xAI API requires the schema wrapped as `{ name, schema }`; the base class sends it bare.
	 *
	 * @since 1.0.0
	 *
	 * @param  array<string, mixed>|null $outputSchema The output schema.
	 * @return array<string, mixed>
	 */
	protected function prepareResponseFormatParam( ?array $outputSchema ): array { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid, WordPress.NamingConventions.ValidVariableName.VariableNotSnakeCase
		if ( is_array( $outputSchema ) ) { // phpcs:ignore WordPress.NamingConventions.ValidVariableName.VariableNotSnakeCase
			return array(
				'type'        => 'json_schema',
				'json_schema' => array(
					'name'   => 'response',
					'schema' => $outputSchema, // phpcs:ignore WordPress.NamingConventions.ValidVariableName.VariableNotSnakeCase
				),
			);
		}

		return array( 'type' => 'json_object' );
	}

	/**
	 * Returns request options with at least the minimum timeout.
	 *
	 * Non-reasoning models answer quickly, so the caller's timeout is kept for them.
	 *
	 * @since 1.0.0
	 *
	 * @param  RequestOptions|null $options  Existing options.
	 * @param  string              $model_id Model ID, or an empty string when unknown.
	 * @return RequestOptions
	 */
	public static function request_options( $options, string $model_id = '' ) {
		$options = $options ? clone $options : new RequestOptions();
		$minimum = false !== strpos( $model_id, 'non-reasoning' ) ? 0.0 : self::MIN_TIMEOUT;

		/**
		 * Filters the minimum request timeout in seconds.
		 *
		 * @since 1.0.3
		 *
		 * @param float  $minimum  Minimum timeout. 0 for non-reasoning models, otherwise 180.
		 * @param string $model_id Model ID, or an empty string when unknown.
		 */
		$minimum = (float) apply_filters( 'wzgka_min_timeout', $minimum, $model_id );

		if ( null === $options->getTimeout() ) {
			$options->setTimeout( self::MIN_TIMEOUT );
		} elseif ( $options->getTimeout() < $minimum ) {
			$options->setTimeout( $minimum );
		}
		return $options;
	}
}
