<?php

namespace Drupal\symfony_mailer\Plugin\EmailAdjuster;

use Drupal\Core\Form\FormStateInterface;
use Drupal\symfony_mailer\EmailInterface;
use Drupal\symfony_mailer\Processor\EmailAdjusterBase;

/**
 * Defines the Plain text alternative Email Adjuster.
 *
 * @EmailAdjuster(
 *   id = "email_plain",
 *   label = @Translation("Plain text alternative"),
 *   description = @Translation("Sets the email plain text alternative."),
 * )
 */
class PlainEmailAdjuster extends EmailAdjusterBase {

  /**
   * {@inheritdoc}
   */
  public function build(EmailInterface $email) {
    $email->setTextBody($this->configuration['value']);
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $form['value'] = [
      '#type' => 'textarea',
      '#default_value' => $this->configuration['value'] ?? NULL,
      '#required' => TRUE,
      '#description' => $this->t('Plain text alternative.'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function getSummary() {
    return $this->configuration['value'];
  }

}
