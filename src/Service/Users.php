<?php

namespace Drupal\openam_api\Service;

use Drupal\Core\Config\ConfigFactory;
use Drupal\openam_api\Event\PostUserCreateEvent;
use Drupal\openam_api\Event\PreUserCreateEvent;
use Exception;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

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
   * @param \Drupal\openam_api\Service\OpenamApiClient $openamApiClient
   *   OpenAM API Client.
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $eventDispatcher
   *   The event dispatcher.
   */
  public function __construct(ConfigFactory $configFactory, OpenamApiClient $openamApiClient,
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

  public function isTokenValid($token, $options) {
    try {
      $apiOptions = $this->config->get('isValidToken');
      //$apiOptions['headers']['token'] = $token;
      $apiOptions['uri_template_options']['token'] = $token;
      // Merge request options for proxy related configurations.
      $apiOptions = array_merge_recursive($apiOptions, $options);
      $response = $this->openamApiClient->callEndpoint($apiOptions);
      //TODO: return response from parsed json.
      return $response;
    } catch (Exception $e) {
      $this->openamApiClient->logError("Unable to create user", $e);
      return FALSE;
    }
  }

}
