<?php

namespace Drupal\symfony_mailer\Plugin\MailerTransport;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Site\Settings;

/**
 * Defines the native Mail Transport plug-in.
 *
 * @MailerTransport(
 *   id = "sendmail",
 *   label = @Translation("Sendmail"),
 *   description = @Translation("Use the local sendmail binary to send emails."),
 * )
 */
class SendmailTransport extends TransportBase {

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'query' => ['command' => ''],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $commands = Settings::get('mailer_sendmail_commands', []);
    $commands = ['' => $this->t('&lt;Default&gt;')] + array_combine($commands, $commands);

    $form['command'] = [
      '#type' => 'radios',
      '#title' => $this->t('Command'),
      '#default_value' => $this->configuration['query']['command'],
      '#description' => $this->t('Sendmail command to execute. Configure available commands by setting the variable %var in %file.', [
        '%var' => 'mailer_sendmail_commands',
        '%file' => 'settings.php',
      ]),
      '#options' => $commands,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    $this->configuration['query']['command'] = $form_state->getValue('command');
  }

}
