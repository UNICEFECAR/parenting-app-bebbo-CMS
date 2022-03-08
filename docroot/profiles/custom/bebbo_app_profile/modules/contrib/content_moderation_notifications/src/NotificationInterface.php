<?php

namespace Drupal\content_moderation_notifications;

use Drupal\Core\Entity\EntityInterface;

/**
 * Interface for notification service.
 */
interface NotificationInterface {

  /**
   * Processes a given entity in transition.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity being transitioned from one state to another.
   */
  public function processEntity(EntityInterface $entity);

  /**
   * Send notifications for a given entity and set of notifications.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity we may be moderating.
   * @param \Drupal\content_moderation_notifications\ContentModerationNotificationInterface[] $notifications
   *   List of content moderation notification entities.
   *
   * @return bool
   *   TRUE if this entity is moderated, FALSE otherwise.
   */
  public function sendNotification(EntityInterface $entity, array $notifications);

}
