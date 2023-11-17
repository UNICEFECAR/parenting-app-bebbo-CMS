<?php

namespace Drupal\symfony_mailer\Processor;

use Drupal\symfony_mailer\EmailInterface;

/**
 * Defines a trait to help writing EmailProcessorInterface implementations.
 */
trait EmailProcessorTrait {

  /**
   * {@inheritdoc}
   */
  public function init(EmailInterface $email) {
    foreach (self::FUNCTION_NAMES as $phase => $function) {
      $email->addProcessor([$this, $function], $phase, $this->getWeight($phase), $this->getId());
    }
  }

  /**
   * {@inheritdoc}
   */
  public function build(EmailInterface $email) {
  }

  /**
   * {@inheritdoc}
   */
  public function preRender(EmailInterface $email) {
  }

  /**
   * {@inheritdoc}
   */
  public function postRender(EmailInterface $email) {
  }

  /**
   * {@inheritdoc}
   */
  public function postSend(EmailInterface $email) {
  }

  /**
   * {@inheritdoc}
   */
  public function getWeight(int $phase) {
    return EmailInterface::DEFAULT_WEIGHT;
  }

  /**
   * {@inheritdoc}
   */
  public function getId() {
    return static::class;
  }

}
