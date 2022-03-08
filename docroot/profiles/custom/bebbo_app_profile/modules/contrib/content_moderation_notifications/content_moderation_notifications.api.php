<?php

/**
 * @file
 * Callbacks and hooks related to content_moderation_notifications.
 */

use Drupal\Core\Entity\EntityInterface;

/**
 * Alter mail information before sending.
 *
 * Called by
 * Drupal\content_moderation_notifications\Notification::sendNotification().
 *
 * @param \Drupal\Core\Entity\EntityInterface $entity
 *   The moderated entity.
 * @param array $data
 *   The mail information.
 */
function hook_content_moderation_notification_mail_data_alter(EntityInterface $entity, array &$data) {
  // Add an extra email address to the list.
  $data['to'][] = 'example@example.com';
}
