<?php

namespace Drupal\symfony_mailer\Processor;

use Drupal\symfony_mailer\EmailInterface;

/**
 * Defines the interface for Email Processors.
 */
interface EmailProcessorInterface {

  /**
   * Mapping from phase to default function name.
   *
   * @var string[]
   */
  public const FUNCTION_NAMES = [
    EmailInterface::PHASE_BUILD => 'build',
    EmailInterface::PHASE_PRE_RENDER => 'preRender',
    EmailInterface::PHASE_POST_RENDER => 'postRender',
    EmailInterface::PHASE_POST_SEND => 'postSend',
  ];

  /**
   * Initializes an email to call this email processor.
   *
   * @param \Drupal\symfony_mailer\EmailInterface $email
   *   The email to initialize.
   */
  public function init(EmailInterface $email);

  /**
   * Process emails during the build phase.
   *
   * Must not trigger any rendering because cannot yet rely on the correct
   * language, theme, and account. For example, must not cast a translatable
   * string into a plain string, or replace tokens.
   *
   * @param \Drupal\symfony_mailer\EmailInterface $email
   *   The email to process.
   */
  public function build(EmailInterface $email);

  /**
   * Process emails during the pre-render phase.
   *
   * Not normally needed. Only if there is a rendering step that needs to be
   * done before the main rendering call.
   *
   * @param \Drupal\symfony_mailer\EmailInterface $email
   *   The email to process.
   */
  public function preRender(EmailInterface $email);

  /**
   * Process emails during the post-render phase.
   *
   * Act on the rendered HTML, or any header.
   *
   * @param \Drupal\symfony_mailer\EmailInterface $email
   *   The email to process.
   */
  public function postRender(EmailInterface $email);

  /**
   * Process emails during the post-send phase.
   *
   * No further alterations allowed.
   *
   * @param \Drupal\symfony_mailer\EmailInterface $email
   *   The email to process.
   */
  public function postSend(EmailInterface $email);

  /**
   * Gets the weight of the email processor.
   *
   * @param int $phase
   *   The phase that will run, one of the EmailInterface::PHASE_ constants.
   *
   * @return int
   *   The weight.
   */
  public function getWeight(int $phase);

  /**
   * Gets the ID of the email processor.
   *
   * @return string
   *   The ID.
   */
  public function getId();

}
