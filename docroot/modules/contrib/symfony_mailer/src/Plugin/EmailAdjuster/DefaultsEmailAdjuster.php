<?php

namespace Drupal\symfony_mailer\Plugin\EmailAdjuster;

use Drupal\symfony_mailer\EmailInterface;
use Drupal\symfony_mailer\Entity\MailerTransport;
use Drupal\symfony_mailer\Processor\EmailAdjusterBase;

/**
 * Defines the Default headers Email Adjuster.
 *
 * @EmailAdjuster(
 *   id = "mailer_default_headers",
 *   label = @Translation("Default headers"),
 *   description = @Translation("Set default headers."),
 *   automatic = TRUE,
 *   weight = 100,
 * )
 */
class DefaultsEmailAdjuster extends EmailAdjusterBase {

  /**
   * {@inheritdoc}
   */
  public function build(EmailInterface $email) {
    $theme = $email->getTheme();
    $email->setSender('<site>')
      ->addTextHeader('X-Mailer', 'Drupal')
      ->addLibrary("$theme/email");

    if ($default_transport = MailerTransport::loadDefault()) {
      $email->setTransportDsn($default_transport->getDsn());
    }
  }

}
