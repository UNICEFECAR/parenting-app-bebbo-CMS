<?php

namespace Drupal\content_moderation_notifications;

use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Defines an access controller for the content_moderation_notification entity.
 *
 * We set this class to be the access controller in
 * ContentModerationNotification's entity annotation.
 *
 * @see \Drupal\content_moderation_notifications\Entity\ContentModerationNotification
 *
 * @ingroup content_moderation_notifications
 */
class ContentModerationNotificationsAccessController extends EntityAccessControlHandler {

  /**
   * {@inheritdoc}
   */
  public function checkAccess(EntityInterface $entity, $operation, AccountInterface $account) {
    // No special access handling. Defer to the entity system which will only
    // allow admin access by default.
    return parent::checkAccess($entity, $operation, $account);
  }

}
