<?php

namespace Drupal\symfony_mailer\Plugin\EmailAdjuster;

/**
 * Defines the Cc Email Adjuster.
 *
 * @EmailAdjuster(
 *   id = "email_cc",
 *   label = @Translation("Cc"),
 *   description = @Translation("Sets the email cc header."),
 * )
 */
class CcEmailAdjuster extends AddressAdjusterBase {

  /**
   * The name of the associated header.
   */
  protected const NAME = 'cc';

}
