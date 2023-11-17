<?php

namespace Drupal\symfony_mailer\Plugin\EmailAdjuster;

use Drupal\symfony_mailer\EmailInterface;
use Drupal\symfony_mailer\Processor\EmailAdjusterBase;

/**
 * Defines the Plain text only Email Adjuster.
 *
 * @todo Disabled for now - use WrapAndConvertEmailAdjuster
 * EmailAdjuster(
 *   id = "mailer_plain_only",
 *   label = @Translation("Plain text only"),
 *   description = @Translation("Sends email as plain text only."),
 *   weight = 810,
 * )
 */
class PlainOnlyEmailAdjuster extends EmailAdjusterBase {

  /**
   * {@inheritdoc}
   */
  public function postRender(EmailInterface $email) {
    $email->setHtmlBody(NULL);
  }

}
