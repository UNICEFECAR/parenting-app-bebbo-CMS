<?php

namespace Drupal\symfony_mailer\Plugin\EmailBuilder;

use Drupal\symfony_mailer\Address;
use Drupal\symfony_mailer\EmailInterface;
use Drupal\symfony_mailer\MailerHelperTrait;
use Drupal\symfony_mailer\Processor\EmailBuilderBase;
use Drupal\symfony_mailer\Processor\TokenProcessorTrait;

/**
 * Defines the base class for simplenews Email Builder plug-ins.
 */
class SimplenewsEmailBuilderBase extends EmailBuilderBase {

  use MailerHelperTrait;
  use TokenProcessorTrait;

  /**
   * {@inheritdoc}
   */
  public function build(EmailInterface $email) {
    // @todo Add a method SubscriberInterface::getAddress().
    $subscriber = $email->getParam('simplenews_subscriber');
    $address = new Address($subscriber->getMail(), NULL, $subscriber->getLangcode(), $subscriber->getUser());
    $email->setTo($address);
  }

}
