<?php

namespace Drupal\symfony_mailer\Plugin\EmailBuilder;

use Drupal\symfony_mailer\EmailFactoryInterface;
use Drupal\symfony_mailer\EmailInterface;
use Drupal\symfony_mailer\Entity\MailerPolicy;
use Drupal\symfony_mailer\MailerHelperTrait;
use Drupal\symfony_mailer\Processor\EmailBuilderBase;
use Drupal\symfony_mailer\Processor\TokenProcessorTrait;
use Drupal\user\UserInterface;

/**
 * Defines the Email Builder plug-in for user module.
 *
 * @EmailBuilder(
 *   id = "user",
 *   sub_types = {
 *     "cancel_confirm" = @Translation("Account cancellation confirmation"),
 *     "password_reset" = @Translation("Password recovery"),
 *     "register_admin_created" = @Translation("Account created by administrator"),
 *     "register_no_approval_required" = @Translation("Registration confirmation (No approval required)"),
 *     "register_pending_approval" = @Translation("Registration confirmation (Pending approval)"),
 *     "register_pending_approval_admin" = @Translation("Admin (user awaiting approval)"),
 *     "status_activated" = @Translation("Account activation"),
 *     "status_blocked" = @Translation("Account blocked"),
 *     "status_canceled" = @Translation("Account cancelled"),
 *   },
 *   override = TRUE,
 *   common_adjusters = {"email_subject", "email_body", "email_skip_sending"},
 *   import = @Translation("User email settings"),
 *   import_warning = @Translation("This overrides the default HTML messages with imported plain text versions"),
 *   config_overrides = {
 *     "user.settings" = {
 *       "notify" = {
 *         "cancel_confirm" = TRUE,
 *         "password_reset" = TRUE,
 *         "status_activated" = TRUE,
 *         "status_blocked" = TRUE,
 *         "status_canceled" = TRUE,
 *         "register_admin_created" = TRUE,
 *         "register_no_approval_required" = TRUE,
 *         "register_pending_approval" = TRUE,
 *       },
 *     },
 *   },
 *   form_alter = {
 *     "user_admin_settings" = {
 *       "remove" = {
 *         "mail_notification_address",
 *         "email_admin_created",
 *         "email_pending_approval",
 *         "email_pending_approval_admin",
 *         "email_no_approval_required",
 *         "email_password_reset",
 *         "email_activated",
 *         "email_blocked",
 *         "email_cancel_confirm",
 *         "email_canceled",
 *       },
 *       "type" = "user",
 *     },
 *   },
 * )
 *
 * @todo Notes for adopting Symfony Mailer into Drupal core. This builder can
 * set langcode, to, reply-to so the calling code doesn't need to.
 */
class UserEmailBuilder extends EmailBuilderBase {

  use MailerHelperTrait;
  use TokenProcessorTrait;

  /**
   * Saves the parameters for a newly created email.
   *
   * @param \Drupal\symfony_mailer\EmailInterface $email
   *   The email to modify.
   * @param \Drupal\user\UserInterface $user
   *   The user.
   */
  public function createParams(EmailInterface $email, UserInterface $user = NULL) {
    assert($user != NULL);
    $email->setParam('user', $user);
  }

  /**
   * {@inheritdoc}
   */
  public function fromArray(EmailFactoryInterface $factory, array $message) {
    return $factory->newTypedEmail($message['module'], $message['key'], $message['params']['account']);
  }

  /**
   * {@inheritdoc}
   */
  public function build(EmailInterface $email) {
    if ($email->getSubType() != 'register_pending_approval_admin') {
      $email->setTo($email->getParam('user'));
    }
    $this->tokenOptions(['callback' => 'user_mail_tokens', 'clear' => TRUE]);
  }

  /**
   * {@inheritdoc}
   */
  public function import() {
    $config_factory = $this->helper()->config();
    $notify = $config_factory->get('user.settings')->get('notify');
    $mail = $config_factory->get('user.mail')->get();
    unset($mail['langcode']);
    unset($mail['_core']);

    if ($mail_notification = $config_factory->get('system.site')->get('mail_notification')) {
      $notification_policy = $this->helper()->policyFromAddresses($this->helper()->parseAddress($mail_notification));
      $config['email_from'] = $notification_policy;
      MailerPolicy::import("user", $config);
    }

    foreach ($mail as $sub_type => $values) {
      $config = [
        'email_subject' => ['value' => $values["subject"]],
        'email_body' => [
          'content' => [
            'value' => $values["body"],
            'format' => 'plain_text',
          ],
        ],
      ];
      if (isset($notify[$sub_type]) && !$notify[$sub_type]) {
        $config['email_skip_sending']['message'] = 'Notification disabled in settings';
      }
      if (($sub_type == 'register_pending_approval_admin') && isset($notification_policy)) {
        $config['email_to'] = $notification_policy;
      }
      MailerPolicy::import("user.$sub_type", $config);
    }
  }

}
