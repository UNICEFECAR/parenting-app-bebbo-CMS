<?php

namespace Drupal\symfony_mailer\Plugin\EmailBuilder;

use Drupal\symfony_mailer\EmailInterface;
use Drupal\symfony_mailer\Processor\EmailBuilderBase;
use Drupal\symfony_mailer\Processor\TokenProcessorTrait;

/**
 * Defines the Email Builder plug-in for test mails.
 *
 * @EmailBuilder(
 *   id = "symfony_mailer",
 *   sub_types = { "test" = @Translation("Test email") },
 *   common_adjusters = {"email_subject", "email_body"},
 * )
 */
class TestEmailBuilder extends EmailBuilderBase {

  use TokenProcessorTrait;

  /**
   * Saves the parameters for a newly created email.
   *
   * @param \Drupal\symfony_mailer\EmailInterface $email
   *   The email to modify.
   * @param mixed $to
   *   The to addresses, see Address::convert().
   */
  public function createParams(EmailInterface $email, $to = NULL) {
    if ($to) {
      // For back-compatibility, allow $to to be NULL.
      $email->setParam('to', $to);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function build(EmailInterface $email) {
    if ($to = $email->getParam('to')) {
      $email->setTo($to);
    }

    // - Add a custom CSS library. The library is defined in
    //   \Drupal\symfony_mailer\symfony_mailer.libraries.yml. The CSS is
    //   defined in \Drupal\symfony_mailer\css\test.email.css.
    // - Set an parameter programmatically.
    //   The variable is used by the mailer policy which specifies the
    //   email title and body as defined in
    //   \Drupal\symfony_mailer\config\install\symfony_mailer.mailer_policy.symfony_mailer.test.yml.
    $email->addLibrary('symfony_mailer/test')
      ->setVariable('day', date("l"));
  }

}
