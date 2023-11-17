<?php

namespace Drupal\symfony_mailer\Processor;

use Drupal\symfony_mailer\EmailFactoryInterface;
use Drupal\symfony_mailer\EmailInterface;

/**
 * Defines the interface for Email Builders.
 */
interface EmailBuilderInterface extends EmailProcessorInterface {

  /**
   * Saves the parameters for a newly created email.
   *
   * @param \Drupal\symfony_mailer\EmailInterface $email
   *   The email to modify.
   */
  public function createParams(EmailInterface $email);

  /**
   * Creates an email from a message array.
   *
   * Implement this function to support conversion from the old email
   * interface. This function will eventually be deprecated and removed.
   *
   * @param \Drupal\symfony_mailer\EmailFactoryInterface $factory
   *   The Email Factory for creating the email.
   * @param array $message
   *   The array to create from.
   */
  public function fromArray(EmailFactoryInterface $factory, array $message);

  /**
   * Imports Mailer Policy from legacy email settings.
   *
   * Implement this function if "import" is set in the EmailBuilder annotation.
   */
  public function import();

}
