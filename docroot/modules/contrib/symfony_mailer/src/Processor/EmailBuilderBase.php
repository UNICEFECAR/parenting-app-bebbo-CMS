<?php

namespace Drupal\symfony_mailer\Processor;

use Drupal\symfony_mailer\EmailFactoryInterface;
use Drupal\symfony_mailer\EmailInterface;

/**
 * Defines the base class for EmailBuilder plug-ins.
 */
abstract class EmailBuilderBase extends EmailProcessorBase implements EmailBuilderInterface {

  /**
   * The default weight for an email builder.
   */
  const DEFAULT_WEIGHT = 300;

  /**
   * {@inheritdoc}
   */
  public function createParams(EmailInterface $email) {
  }

  /**
   * {@inheritdoc}
   */
  public function fromArray(EmailFactoryInterface $factory, array $message) {
    $id = $this->getPluginId();
    throw new \LogicException("Conversion from old email system not supported for $id");
  }

  /**
   * {@inheritdoc}
   */
  public function import() {
    $id = $this->getPluginId();
    throw new \LogicException("Import function missing for $id");
  }

}
