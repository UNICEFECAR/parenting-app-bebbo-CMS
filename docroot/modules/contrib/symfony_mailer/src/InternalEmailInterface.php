<?php

namespace Drupal\symfony_mailer;

use Drupal\Core\Session\AccountInterface;

/**
 * Defines an extended Email interface that adds internal functions.
 *
 * @internal
 */
interface InternalEmailInterface extends EmailInterface {

  /**
   * Runs processing of the current phase for all email processors.
   *
   * @return $this
   */
  public function process();

  /**
   * Ends the initialization phase.
   *
   * Valid: initialisation.
   *
   * @return $this
   */
  public function initDone();

  /**
   * Customizes the email.
   *
   * Valid: before rendering.
   *
   * @param string $langcode
   *   The language code.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The account.
   *
   * @return $this
   */
  public function customize(string $langcode, AccountInterface $account);

  /**
   * Renders the email.
   *
   * Valid: before rendering.
   *
   * @return $this
   */
  public function render();

  /**
   * Get the phase of processing.
   *
   * @return int
   *   The phase, one of the PHASE_ constants.
   */
  public function getPhase();

  /**
   * Gets the inner Symfony email to send.
   *
   * Valid: after rendering.
   *
   * @return \Symfony\Component\Mime\Email
   *   Inner Symfony email.
   */
  public function getSymfonyEmail();

  /**
   * Sets the error message from sending the email.
   *
   * Valid: after sending.
   *
   * @param string $error
   *   The error message.
   *
   * @return $this
   */
  public function setError(string $error);

}
