<?php

namespace Drupal\symfony_mailer\Plugin\EmailBuilder;

use Drupal\contact\Entity\ContactForm;
use Drupal\contact\MessageInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Drupal\symfony_mailer\EmailFactoryInterface;
use Drupal\symfony_mailer\EmailInterface;
use Drupal\symfony_mailer\Entity\MailerPolicy;

/**
 * Defines the Email Builder plug-in for contact module page forms.
 *
 * @EmailBuilder(
 *   id = "contact_form",
 *   label = @Translation("Contact form"),
 *   sub_types = {
 *     "mail" = @Translation("Message"),
 *     "copy" = @Translation("Sender copy"),
 *     "autoreply" = @Translation("Auto-reply"),
 *   },
 *   has_entity = TRUE,
 *   override = {
 *     "contact.page_mail",
 *     "contact.page_copy",
 *     "contact.page_autoreply",
 *   },
 *   common_adjusters = {"email_subject", "email_body", "email_to"},
 *   import = @Translation("Contact form recipients"),
 *   form_alter = {
 *     "*" = {
 *       "remove" = { "recipients" },
 *       "default" = { "recipients" = "[site:mail]" },
 *       "entity_sub_type" = "mail",
 *     },
 *   }
 * )
 *
 * @todo Notes for adopting Symfony Mailer into Drupal core. This builder can
 * set langcode, to, reply-to so the calling code doesn't need to.
 */
class ContactPageEmailBuilder extends ContactEmailBuilderBase {

  /**
   * Saves the parameters for a newly created email.
   *
   * @param \Drupal\symfony_mailer\EmailInterface $email
   *   The email to modify.
   * @param \Drupal\contact\MessageInterface $message
   *   Submitted message entity.
   * @param \Drupal\Core\Session\AccountInterface $sender
   *   The sender.
   */
  public function createParams(EmailInterface $email, MessageInterface $message = NULL, AccountInterface $sender = NULL) {
    assert($sender != NULL);
    $email->setParam('contact_message', $message)
      ->setParam('sender', $sender);
  }

  /**
   * {@inheritdoc}
   */
  public function fromArray(EmailFactoryInterface $factory, array $message) {
    $sender = $message['params']['sender'];
    $contact_message = $message['params']['contact_message'];
    // Remove page_.
    $key = substr($message['key'], 5);
    return $factory->newEntityEmail($message['params']['contact_form'], $key, $contact_message, $sender);
  }

  /**
   * {@inheritdoc}
   */
  public function build(EmailInterface $email) {
    parent::build($email);
    $email->setVariable('form', $email->getEntity()->label())
      ->setVariable('form_url', Url::fromRoute('<current>')->toString());

    // @todo This should also be moved to mailer policy with an import.
    if ($email->getSubType() == 'autoreply') {
      $email->setBody($email->getEntity()->getReply());
    }
  }

  /**
   * {@inheritdoc}
   */
  public function import() {
    $helper = $this->helper();

    foreach (ContactForm::loadMultiple() as $id => $form) {
      if ($id != 'personal') {
        $addresses = $helper->parseAddress(implode(',', $form->getRecipients()));
        $config['email_to'] = $helper->policyFromAddresses($addresses);
        MailerPolicy::import("contact_form.mail.$id", $config);
      }
    }
  }

}
