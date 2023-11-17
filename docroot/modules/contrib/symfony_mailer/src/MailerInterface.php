<?php

namespace Drupal\symfony_mailer;

/**
 * Interface for mailer service.
 */
interface MailerInterface {

  /**
   * Sends an email.
   *
   * @param \Drupal\symfony_mailer\InternalEmailInterface $email
   *   The email to send.
   *
   * @return bool
   *   Whether successful.
   */
  public function send(InternalEmailInterface $email);

  /**
   * Changes the active theme.
   *
   * @param string $theme_name
   *   The theme name.
   *
   * @return string
   *   The previously active theme name.
   */
  public function changeTheme(string $theme_name);

}
