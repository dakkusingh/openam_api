<?php

namespace Drupal\openam_api\EventSubscriber;

use Drupal\openam_api\Event\PostUserCreateEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * {@inheritdoc}
 */
class PostUserCreate implements EventSubscriberInterface {

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[PostUserCreateEvent::OPENAM_API_POSTUSERCREATE] = 'doPostUserCreateSub';
    return $events;
  }

  /**
   * Alter user before post submit.
   *
   * @param \Drupal\openam_api\Event\PostUserCreateEvent $event
   *   Post User Create Event.
   */
  public function doPostUserCreateSub(PostUserCreateEvent $event) {
    // $user = $event->getUser();
    // ksm($user);
    // $event->setUser($user);
  }

}
