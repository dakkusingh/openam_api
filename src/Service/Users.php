<?php

namespace Drupal\openam_api\Service;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Config\ConfigFactory;
use Exception;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Drupal\Component\Serialization\Json;

/**
 * Class Users.
 *
 * @package Drupal\openam_api\Service
 */
class Users {

  /**
   * OpenAM Client.
   *
   * @var \Drupal\openam_api\Service\OpenamApiClient
   */
  public $openamApiClient;

  /**
   * The event dispatcher.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
   */
  protected $eventDispatcher;

  /**
   * An instance of Config Factory.
   *
   * @var \Drupal\Core\Config\Config|\Drupal\Core\Config\ImmutableConfig
   */
  private $config;

  /**
   * Users constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactory $configFactory
   *   Config factory.
   * @param \Drupal\openam_api\Service\OpenamApiClient $openamApiClient
   *   OpenAM API Client.
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $eventDispatcher
   *   The event dispatcher.
   */
  public function __construct(ConfigFactory $configFactory,
                              OpenamApiClient $openamApiClient,
                              EventDispatcherInterface $eventDispatcher) {
    $openamConfig = $configFactory->get('openam_api.settings');
    $this->config = $openamConfig->get('openam_api_operations');
    $this->openamApiClient = $openamApiClient;
    $this->eventDispatcher = $eventDispatcher;
  }

  /**
   * Validates the authToken with openAM.
   *
   * @param string $authToken
   *   Auth token to validate with openAM.
   * @param array $options
   *   Additional options for the guzzle request. e.g. proxy settings.
   *
   * @return bool|mixed|null|\Psr\Http\Message\StreamInterface
   *   Auth api response having user id, if token is valid.
   *
   * @throws \GuzzleHttp\Exception\GuzzleException
   */
  public function isTokenValid($authToken, array $options = []) {
    try {
      // Clean up the token value to handle " and +.
      $authToken = $this->openamApiClient->cleanSubjectId($authToken);
      $postBody = Json::encode(['_action' => 'validate']);

      $requestOptions = [
        'headers' => [
          'authToken' => $authToken,
        ],
        'body' => $postBody,
      ];
      $apiOptions = $this->config['isValidToken'];
      $apiOptions['uri_template_options']['token'] = $authToken;
      $apiOptions = NestedArray::mergeDeep($apiOptions, $requestOptions, $options);

      return $this->openamApiClient->queryEndpoint($apiOptions);
    }
    catch (Exception $e) {
      $this->openamApiClient->logError('Error validating auth token', $e);
      return FALSE;
    }
  }

  /**
   * Get user attributes from openAM.
   *
   * @param string $username
   *   User id for which attributes to be fetched.
   * @param string $authToken
   *   Auth token to validate with openAM.
   * @param array $options
   *   Additional options for the guzzle request. e.g. proxy settings.
   *
   * @return array|null
   *   User attributes from openAM.
   *
   * @throws \GuzzleHttp\Exception\GuzzleException
   */
  public function getUserData($username, $authToken, array $options = []) {
    $response = NULL;

    try {
      $authToken = $this->openamApiClient->cleanSubjectId($authToken);

      $requestOptions = [
        'headers' => [
          'authToken' => $authToken,
        ],
      ];

      $apiOptions = $this->config['attributes'];
      $apiOptions['uri_template_options']['username'] = $username;
      $apiOptions = NestedArray::mergeDeep($apiOptions, $requestOptions, $options);

      $response = $this->openamApiClient->queryEndpoint($apiOptions);
    }
    catch (Exception $e) {
      $this->openamApiClient->logError('Error getting user attributes', $e);
    }

    return $response;
  }

  /**
   * Logs out user from openAM using authToken.
   *
   * @param string $authToken
   *   Auth token to be used while loging out from openAM.
   * @param array $options
   *   Additional options for the guzzle request. e.g. proxy settings.
   *
   * @return array|null
   *   Sucess message response, if logout is successful.
   *
   * @throws \GuzzleHttp\Exception\GuzzleException
   */
  public function logoutUser($authToken, array $options = []) {
    try {
      // Clean up the token value to handle " and +.
      $authToken = $this->openamApiClient->cleanSubjectId($authToken);
      $postBody = Json::encode(['_action' => 'logout']);

      $requestOptions = [
        'headers' => [
          'authToken' => $authToken,
        ],
        'body' => $postBody,
      ];

      $apiOptions = $this->config['logout'];
      $apiOptions['uri_template_options'] = [];
      $apiOptions = NestedArray::mergeDeep($apiOptions, $requestOptions, $options);
      return $this->openamApiClient->queryEndpoint($apiOptions);
    }
    catch (Exception $e) {
      $this->openamApiClient->logError('Error loging out user', $e);
      return FALSE;
    }
  }

}
