<?php

namespace Drupal\symfony_mailer;

use Symfony\Component\Mime\Header\Headers;

/**
 * Defines an interface related to the Symfony Email object.
 *
 * The functions are mostly identical, except that set accessors are explicitly
 * named, e.g. setSubject() instead of subject(). Exceptions:
 * - No 'returnPath': should only be set by the SMTP server.
 *
 *   @see https://www.postmastery.com/about-the-return-path-header/
 * - No 'date': defaults automatically, can still override via getHeaders() if
 *   needed.
 * - Accept MarkupInterface for 'subject'.
 * - Remove all references to charset: always use utf-8.
 * - Remove all references to Symfony 'resource': these don't really apply in
 *   the Drupal environment.
 */
interface BaseEmailInterface {

  /**
   * Sets the sender address.
   *
   * @param mixed $address
   *   The address to set.
   *
   * @return $this
   */
  public function setSender($address);

  /**
   * Gets the sender address.
   *
   * @return \Drupal\symfony_mailer\AddressInterface
   *   The sender address, or NULL if not set.
   */
  public function getSender(): ?AddressInterface;

  /**
   * Sets addresses for the specified header.
   *
   * @param string $name
   *   The name of the header to set.
   * @param mixed $addresses
   *   The addresses to set, see Address::convert(). Passing NULL as a value
   *   will erase the specified header.
   *
   * @return $this
   */
  public function setAddress(string $name, $addresses);

  /**
   * Gets addresses for the specified header.
   *
   * @param string $name
   *   The name of the header to get.
   *
   * @return \Drupal\symfony_mailer\AddressInterface[]
   *   The email addresses for the specified header.
   */
  public function getAddress(string $name): array;

  /**
   * Sets one or more from addresses.
   *
   * @param mixed $addresses
   *   The addresses to set, see Address::convert().
   *
   * @return $this
   */
  public function setFrom($addresses);

  /**
   * Gets the from addresses.
   *
   * @return \Drupal\symfony_mailer\AddressInterface[]
   *   The from addresses.
   */
  public function getFrom(): array;

  /**
   * Sets "reply-to" addresses.
   *
   * @param mixed $addresses
   *   The addresses to set, see Address::convert(). Passing NULL as a value
   *   will erase the reply-to address.
   *
   * @return $this
   */
  public function setReplyTo($addresses);

  /**
   * Gets the reply-to addresses.
   *
   * @return \Drupal\symfony_mailer\AddressInterface[]
   *   The reply-to addresses.
   */
  public function getReplyTo(): array;

  /**
   * Sets "to" addresses.
   *
   * Valid: build.
   *
   * @param mixed $addresses
   *   The addresses to set, see Address::convert(). Passing NULL as a value
   *   will erase "to" address.
   *
   * @return $this
   */
  public function setTo($addresses);

  /**
   * Gets the to addresses.
   *
   * @return \Drupal\symfony_mailer\AddressInterface[]
   *   The to addresses.
   */
  public function getTo(): array;

  /**
   * Sets "cc" addresses.
   *
   * @param mixed $addresses
   *   The addresses to set, see Address::convert(). Passing NULL as a value
   *   will erase "сс" addresses.
   *
   * @return $this
   */
  public function setCc($addresses);

  /**
   * Gets the cc addresses.
   *
   * @return \Drupal\symfony_mailer\AddressInterface[]
   *   The cc addresses.
   */
  public function getCc(): array;

  /**
   * Sets "bcc" addresses.
   *
   * @param mixed $addresses
   *   The addresses to set, see Address::convert(). Passing NULL as a value
   *   will erase "bсс" addresses.
   *
   * @return $this
   */
  public function setBcc($addresses);

  /**
   * Gets the bcc addresses.
   *
   * @return \Drupal\symfony_mailer\AddressInterface[]
   *   The bcc addresses.
   */
  public function getBcc(): array;

  /**
   * Sets the priority of this message.
   *
   * @param int $priority
   *   The priority, where 1 is the highest priority and 5 is the lowest.
   *
   * @return $this
   */
  public function setPriority(int $priority);

  /**
   * Get the priority of this message.
   *
   * @return int
   *   The priority, where 1 is the highest priority and 5 is the lowest.
   */
  public function getPriority(): int;

  /**
   * Sets the text body.
   *
   * By default, the text body will be generated from the unrendered body using
   * EmailInterface::getBody(). This function can be used to set a custom
   * plain-text alternative,
   *
   * @param string $body
   *   The text body.
   *
   * @return $this
   */
  public function setTextBody(string $body);

  /**
   * Gets the text body.
   *
   * @return string
   *   The text body, or NULL if not set.
   */
  public function getTextBody(): ?string;

  /**
   * Sets the HTML body.
   *
   * Valid: after rendering. Email builders should instead call
   * EmailInterface::setBody() or related functions before rendering.
   *
   * @param string|null $body
   *   (optional) The HTML body, or NULL to remove the HTML body.
   *
   * @return $this
   */
  public function setHtmlBody(?string $body);

  /**
   * Gets the HTML body.
   *
   * Valid: after rendering.
   *
   * @return string
   *   The HTML body, or NULL if not set.
   */
  public function getHtmlBody(): ?string;

  /**
   * Adds an attachment from a path.
   *
   * @param string $path
   *   The path to the file.
   * @param string|null $name
   *   (optional) The file name. Defaults to the base name of the path.
   * @param string|null $mimeType
   *   (optional) The mime type. If omitted, the type will be guessed.
   *
   * @return $this
   */
  public function attachFromPath(string $path, string $name = NULL, string $mimeType = NULL);

  /**
   * Adds an attachment from temporary content that's not related to a path.
   *
   * If the content comes from a path (such as a local file, or web resource)
   * then use attachFromPath(). This is important to ensure security checking
   * runs based on the correct path.
   *
   * @param string $body
   *   The content of the attachment.
   * @param string|null $name
   *   (optional) The file name.
   * @param string|null $mimeType
   *   (optional) The mime type. If omitted, the type will be guessed.
   *
   * @return $this
   */
  public function attachNoPath(string $body, string $name = NULL, string $mimeType = NULL);

  // @codingStandardsIgnoreStart
  /**
   * @return $this
   */
  // public function embedFromPath(string $path, string $name = null, string $contentType = null);
  // @codingStandardsIgnoreEnd

  /**
   * Gets the headers object for getting or setting headers.
   *
   * @return \Symfony\Component\Mime\Header\Headers
   *   The headers object.
   */
  public function getHeaders(): Headers;

  /**
   * Adds a text header.
   *
   * @param string $name
   *   The name of the header.
   * @param string $value
   *   The header value.
   *
   * @return $this
   */
  public function addTextHeader(string $name, string $value);

}
