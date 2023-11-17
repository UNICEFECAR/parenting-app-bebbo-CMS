<?php

namespace Drupal\symfony_mailer\Plugin\EmailBuilder;

use Drupal\Core\Site\Settings;
use Drupal\Core\Url;
use Drupal\symfony_mailer\EmailFactoryInterface;
use Drupal\symfony_mailer\EmailInterface;
use Drupal\symfony_mailer\Entity\MailerPolicy;
use Drupal\symfony_mailer\Exception\SkipMailException;
use Drupal\symfony_mailer\MailerHelperTrait;
use Drupal\symfony_mailer\Processor\EmailBuilderBase;
use Drupal\update\UpdateManagerInterface;

/**
 * Defines the Email Builder plug-in for update module.
 *
 * @EmailBuilder(
 *   id = "update",
 *   sub_types = { "status_notify" = @Translation("Available updates") },
 *   override = TRUE,
 *   common_adjusters = {"email_subject", "email_body", "email_to"},
 *   import = @Translation("Update notification addresses"),
 *   config_overrides = {
 *     "update.settings" = {
 *       "notification" = { "emails" = { "dummy" } },
 *     },
 *   },
 *   form_alter = {
 *     "update_settings" = {
 *       "remove" = { "update_notify_emails" },
 *       "type" = "update",
 *     },
 *   },
 * )
 *
 * The notification address is configured using Mailer Policy for
 * UpdateEmailBuilder. Set a dummy value in update.settings to force the update
 * module to send an email. NB UpdateEmailBuilder ignores the passed 'To'
 * address so the dummy value will never be used.
 */
class UpdateEmailBuilder extends EmailBuilderBase {

  use MailerHelperTrait;

  /**
   * {@inheritdoc}
   */
  public function fromArray(EmailFactoryInterface $factory, array $message) {
    return $factory->newTypedEmail($message['module'], $message['key']);
  }

  /**
   * {@inheritdoc}
   */
  public function build(EmailInterface $email) {
    $config = $this->helper()->config();
    $notify_all = ($config->get('update.settings')->get('notification.threshold') == 'all');
    \Drupal::moduleHandler()->loadInclude('update', 'install');
    $requirements = update_requirements('runtime');

    foreach (['core', 'contrib'] as $report_type) {
      $status = $requirements["update_$report_type"];
      if (isset($status['severity'])) {
        if ($status['severity'] == REQUIREMENT_ERROR || ($notify_all && $status['reason'] == UpdateManagerInterface::NOT_CURRENT)) {
          $messages[] = _update_message_text($report_type, $status['reason']);
        }
      }
    }

    $site_name = $config->get('system.site')->get('name');
    $email->setVariable('site_name', $site_name)
      ->setVariable('update_status', Url::fromRoute('update.status')->toString())
      ->setVariable('update_settings', Url::fromRoute('update.settings')->toString())
      ->setVariable('messages', $messages);

    if (Settings::get('allow_authorize_operations', TRUE)) {
      $email->setVariable('update_manager', Url::fromRoute('update.report_update')->toString());
    }
  }

  /**
   * Skip sending the update email when no 'To' header is configured.
   *
   * This check has to be done after
   * {@see \Drupal\symfony_mailer\EmailInterface::PHASE_BUILD} because
   * otherwise the 'To' header might not be set yet.
   *
   * @throws \Drupal\symfony_mailer\Exception\SkipMailException
   *
   * @see \Drupal\symfony_mailer\EmailInterface::PHASE_PRE_RENDER
   */
  public function preRender(EmailInterface $email): void {
    if (empty($email->getTo())) {
      throw new SkipMailException('No update notification address configured.');
    }
  }

  /**
   * {@inheritdoc}
   */
  public function import() {
    // Get without overrides to avoid the dummy value
    // set by MailerBcConfigOverride.
    $mail_notification = implode(',', $this->helper()->config()->get('update.settings')->getOriginal('notification.emails', FALSE));

    if ($mail_notification) {
      $notification_policy = $this->helper()->policyFromAddresses($this->helper()->parseAddress($mail_notification));
      $config['email_to'] = $notification_policy;
      MailerPolicy::import("update.status_notify", $config);
    }
  }

}
