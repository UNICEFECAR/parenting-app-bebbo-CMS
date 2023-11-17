<?php

namespace Drupal\symfony_mailer;

use Drupal\Core\Config\Entity\ConfigEntityInterface;

/**
 * Provides an interface for the Email Factory.
 */
interface EmailFactoryInterface {

  /**
   * Sends an email of a specific type, unrelated to a config entity.
   *
   * @param string $type
   *   Type. @see \Drupal\symfony_mailer\EmailInterface::getType()
   * @param string $sub_type
   *   Sub-type. @see \Drupal\symfony_mailer\EmailInterface::getSubType()
   * @param mixed $params
   *   Parameters for building this email.
   *
   * @return \Drupal\symfony_mailer\EmailInterface
   *   A new email object.
   */
  public function sendTypedEmail(string $type, string $sub_type, ...$params);

  /**
   * Sends an email related to a config entity.
   *
   * @param \Drupal\Core\Config\Entity\ConfigEntityInterface $entity
   *   Entity. @see \Drupal\symfony_mailer\EmailInterface::getEntity()
   * @param string $sub_type
   *   Sub-type. @see \Drupal\symfony_mailer\EmailInterface::getSubType()
   * @param mixed $params
   *   Parameters for building this email.
   *
   * @return \Drupal\symfony_mailer\EmailInterface
   *   A new email object.
   */
  public function sendEntityEmail(ConfigEntityInterface $entity, string $sub_type, ...$params);

  /**
   * Creates an email of a specific type, unrelated to a config entity.
   *
   * The email is not sent, allowing the caller to modify it before sending.
   * Normally it is recommended to call ::sendModuleMail() instead, and allow
   * the EmailBuilder to create the mail.
   *
   * @param string $type
   *   Type. @see \Drupal\symfony_mailer\EmailInterface::getType()
   * @param string $sub_type
   *   Sub-type. @see \Drupal\symfony_mailer\EmailInterface::getSubType()
   * @param mixed $params
   *   Parameters for building this email.
   *
   * @return \Drupal\symfony_mailer\EmailInterface
   *   A new email object.
   */
  public function newTypedEmail(string $type, string $sub_type, ...$params);

  /**
   * Creates an email related to a config entity.
   *
   * The email is not sent, allowing the caller to modify it before sending.
   * Normally it is recommended to call ::sendEntityMail() instead, and allow
   * the EmailBuilder to create the mail.
   *
   * @param \Drupal\Core\Config\Entity\ConfigEntityInterface $entity
   *   Entity. @see \Drupal\symfony_mailer\EmailInterface::getEntity()
   * @param string $sub_type
   *   Sub-type. @see \Drupal\symfony_mailer\EmailInterface::getSubType()
   * @param mixed $params
   *   Parameters for building this email.
   *
   * @return \Drupal\symfony_mailer\EmailInterface
   *   A new email object.
   */
  public function newEntityEmail(ConfigEntityInterface $entity, string $sub_type, ...$params);

}
