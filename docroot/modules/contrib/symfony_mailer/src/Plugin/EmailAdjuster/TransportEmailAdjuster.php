<?php

namespace Drupal\symfony_mailer\Plugin\EmailAdjuster;

use Drupal\Core\Form\FormStateInterface;
use Drupal\symfony_mailer\EmailInterface;
use Drupal\symfony_mailer\Entity\MailerTransport;
use Drupal\symfony_mailer\Processor\EmailAdjusterBase;

/**
 * Defines the Mailer transport Email Adjuster.
 *
 * @EmailAdjuster(
 *   id = "email_transport",
 *   label = @Translation("Mailer transport"),
 *   description = @Translation("Sets the mailer transport alternative."),
 * )
 */
class TransportEmailAdjuster extends EmailAdjusterBase {

  /**
   * {@inheritdoc}
   */
  public function build(EmailInterface $email) {
    if ($transport = MailerTransport::load($this->configuration['value'])) {
      $email->setTransportDsn($transport->getDsn());
    }
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $options = [];
    foreach (MailerTransport::loadMultiple() as $id => $transport) {
      $options[$id] = $transport->label();
    }

    $form['value'] = [
      '#type' => 'select',
      '#options' => $options,
      '#default_value' => $this->configuration['value'] ?? NULL,
      '#required' => TRUE,
      '#description' => $this->t('Mailer transport.'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function getSummary() {
    if ($transport = MailerTransport::load($this->configuration['value'])) {
      return $transport->label();
    }
    return NULL;
  }

}
