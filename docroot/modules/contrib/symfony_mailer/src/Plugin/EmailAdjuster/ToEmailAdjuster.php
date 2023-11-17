<?php

namespace Drupal\symfony_mailer\Plugin\EmailAdjuster;

/**
 * Defines the To Email Adjuster.
 *
 * @EmailAdjuster(
 *   id = "email_to",
 *   label = @Translation("To"),
 *   description = @Translation("Sets the email to header."),
 * )
 */
class ToEmailAdjuster extends AddressAdjusterBase {

  /**
   * The name of the associated header.
   */
  protected const NAME = 'to';

}
