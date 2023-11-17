<?php

namespace Drupal\symfony_mailer;

use Drupal\Component\Render\MarkupInterface;

/**
 * Provides the legacy mailer helper service.
 */
class LegacyMailerHelper implements LegacyMailerHelperInterface {

  /**
   * List of lower-cased address headers.
   *
   * Some address headers are stored directly in $message in addition to
   * $message['headers']. The array value indicates whether this is the case.
   *
   * @var array
   */
  protected const ADDRESS_HEADERS = [
    'from' => TRUE,
    'reply-to' => TRUE,
    'to' => TRUE,
    'cc' => FALSE,
    'bcc' => FALSE,
  ];

  /**
   * List of lower-cased headers to skip copying from the array.
   *
   * @var array
   */
  protected const SKIP_HEADERS = [
    // Set by Symfony mailer library.
    'content-transfer-encoding' => TRUE,
    'content-type' => TRUE,
    'date' => TRUE,
    'message-id' => TRUE,
    'mime-version' => TRUE,

    // Set by sending MTA.
    'return-path' => TRUE,
  ];

  /**
   * The mailer helper.
   *
   * @var \Drupal\symfony_mailer\MailerHelperInterface
   */
  protected $mailerHelper;

  /**
   * Constructs the MailerHelper object.
   *
   * @param \Drupal\symfony_mailer\MailerHelperInterface $mailer_helper
   *   The mailer helper.
   */
  public function __construct(MailerHelperInterface $mailer_helper) {
    $this->mailerHelper = $mailer_helper;
  }

  /**
   * {@inheritdoc}
   */
  public function formatBody(array $body_array) {
    foreach ($body_array as $part) {
      if ($part instanceof MarkupInterface) {
        $body[] = ['#markup' => $part];
      }
      else {
        $body[] = [
          '#type' => 'processed_text',
          '#text' => $part,
        ];
      }
    }
    return $body ?? [];
  }

  /**
   * {@inheritdoc}
   */
  public function emailToArray(EmailInterface $email, array &$message) {
    $message['subject'] = $email->getSubject();
    if ($email->getPhase() >= EmailInterface::PHASE_POST_RENDER) {
      $message['body'] = $email->getHtmlBody();
    }

    foreach ($email->getHeaders()->all() as $name => $header) {
      $lc_name = strtolower($name);
      if (isset(self::SKIP_HEADERS[$lc_name])) {
        continue;
      }

      // Copy the header.
      $message['headers'][$name] = $header->getBodyAsString();
      if (!empty(self::ADDRESS_HEADERS[$lc_name])) {
        // Also copy directly to $message.
        $message[$lc_name] = $message['headers'][$name];
      }
    }

    // Drupal doesn't store the 'To' header in $message['headers'].
    unset($message['headers']['To']);
  }

  /**
   * {@inheritdoc}
   */
  public function emailFromArray(EmailInterface $email, array $message) {
    $email->setSubject($message['subject']);

    // Attachments.
    $attachments = $message['params']['attachments'] ?? [];
    foreach ($attachments as $attachment) {
      if (!empty($attachment['filepath'])) {
        $email->attachFromPath($attachment['filepath'], $attachment['filename'] ?? NULL, $attachment['filemime'] ?? NULL);
      }
      elseif (!empty($attachment['filecontent'])) {
        $email->attachNoPath($attachment["filecontent"], $attachment['filename'] ?? NULL, $attachment['filemime'] ?? NULL);
      }
    }

    // Headers.
    $src_headers = $message['headers'];
    $dest_headers = $email->getHeaders();

    // Add in 'To' header which is stored directly in the message.
    // @see \Drupal\Core\Mail\Plugin\Mail\PhpMail::mail()
    if (isset($message['to'])) {
      $src_headers['to'] = $message['to'];
    }

    foreach ($src_headers as $name => $value) {
      $name = strtolower($name);
      if (isset(self::SKIP_HEADERS[$name])) {
        continue;
      }

      if (isset(self::ADDRESS_HEADERS[$name])) {
        $email->setAddress($name, $this->mailerHelper->parseAddress($value));
      }
      else {
        $dest_headers->addHeader($name, $value);
      }
    }

    // Plain-text version.
    if (isset($message['plain'])) {
      $email->setTextBody($message['plain']);
    }
  }

}
