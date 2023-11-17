<?php

namespace Drupal\symfony_mailer\Plugin\EmailBuilder;

use Drupal\simplenews\SubscriberInterface;
use Drupal\symfony_mailer\EmailFactoryInterface;
use Drupal\symfony_mailer\EmailInterface;
use Drupal\symfony_mailer\Entity\MailerPolicy;

/**
 * Defines the Email Builder plug-in for simplenews module.
 *
 * @EmailBuilder(
 *   id = "simplenews",
 *   sub_types = {
 *     "subscribe" = @Translation("Subscription confirmation"),
 *     "validate" = @Translation("Validate"),
 *   },
 *   override = {"simplenews.subscribe_combined", "simplenews.validate"},
 *   common_adjusters = {"email_subject", "email_body"},
 *   import = @Translation("Simplenews subscriber settings"),
 *   import_warning = @Translation("This overrides the default HTML messages with imported plain text versions"),
 *   form_alter = {
 *     "simplenews_admin_settings_newsletter" = {
 *       "remove" = { "simplenews_default_options", "simplenews_sender_info" },
 *     },
 *     "simplenews_admin_settings_subscription" = {
 *       "remove" = { "subscription_mail" },
 *       "type" = "simplenews",
 *     },
 *   },
 * )
 */
class SimplenewsEmailBuilder extends SimplenewsEmailBuilderBase {

  /**
   * Saves the parameters for a newly created email.
   *
   * @param \Drupal\symfony_mailer\EmailInterface $email
   *   The email to modify.
   * @param \Drupal\simplenews\SubscriberInterface $subscriber
   *   The subscriber.
   */
  public function createParams(EmailInterface $email, SubscriberInterface $subscriber = NULL) {
    assert($subscriber != NULL);
    $email->setParam('simplenews_subscriber', $subscriber);
  }

  /**
   * {@inheritdoc}
   */
  public function fromArray(EmailFactoryInterface $factory, array $message) {
    $key = ($message['key'] == 'subscribe_combined') ? 'subscribe' : 'validate';
    return $factory->newTypedEmail('simplenews', $key, $message['params']['context']['simplenews_subscriber']);
  }

  /**
   * {@inheritdoc}
   */
  public function import() {
    $subscription = $this->helper()->config()->get('simplenews.settings')->get('subscription');

    $convert = [
      'confirm_combined' => 'subscribe',
      'validate' => 'validate',
    ];

    foreach ($convert as $from => $to) {
      $config = [
        'email_subject' => ['value' => $subscription["{$from}_subject"]],
        'email_body' => [
          'content' => [
            'value' => $subscription["{$from}_body"],
            'format' => 'plain_text',
          ],
        ],
      ];
      MailerPolicy::import("simplenews.$to", $config);
    }
  }

}
