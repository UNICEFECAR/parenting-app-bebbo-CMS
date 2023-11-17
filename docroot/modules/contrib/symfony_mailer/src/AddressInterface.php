<?php

namespace Drupal\symfony_mailer;

/**
 * Defines the interface for an Email address.
 */
interface AddressInterface {

  /**
   * Creates an address from various other data types.
   *
   * @param mixed $address
   *   The input address, one of the following:
   *   - \Drupal\symfony_mailer\AddressInterface
   *   - string containing a single email address without display name
   *   - \Drupal\Core\Session\AccountInterface
   *   - \Symfony\Component\Mime\Address.
   *
   * @return \Drupal\symfony_mailer\AddressInterface
   *   The address.
   */
  public static function create($address);

  /**
   * Gets the email address of this address.
   *
   * @return string
   *   The email address.
   */
  public function getEmail();

  /**
   * Gets the display name of this address.
   *
   * @return string
   *   The display name.
   */
  public function getDisplayName();

  /**
   * Gets the language code of this address.
   *
   * @return string
   *   The language code.
   */
  public function getLangcode();

  /**
   * Gets the account associated with the recipient of this email.
   *
   * @return \Drupal\Core\Session\AccountInterface
   *   The account.
   */
  public function getAccount();

  /**
   * Gets a Symfony address object from this address.
   *
   * @return \Symfony\Component\Mime\Address
   *   The Symfony address.
   */
  public function getSymfony();

  /**
   * Converts one or more addresses.
   *
   * @param mixed $addresses
   *   The addresses to set. Can be a single element or an array of data types
   *   accepted by static::create().
   *
   * @return \Drupal\symfony_mailer\AddressInterface[]
   *   The converted addresses.
   */
  public static function convert($addresses);

}
