<?php

namespace Drupal\openam_api\Service;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Logger\LoggerChannelFactory;
use Drupal\Core\Config\ConfigFactory;
use Exception;
use Drupal\Core\Url;
use Drupal\Core\Http\ClientFactory;
use Drupal\Component\Serialization\Json;

/**
 * Service class for OpenamApiClient.
 */
class OpenamApiClient {

  /**
   * An instance of Config Factory.
   *
   * @var \Drupal\Core\Config\Config|\Drupal\Core\Config\ImmutableConfig
   */
  private $config;

  /**
   * LoggerChannelFactory.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactory
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
   * @param \Drupal\Core\Logger\LoggerChannelFactory $loggerFactory
   *   LoggerChannelFactory.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $moduleHandler
   *   The module handler.
   */
  public function __construct(ClientFactory $httpClientFactory,
                              ConfigFactory $configFactory,
                              LoggerChannelFactory $loggerFactory,
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
    $this->loggerFactory->get('openam_api')->error(
      '@message - @exception', [
        '@message' => $message,
        // TODO Update the exception output for better readability.
        '@exception' => $e,
      ]
    );
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
      // TODO: Better handling of exception with response status codes.
      $this->logError('Error querying the endpoint', $e);
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
