<?php

namespace Drupal\symfony_mailer;

use Drupal\Core\Session\AccountInterface;
use Drupal\user\Entity\User;
use Symfony\Component\Mime\Address as SymfonyAddress;

/**
 * Defines the class for an Email address.
 *
 * This class is used for the address headers on an email. For the to address,
 * it encodes extra information to customise the email for the recipients:
 * langcode and account.
 */
class Address implements AddressInterface {

  /**
   * The email address.
   *
   * @var string
   */
  protected $email;

  /**
   * The display name, or NULL.
   *
   * @var string
   */
  protected $displayName;

  /**
   * The language code, or NULL.
   *
   * @var string
   */
  protected $langcode;

  /**
   * The account, or NULL.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $account;

  /**
   * Constructs an address object.
   *
   * @param string $email
   *   The email address.
   * @param string $display_name
   *   (Optional) The display name.
   * @param string $langcode
   *   (Optional) The language code.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   (Optional) The account.
   */
  public function __construct(string $email, string $display_name = NULL, string $langcode = NULL, AccountInterface $account = NULL) {
    $this->email = $email;
    $this->displayName = $display_name;
    $this->langcode = $langcode;
    $this->account = $account;
  }

  /**
   * {@inheritdoc}
   */
  public static function create($address) {
    if ($address instanceof AddressInterface) {
      return $address;
    }
    elseif (is_string($address)) {
      if ($address == '<site>') {
        $site_config = \Drupal::config('system.site');
        $site_mail = $site_config->get('mail') ?: ini_get('sendmail_from');
        return new static($site_mail, $site_config->get('name'));
      }
      elseif ($user = user_load_by_mail($address)) {
        return static::create($user);
      }
      else {
        return new static($address);
      }
    }
    elseif ($address instanceof AccountInterface) {
      return new static($address->getEmail(), $address->getDisplayName(), $address->getPreferredLangcode(), $address);
    }
    elseif ($address instanceof SymfonyAddress) {
      return new static($address->getAddress(), $address->getName());
    }
    else {
      throw new \LogicException('Cannot convert to address.');
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getEmail() {
    return $this->email;
  }

  /**
   * {@inheritdoc}
   */
  public function getDisplayName() {
    return $this->displayName;
  }

  /**
   * {@inheritdoc}
   */
  public function getLangcode() {
    return $this->langcode;
  }

  /**
   * {@inheritdoc}
   */
  public function getAccount() {
    return $this->account;
  }

  /**
   * {@inheritdoc}
   */
  public function getSymfony() {
    return new SymfonyAddress($this->email, $this->displayName ?? '');
  }

  /**
   * {@inheritdoc}
   */
  public static function convert($addresses) {
    $result = [];

    if (!is_array($addresses)) {
      $addresses = [$addresses];
    }

    foreach ($addresses as $address) {
      $result[] = static::create($address);
    }

    return $result;
  }

  /**
   * {@inheritdoc}
   *
   * Serialization is intended only for testing.
   *
   * @internal
   */
  public function __serialize() {
    return [$this->email, $this->displayName, $this->langcode,
      $this->account ? $this->account->id() : NULL,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function __unserialize(array $data) {
    [$this->email, $this->displayName, $this->langcode, $account_id] = $data;
    if ($account_id) {
      $this->account = User::load($account_id);
    }
  }

}
