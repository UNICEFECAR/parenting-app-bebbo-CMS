<?php

namespace Drupal\symfony_mailer\Processor;

use Drupal\Core\Form\FormStateInterface;

/**
 * Defines the base class for EmailAdjuster plug-ins.
 */
abstract class EmailAdjusterBase extends EmailProcessorBase implements EmailAdjusterInterface {

  /**
   * The default weight for an email adjuster.
   */
  const DEFAULT_WEIGHT = 400;

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function getLabel() {
    return $this->pluginDefinition['label'];
  }

  /**
   * {@inheritdoc}
   */
  public function getSummary() {
    return NULL;
  }

}
