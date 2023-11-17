<?php

namespace Drupal\symfony_mailer\Plugin\MailerTransport;

use Drupal\Core\Form\FormStateInterface;

/**
 * Defines the native Mail Transport plug-in.
 *
 * @MailerTransport(
 *   id = "native",
 *   label = @Translation("Native"),
 *   description = @Translation("Use the sendmail binary and options configured in the sendmail_path setting of php.ini."),
 *   warning = @Translation("<b>Not recommended</b>, prefer Sendmail. If php.ini uses the sendmail -t command, you won't have error reporting and Bcc headers won't be removed."),
 * )
 */
class NativeTransport extends TransportBase {

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
  }

}
