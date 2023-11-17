<?php

namespace Drupal\symfony_mailer\Plugin\EmailAdjuster;

/**
 * Defines the From Email Adjuster.
 *
 * @EmailAdjuster(
 *   id = "email_from",
 *   label = @Translation("From"),
 *   description = @Translation("Sets the email from header."),
 * )
 */
class FromEmailAdjuster extends AddressAdjusterBase {

  /**
   * The name of the associated header.
   */
  protected const NAME = 'from';

}
