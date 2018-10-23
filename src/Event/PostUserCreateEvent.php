<?php

namespace Drupal\openam_api\Event;

use Symfony\Component\EventDispatcher\Event;

/**
 * Class PostUserCreateEvent.
 *
 * @package Drupal\openam_api\Event
 */
class PostUserCreateEvent extends Event {

  const OPENAM_API_POSTUSERCREATE = 'openam_api.postusercreate';

  protected $user;

  /**
   * PostUserCreateEvent constructor.
   *
   * @param object $user
   *   User.
   */
  public function __construct($user) {
    $this->user = $user;
  }

  /**
   * Getter for the user array.
   *
   * @return user
   *   User
   */
  public function getUser() {
    return $this->user;
  }

  /**
   * Setter for user array.
   *
   * @param object $user
   *   User.
   */
  public function setUser($user) {
    $this->user = $user;
  }

}
