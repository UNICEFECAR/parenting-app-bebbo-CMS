<?php

/**
 * @file
 * Documentation of Symfony Mailer hooks.
 */

use Drupal\symfony_mailer\EmailInterface;

/**
 * Acts on email in a phase.
 *
 * The phase names are defined in EmailInterface::PHASE_NAMES.
 *
 * @param \Drupal\symfony_mailer\EmailInterface $email
 *   The email.
 */
function hook_mailer_PHASE(EmailInterface $email) {
  // hook_mailer_init():
  // - Add a class.
  // - Re-use an EmailAdjuster.
  (new CustomEmailProcessor())->init($email);
  $config = ['message' => 'Unpopular user skipped'];
  Drupal::service('plugin.manager.email_adjuster')->createInstance('email_skip_sending', $config)->init($email);

  // hook_mailer_build():
  $email->setTo('user@example.com');
  $body = $email->getBody();
  $body['extra'] = ['#markup' => 'Extra text'];
  $email->setBody($body);

  // hook_mailer_post_render():
  $email->setHtmlBody($email->getHtmlBody() . '<p><b>More</b> extra text</p>');

  // hook_mailer_post_send():
  $to = $email->getHeaders()->get('To')->getBodyAsString();
  \Drupal::messenger()->addMessage(t('Email sent to %to.', ['%to' => $to]));
}

/**
 * Acts on an email in a phase for a specific email type.
 *
 * The phase names are defined in EmailInterface::PHASE_NAMES.
 *
 * @param \Drupal\symfony_mailer\EmailInterface $email
 *   The email.
 */
function hook_mailer_TYPE_PHASE(EmailInterface $email) {
}

/**
 * Acts on an email in a specific phase for a specific email type and sub-type.
 *
 * The phase names are defined in EmailInterface::PHASE_NAMES.
 *
 * @param \Drupal\symfony_mailer\EmailInterface $email
 *   The email.
 */
function hook_mailer_TYPE__SUBTYPE_PHASE(EmailInterface $email) {
}

/**
 * Alters email builder plug-in definitions.
 *
 * @param array $email_builders
 *   An associative array of all email builder definitions, keyed by the ID.
 */
function hook_mailer_builder_info_alter(array &$email_builders) {
}

/**
 * Alters mailer transport plug-in definitions.
 *
 * @param array $mailer_transports
 *   An associative array of all mailer transport definitions, keyed by the ID.
 */
function hook_mailer_transport_info_alter(array &$mailer_transports) {
}

/**
 * Alters email adjusters plug-in definitions.
 *
 * @param array $mailer_adjusters
 *   An associative array of all email adjuster definitions, keyed by the ID.
 */
function hook_mailer_adjuster_info_alter(array &$mailer_adjusters) {
}
