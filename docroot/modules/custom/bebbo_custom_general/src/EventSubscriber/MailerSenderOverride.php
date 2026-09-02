<?php

namespace Drupal\bebbo_custom_general\EventSubscriber;

use Drupal\Core\Config\ConfigFactoryInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Event\MessageEvent;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

/**
 * Overrides the email sender/from to match the SMTP-authenticated account.
 *
 * Office 365 rejects emails when the From address differs from the
 * authenticated SMTP user (SendAsDenied). This subscriber forces
 * From + envelope sender to admin@bebbo.app and sets Reply-To to
 * the original site email so replies still reach the right inbox.
 */
class MailerSenderOverride implements EventSubscriberInterface {

  public function __construct(
    protected ConfigFactoryInterface $configFactory,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      MessageEvent::class => ['onMessage', 0],
    ];
  }

  /**
   * Overrides From/Sender to the SMTP-authenticated address.
   */
  public function onMessage(MessageEvent $event): void {
    $message = $event->getMessage();
    if (!$message instanceof Email) {
      return;
    }

    $smtpUser = $this->configFactory
      ->get('symfony_mailer_office365.config')
      ->get('mail');
    if (empty($smtpUser)) {
      return;
    }

    $siteName = $this->configFactory
      ->get('system.site')
      ->get('name') ?: 'Bebbo';

    $smtpAddress = new Address($smtpUser, $siteName);

    // Preserve original From as Reply-To (only if Reply-To is not already set).
    $originalFrom = $message->getFrom();
    if (empty($message->getReplyTo()) && !empty($originalFrom)) {
      $message->replyTo(...$originalFrom);
    }

    $message->from($smtpAddress);
    $message->sender($smtpAddress);

    // Override envelope sender to match.
    $event->setEnvelope(new Envelope(
      $smtpAddress,
      $event->getEnvelope()->getRecipients(),
    ));
  }

}
