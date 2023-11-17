<?php

namespace Drupal\symfony_mailer\Plugin\EmailAdjuster;

use Drupal\Core\Form\FormStateInterface;
use Drupal\symfony_mailer\EmailInterface;
use Drupal\symfony_mailer\Exception\SkipMailException;
use Drupal\symfony_mailer\Processor\EmailAdjusterBase;

/**
 * Defines the Skip Sending Email Adjuster.
 *
 * @EmailAdjuster(
 *   id = "email_skip_sending",
 *   label = @Translation("Skip sending"),
 *   description = @Translation("Skips the email sending."),
 *   weight = -1,
 * )
 */
class SkipSendingEmailAdjuster extends EmailAdjusterBase {

  /**
   * {@inheritdoc}
   */
  public function build(EmailInterface $email) {
    throw new SkipMailException($this->configuration['message']);
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $form['message'] = [
      '#type' => 'textfield',
      '#default_value' => $this->configuration['message'] ?? NULL,
      '#description' => $this->t('Users with permission to manage mailer settings will see this message when skipping an email.'),
    ];

    return $form;
  }

}
