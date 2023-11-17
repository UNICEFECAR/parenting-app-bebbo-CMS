<?php

namespace Drupal\symfony_mailer\Plugin\EmailAdjuster;

use Drupal\Core\Form\FormStateInterface;
use Drupal\symfony_mailer\EmailInterface;
use Drupal\symfony_mailer\Processor\EmailAdjusterBase;
use Symfony\Component\Mime\Email;

/**
 * Defines the Priority header Email Adjuster.
 *
 * @EmailAdjuster(
 *   id = "email_priority",
 *   label = @Translation("Priority"),
 *   description = @Translation("Sets the email priority."),
 * )
 */
class PriorityEmailAdjuster extends EmailAdjusterBase {

  /**
   * {@inheritdoc}
   */
  public function build(EmailInterface $email) {
    $priority = $this->configuration['value'];
    $email->setPriority($priority);
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $form['value'] = [
      '#type' => 'select',
      '#options' => $this->getPriorities(),
      '#default_value' => $this->configuration['value'] ?? NULL,
      '#required' => TRUE,
      '#description' => $this->t('Email priority.'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function getSummary() {
    return $this->getPriorities()[$this->configuration['value']];
  }

  /**
   * Returns a list of priority options.
   *
   * @return string[]
   *   The priority options.
   */
  protected function getPriorities() {
    return [
      Email::PRIORITY_HIGHEST => $this->t('Highest'),
      Email::PRIORITY_HIGH => $this->t('High'),
      Email::PRIORITY_NORMAL => $this->t('Normal'),
      Email::PRIORITY_LOW => $this->t('Low'),
      Email::PRIORITY_LOWEST => $this->t('Lowest'),
    ];
  }

}
