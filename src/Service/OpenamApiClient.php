<?php

namespace Drupal\openam_api\Service;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Config\ConfigFactory;
use Exception;
use Drupal\Core\Url;
use Drupal\Core\Http\ClientFactory;
use Drupal\Component\Serialization\Json;

/**
 * Service class for OpenamApiClient.
 */
class OpenamApiClient {

  const HTTP_STATUS_CODE_MESSAGES = [
    400 => "400 Bad Request - The request was malformed.",
    401 => "401 Unauthorized - The request requires user authentication.",
    403 => "403 Forbidden - Access was forbidden during an operation on a resource.",
    404 => "404 Not Found - The specified resource could not be found, perhaps because it does not exist.",
    500 => "500 Internal Server Error - The server encountered an unexpected condition that prevented it from fulfilling the request.",
    501 => "501 Not Implemented - The resource does not support the functionality required to fulfill the request.",
    503 => "503 Service Unavailable - The requested resource was temporarily unavailable. The service may have been disabled, for example."
  ];

  /**
   * An instance of Config Factory.
   *
   * @var \Drupal\Core\Config\Config|\Drupal\Core\Config\ImmutableConfig
   */
  private $config;

  /**
   * LoggerChannelFactory.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface
   */
  public $loggerFactory;

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  private $moduleHandler;

  /**
   * The HTTP client to fetch the API data.
   *
   * @var \Drupal\Core\Http\ClientFactory
   */
  private $httpClientFactory;

  /**
   * Create the OpenAM API client.
   *
   * @param \Drupal\Core\Http\ClientFactory $httpClientFactory
   *   A Guzzle client object.
   * @param \Drupal\Core\Config\ConfigFactory $configFactory
   *   An instance of Config Factory.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $loggerFactory
   *   LoggerChannelFactory.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $moduleHandler
   *   The module handler.
   */
  public function __construct(ClientFactory $httpClientFactory,
                              ConfigFactory $configFactory,
                              LoggerChannelFactoryInterface $loggerFactory,
                              ModuleHandlerInterface $moduleHandler) {
    $this->config = $configFactory->get('openam_api.settings');
    $this->loggerFactory = $loggerFactory;
    $this->moduleHandler = $moduleHandler;
    $this->httpClientFactory = $httpClientFactory;

  }

  /**
   * Debug OpenAM response and exceptions.
   *
   * @param mixed $data
   *   Data to debug.
   * @param string $type
   *   Response or Exception.
   */
  public function debug($data, $type = 'response') {
    if ($this->config->get('debug_' . $type)) {
      if ($this->moduleHandler->moduleExists('devel')) {
        ksm($data);
      }
    }
  }

  /**
   * Logs an error to the Drupal error log.
   *
   * @param string $message
   *   The error message.
   * @param \Exception $e
   *   The exception being handled.
   */
  public function logError($message, Exception $e) {
    $this->debug($e, 'exception');
    if ($this->config->get('log_exception')) {
      $this->loggerFactory->get('openam_api')->error(
        '@message - @exception', [
          '@message' => $message,
          // TODO Update the exception output for better readability.
          '@exception' => $e,
        ]
      );
    }
  }

  /**
   * Call the API endpoint based on the configurable options.
   *
   * @param array $options
   *   Request options with headers and post body etc.
   *
   * @return mixed|\Psr\Http\Message\ResponseInterface
   *   Guzzle response instance.
   *
   * @throws \GuzzleHttp\Exception\GuzzleException
   */
  public function callEndpoint(array $options = []) {
    $url = $this->requestUrl($options);
    $headers = $this->generateHeadersWithOptions($options);

    $client = $this->httpClientFactory->fromOptions($headers);
    return $client->request($options['http_method'], $url);
  }

  /**
   * Build the request Url based on the configurable options.
   *
   * @param array $options
   *   Request options with headers and post body etc.
   *
   * @return \Drupal\Core\GeneratedUrl|string
   *   Request url with replaced placeholder.
   */
  protected function requestUrl(array $options = []) {
    $baseUrl = $this->config->get('openam_api_url');
    $uri = \GuzzleHttp\uri_template($options['uri'], $options['uri_template_options']);
    return Url::fromUri($baseUrl . $uri)->toString();
  }

  /**
   * Replace/remove the quotes and spaces from the string.
   *
   * @param string $string
   *   String to be cleaned.
   *
   * @return mixed
   *   Cleaned string.
   */
  public function cleanSubjectId($string) {
    $string = trim($string);
    return str_replace([' ', '"'], ['+', ''], $string);
  }

  /**
   * Encode a string for MIME header.
   *
   * This method is used for username and password headers.
   * HTTP headers only support ISO-8859-1 characters. Therefore OpenAM allow
   * username and password headers to be MIME encoded.
   *
   * Use this method instead of mb_encode_mimeheader() as the latter can not
   * handle large strings, it also will not encode email addresses.
   *
   * https://bugster.forgerock.org/jira/browse/OPENAM-3750
   *
   * @param string $header_value
   *   The string to be encoded.
   *
   * @return string
   *   The encoded string.
   */
  public function base64EncodeHeader($header_value) {
    return '=?UTF-8?B?' . base64_encode($header_value) . '?=';
  }

  /**
   * Call openAM API for data.
   *
   * @param array $options
   *   for Url building.
   *
   * @return mixed|null|\Psr\Http\Message\StreamInterface
   *   Returns response body to be used by caller.
   *
   * @throws \GuzzleHttp\Exception\GuzzleException
   */
  public function queryEndpoint(array $options = []) {
    $responseContents = NULL;
    try {
      $response = $this->callEndpoint($options);
      $responseContents = $response->getBody();
      $responseContents = Json::decode($responseContents);
    }
    catch (\Exception $e) {
      if (isset(static::HTTP_STATUS_CODE_MESSAGES[$e->getCode()])) {
        $this->logError(static::HTTP_STATUS_CODE_MESSAGES[$e->getCode()], $e);
        throw $e;
      }

      $this->logError('Error querying the endpoint', $e);
      throw $e;
    }
    return $responseContents;
  }

  /**
   * Build an array of headers to pass to the openAM API.
   *
   * @param array $options
   *   Additional options for the request.
   *
   * @return array
   *   Default + additional option to be used for the request.
   */
  protected function generateHeadersWithOptions(array $options = []) {
    // Prepare initial options.
    $data = [
      'timeout' => $this->config->get('openam_api_timeout'),
    ];

    // Merge request options.
    $data = NestedArray::mergeDeep($data, $options);

    return $data;
  }

}
