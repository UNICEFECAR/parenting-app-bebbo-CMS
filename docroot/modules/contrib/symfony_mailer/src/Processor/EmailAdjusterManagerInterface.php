<?php

namespace Drupal\symfony_mailer\Processor;

use Drupal\Component\Plugin\PluginManagerInterface;
use Drupal\symfony_mailer\EmailInterface;

/**
 * Provides the interface for the email adjuster plugin manager.
 */
interface EmailAdjusterManagerInterface extends PluginManagerInterface {

  /**
   * Applies email policy to an email message.
   *
   * @param \Drupal\symfony_mailer\EmailInterface $email
   *   The email.
   */
  public function applyPolicy(EmailInterface $email);

}
