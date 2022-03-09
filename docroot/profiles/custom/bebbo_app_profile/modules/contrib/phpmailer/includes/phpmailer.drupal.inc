<?php

/**
 * @file
 * Implements PHPMailer support on behalf of Drupal core.
 */

use Drupal\phpmailer\Plugin\Mail\DrupalPHPMailer;

/**
 * Sends an already formatted e-mail message composed by drupal_mail().
 *
 * @param $message
 *   A message array, as described in hook_mail_alter().
 * @return
 *   TRUE if the mail was successfully accepted, otherwise FALSE.
 *
 * @see DrupalPHPMailer::mail()
 *
 * @todo Consider to either drop this entirely, or move into phpmailer.module
 *   as singleton to instantiate the DrupalPHPMailer class.
 *
 * @todo This might be totally unnecessary in Drupal 8, but testing needs to be
 *   done to verify.
 */
function phpmailer_send($message) {
  $mail = &drupal_static(__FUNCTION__);

  if (!isset($mail)) {
    $mail = new DrupalPHPMailer();
  }

  return $mail->mail($message);
}
