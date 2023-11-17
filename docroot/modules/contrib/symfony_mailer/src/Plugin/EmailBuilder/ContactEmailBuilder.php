<?php

namespace Drupal\symfony_mailer\Plugin\EmailBuilder;

use Drupal\contact\MessageInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\symfony_mailer\EmailFactoryInterface;
use Drupal\symfony_mailer\EmailInterface;

/**
 * Defines the Email Builder plug-in for contact module personal forms.
 *
 * @EmailBuilder(
 *   id = "contact",
 *   label = @Translation("Personal contact form"),
 *   sub_types = {
 *     "mail" = @Translation("Message"),
 *     "copy" = @Translation("Sender copy"),
 *   },
 *   override = {"contact.user_mail", "contact.user_copy"}
 * )
 *
 * @todo Notes for adopting Symfony Mailer into Drupal core. This builder can
 * set langcode, to, reply-to so the calling code doesn't need to.
 */
class ContactEmailBuilder extends ContactEmailBuilderBase {

  /**
   * Saves the parameters for a newly created email.
   *
   * @param \Drupal\symfony_mailer\EmailInterface $email
   *   The email to modify.
   * @param \Drupal\contact\MessageInterface $message
   *   Submitted message entity.
   * @param \Drupal\Core\Session\AccountInterface $sender
   *   The sender.
   * @param \Drupal\Core\Session\AccountInterface $recipient
   *   The recipient.
   */
  public function createParams(EmailInterface $email, MessageInterface $message = NULL, AccountInterface $sender = NULL, AccountInterface $recipient = NULL) {
    assert($recipient != NULL);
    $email->setParam('contact_message', $message)
      ->setParam('sender', $sender)
      ->setParam('recipient', $recipient);
  }

  /**
   * {@inheritdoc}
   */
  public function fromArray(EmailFactoryInterface $factory, array $message) {
    $sender = $message['params']['sender'];
    $contact_message = $message['params']['contact_message'];
    // Remove user_.
    $key = substr($message['key'], 5);
    return $factory->newTypedEmail('contact', $key, $contact_message, $sender, $message['params']['recipient']);
  }

  /**
   * {@inheritdoc}
   */
  public function build(EmailInterface $email) {
    parent::build($email);
    $recipient = $email->getParam('recipient');

    $email->setVariable('recipient_name', $recipient->getDisplayName())
      ->setVariable('recipient_edit_url', $recipient->toUrl('edit-form')->toString());

    if ($email->getSubType() == 'mail') {
      $email->setTo($recipient);
    }
  }

}
