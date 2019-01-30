<?php

namespace Drupal\openam_api\Service;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Config\ConfigFactory;
use Drupal\openam_api\Event\PostUserCreateEvent;
use Drupal\openam_api\Event\PreUserCreateEvent;
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
    $this->config = $configFactory->get('openam_api_operations.settings');
    $this->openamApiClient = $openamApiClient;
    $this->eventDispatcher = $eventDispatcher;
  }

  /**
   * Creates an OpenAM User.
   *
   * This is example code.
   *
   * @param array $profile
   *   The new user's profile.
   * @param array|null $credentials
   *   The new user's credentials.
   * @param array $provider
   *   The authentication provider, if using.
   * @param bool $activate
   *   TRUE if the user should be activated after creation.*.
   * @param bool $returnExisting
   *   Return the user if exists?
   *
   * @return bool|object
   *   Returns the user if creation was successful or FALSE if not.
   */
  public function userCreate(array $profile,
                             $credentials = [],
                             array $provider = NULL,
                             $activate = TRUE,
                             $returnExisting = TRUE) {

    // TODO Check if user already exists.
    try {
      $user = [
        // TODO update this for OpenAM.
        'profile' => $profile,
        'credentials' => $credentials,
        'provider' => $provider,
        'activate' => $activate,
        'already_registered' => FALSE,
        'skip_register' => FALSE,
      ];

      // Allow other modules to subscribe to Pre Submit Event.
      $preUserCreateEvent = new PreUserCreateEvent($user);
      $preUser = $this->eventDispatcher->dispatch(PreUserCreateEvent::OPENAM_API_PREUSERCREATE, $preUserCreateEvent);
      $userTemp = $preUser->getUser();

      // TODO Create the User in OpenAM.
      $openamUser = NULL;

      // Debug handler.
      $this->openamApiClient->debug($openamUser, 'response');

      // Allow other modules to subscribe to Post Submit Event.
      $postUserCreateEvent = new PostUserCreateEvent($user);
      $this->eventDispatcher->dispatch(PostUserCreateEvent::OPENAM_API_POSTUSERCREATE, $postUserCreateEvent);

      // Log create user.
      $this->openamApiClient->loggerFactory->get('openam_api')->notice(
        "@message",
        [
          // TODO test and update this for OpenAM user details.
          '@message' => 'created user: ' . $user['profile']['email'],
        ]
      );

      return $openamUser;
    }
    catch (Exception $e) {
      $this->openamApiClient->logError("Unable to create user", $e);
      return FALSE;
    }
  }

  /**
   * Validates the authToken with openAM.
   *
   * @param string $authToken
   *   Auth token to validate with openAM.
   * @param array $options
   *   Additional options for the guzzle request. e.g. proxy settings.
   *
   * @return bool|\Drupal\openam_api\Service\Guzzle\Http\Message\Response
   *   Auth api response having user id, if token is valid.
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

      $apiOptions = $this->config->get('isValidToken');

      $apiOptions['uri_template_options']['token'] = $authToken;

      // Merge request options.
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
   * @return \Drupal\openam_api\Service\Guzzle\Http\Message\Response|null
   *   User attributes from openAM.
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
      $apiOptions = $this->config->get('attributes');
      $apiOptions['uri_template_options']['username'] = $username;
      // Merge request options.
      $apiOptions = NestedArray::mergeDeep($apiOptions, $requestOptions, $options);

      $response = $this->openamApiClient->queryEndpoint($apiOptions);
    }
    catch (Exception $e) {
      $this->openamApiClient->logError('Error getting user attributes', $e);
    }
    return $response;
  }

}
