<?php

namespace Drupal\content_moderation_notifications;

use Drupal\Core\Config\Entity\ConfigEntityInterface;

/**
 * Defines a content moderation notification interface.
 */
interface ContentModerationNotificationInterface extends ConfigEntityInterface {

  /**
   * Get the email addresses.
   *
   * @return string
   *   The email addresses (comma-separated) for which to send the notification.
   */
  public function getEmails();

  /**
   * Send the notification to the entity author.
   *
   * @return bool
   *   Returns TRUE if the notification should be sent to the entity author.
   */
  public function sendToAuthor();

  /**
   * Send the notification to the site mail address.
   *
   * @return bool
   *   Returns FALSE if the notification should be sent to site mail address.
   */
  public function disableSiteMail();

  /**
   * Gets the workflow ID.
   *
   * @return string
   *   The workflow ID.
   */
  public function getWorkflowId();

  /**
   * Gets the relevant roles for this notification.
   *
   * @return string[]
   *   The role IDs that should receive notification.
   */
  public function getRoleIds();

  /**
   * Get the transitions for which to send this notification.
   *
   * @return string[]
   *   The relevant transitions.
   */
  public function getTransitions();

  /**
   * Gets the notification subject.
   *
   * @return string
   *   The message subject.
   */
  public function getSubject();

  /**
   * Gets the message value.
   *
   * @return string
   *   The message body text.
   */
  public function getMessage();

  /**
   * Gets the message format.
   *
   * @return string
   *   The format to be used for the message body.
   */
  public function getMessageFormat();

}
