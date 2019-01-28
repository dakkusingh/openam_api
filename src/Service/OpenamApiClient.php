<?php

namespace Drupal\openam_api\Service;

use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Logger\LoggerChannelFactory;
use Drupal\Core\Config\ConfigFactory;
use Exception;
use Drupal\Core\Url;
use GuzzleHttp\Client as GuzzleClient;


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
   * Create the OpenAM API client.
   *
   * @param \Drupal\Core\Config\ConfigFactory $configFactory
   *   An instance of Config Factory.
   * @param \Drupal\Core\Logger\LoggerChannelFactory $loggerFactory
   *   LoggerChannelFactory.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   */
  public function __construct(ConfigFactory $configFactory,
                              LoggerChannelFactory $loggerFactory,
                              ModuleHandlerInterface $module_handler) {
    $this->config = $configFactory->get('openam_api.settings');
    $this->loggerFactory = $loggerFactory;
    $this->moduleHandler = $module_handler;

    // TODO Extend this for OpenAM Client.
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

  public function callEndpoint(array $options = []) {
    $client = new GuzzleClient();
    $url = $this->requestUrl($options);
    $headers = $this->generateHeadersWithOptions($options);
    return $client->request($options['httpMethod'], $url, $headers);
  }

  protected function requestUrl(array $options = []) {
    $baseUrl = $this->config->get('openam_api_url');
    $uri = \GuzzleHttp\uri_template($options['uri'], $options['uri_template_options']);
    return Url::fromUri( $baseUrl. $uri)->toString();
  }

  /**
   * Call openAM API for data.
   *
   * @param array $options
   *   for Url building.
   *
   * @return Guzzle\Http\Message\Response
   *   Returns response body to be used by caller.
   */
  public function queryEndpoint(array $options = []) {
    $responseContents = NULL;
    try {
      $response = $this->callEndpoint($options);
      $responseContents = $response->getBody()->getContents();
    }
    catch (\Exception $e) {
      $this->logError('Error querying the endpoint', $e);
      // Attempt to get response from the api service.
      $response = $e->getResponse();
      if (!empty($response)) {
        $responseContents = $response->getBody()->getContents();
      }
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
  protected function generateHeadersWithOptions(array $options) {
    // Prepare initial options.
    $data = [
      'headers' => [
        'Accept' => 'application/json',
        'Content-Type' => 'application/json',
      ],
      // TODO: Finalize on this when integrating with apis.
      'timeout' => 30,
    ];
    // Merge request options.
    $data = array_merge_recursive($data, $options);
    return $data;
  }

}
