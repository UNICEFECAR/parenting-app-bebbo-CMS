<?php

namespace Drupal\symfony_mailer;

/**
 * Provides a trait for accessing the mailer helper service.
 */
trait MailerHelperTrait {

  /**
   * The mailer helper.
   *
   * @var \Drupal\symfony_mailer\MailerHelperInterface
   */
  protected $helper;

  /**
   * Sets the mailer helper.
   *
   * @param \Drupal\symfony_mailer\MailerHelperInterface $helper
   *   The mailer helper.
   */
  public function setHelper(MailerHelperInterface $helper) {
    $this->helper = $helper;
  }

  /**
   * Gets the mailer helper.
   *
   * @return \Drupal\symfony_mailer\MailerHelperInterface
   *   The mailer helper.
   */
  public function helper() {
    if (!isset($this->helper)) {
      $this->helper = \Drupal::service('symfony_mailer.helper');
    }
    return $this->helper;
  }

}
