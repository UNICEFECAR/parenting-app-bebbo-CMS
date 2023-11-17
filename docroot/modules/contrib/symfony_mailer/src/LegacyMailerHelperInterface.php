<?php

namespace Drupal\symfony_mailer;

/**
 * Provides the legacy mailer helper service.
 */
interface LegacyMailerHelperInterface {

  /**
   * Formats a message body.
   *
   * Performs conversion of the message body as required by
   * \Drupal\Core\Mail\MailInterface::format().
   *
   * @param array $body_array
   *   An array of body parts.
   *
   * @return string
   *   The body as a single formatted string.
   */
  public function formatBody(array $body_array);

  /**
   * Fills a message array from an Email.
   *
   * @param \Drupal\symfony_mailer\EmailInterface $email
   *   The email to fill from.
   * @param array $message
   *   The array to fill.
   */
  public function emailToArray(EmailInterface $email, array &$message);

  /**
   * Fills an Email from a message array.
   *
   * @param \Drupal\symfony_mailer\EmailInterface $email
   *   The email to fill.
   * @param array $message
   *   The array to fill from.
   */
  public function emailFromArray(EmailInterface $email, array $message);

}
