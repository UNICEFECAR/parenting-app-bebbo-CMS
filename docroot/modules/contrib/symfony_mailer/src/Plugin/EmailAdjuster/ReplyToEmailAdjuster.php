<?php

namespace Drupal\symfony_mailer\Plugin\EmailAdjuster;

/**
 * Defines the Reply-to Email Adjuster.
 *
 * @EmailAdjuster(
 *   id = "email_reply_to",
 *   label = @Translation("Reply-to"),
 *   description = @Translation("Sets the email reply-to header."),
 * )
 */
class ReplyToEmailAdjuster extends AddressAdjusterBase {

  /**
   * The name of the associated header.
   */
  protected const NAME = 'reply-to';

}
