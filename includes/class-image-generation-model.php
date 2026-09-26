<?php
/**
 * Image generation model.
 *
 * @package WebberZone\Grok_Account
 */

namespace WebberZone\Grok_Account;

use WordPress\AiClient\Providers\Http\DTO\Request;
use WordPress\AiClient\Providers\Http\DTO\Response;
use WordPress\AiClient\Providers\Http\Enums\HttpMethodEnum;
use WordPress\AiClient\Providers\OpenAiCompatibleImplementation\AbstractOpenAiCompatibleImageGenerationModel;
use WordPress\AiClient\Results\DTO\GenerativeAiResult;

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Generates images through the xAI images endpoint.
 *
 * @since 1.0.0
 */
class Image_Generation_Model extends AbstractOpenAiCompatibleImageGenerationModel {


	/**
	 * Parameters the xAI images endpoint accepts.
	 *
	 * @var string[]
	 */
	const ALLOWED_PARAMS = array( 'model', 'prompt', 'n', 'response_format' );

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.0.0
	 *
	 * @param array $prompt List of messages.
	 */
	protected function prepareGenerateImageParams( array $prompt ): array {
		$params                    = array_intersect_key( parent::prepareGenerateImageParams( $prompt ), array_flip( self::ALLOWED_PARAMS ) );
		$params['response_format'] = 'b64_json';
		return $params;
	}

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
		return new Request( $method, Provider::url( $path ), $headers, $data, Text_Generation_Model::request_options( $this->getRequestOptions() ) );
	}

	/**
	 * {@inheritDoc}
	 *
	 * The xAI API returns JPEG images, while the base class assumes PNG unless an output format was requested, so the type is read from the image bytes.
	 *
	 * @since 1.0.0
	 *
	 * @param Response $response           API response.
	 * @param string   $expected_mime_type Expected MIME type.
	 */
	protected function parseResponseToGenerativeAiResult( Response $response, string $expected_mime_type = 'image/png' ): GenerativeAiResult {
		$data = $response->getData();
		$b64  = $data['data'][0]['b64_json'] ?? '';
		if ( is_string( $b64 ) && '' !== $b64 ) {
			$head = base64_decode( substr( $b64, 0, 16 ), true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- Sniffing the image signature.
			if ( is_string( $head ) ) {
				if ( 0 === strncmp( $head, "\xFF\xD8\xFF", 3 ) ) {
					$expected_mime_type = 'image/jpeg';
				} elseif ( 0 === strncmp( $head, "\x89PNG", 4 ) ) {
					$expected_mime_type = 'image/png';
				} elseif ( 0 === strncmp( $head, 'RIFF', 4 ) ) {
					$expected_mime_type = 'image/webp';
				}
			}
		}
		return parent::parseResponseToGenerativeAiResult( $response, $expected_mime_type );
	}
}
