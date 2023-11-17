<?php

namespace Drupal\symfony_mailer\Plugin\EmailAdjuster;

/**
 * Defines the Bcc Email Adjuster.
 *
 * @EmailAdjuster(
 *   id = "email_bcc",
 *   label = @Translation("Bcc"),
 *   description = @Translation("Sets the email bcc header."),
 * )
 */
class BccEmailAdjuster extends AddressAdjusterBase {

  /**
   * The name of the associated header.
   */
  protected const NAME = 'bcc';

}
