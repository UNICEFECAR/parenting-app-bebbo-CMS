<?php

namespace Drupal\phpmailer\Controller;

use Drupal\Core\Session\AccountInterface;

/**
 * Access check for user tracker routes.
 */
class PHPMailerMailPreviewController {

  /**
   * Determines access for the HTML mail preview page.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The currently logged in account.
   *
   * @return bool
   *   The access result.
   */
  public function access(AccountInterface $account) {
    /**
     * @todo This may need to be fixed for D8.
     */
    if (\Drupal::moduleHandler()->moduleExists('mimemail')) {
      return $account->hasPermission('administer phpmailer settings');
    }
    return FALSE;
  }

  /**
   * Displays a preview of the message that is about to be sent.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The currently logged in account.
   *
   * @return bool
   *   The access result.
   */
  public function content(AccountInterface $account) {


    /**
     * @todo This needs to be fixed for D8.
     */


    // Suppress devel output in preview.
    $GLOBALS['devel_shutdown'] = TRUE;

    $mailkey = 'phpmailer_preview';
    // Use example address to prevent usage of configurable mail format setting.
    $recipient = 'test@example.com';
    // @see user_register_submit()
    $lang_code = $account->getPreferredLangcode($account);
    $variables = user_mail_tokens($account, $lang_code);
    $variables['!password'] = 'test';
    /**
     * @todo This function is not in D8.
     */
    $subject = _user_mail_text('register_no_approval_required_subject', $lang_code, $variables);
    $body = _user_mail_text('register_no_approval_required_body', $lang_code, $variables);
    $sender = NULL;
    $headers = [];

    // Convert non-html messages.
    // @see drupal_mail_wrapper()
    /**
     * @todo Convert to D8.
     */
    $format = variable_get('mimemail_format', FILTER_FORMAT_DEFAULT);
    $body = check_markup($body, $format, FALSE);
    // @see mimemail_prepare()
    /**
     * @todo Convert to D8.
     */
    $body = theme('mimemail_message', $body, $mailkey);
    foreach (module_implements('mail_post_process') as $module) {
      $function = $module .'_mail_post_process';
      $function($body, $mailkey);
    }

    print $body;
  }

}
