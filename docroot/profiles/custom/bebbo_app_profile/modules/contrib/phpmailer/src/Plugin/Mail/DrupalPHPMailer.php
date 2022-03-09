<?php

namespace Drupal\phpmailer\Plugin\Mail;

use Drupal\Core\Mail\MailFormatHelper;
use Drupal\Core\Mail\MailInterface;
use PHPMailer;

/**
 * Implements the base PHPMailer class for the Drupal MailInterface.
 *
 * Base PHPMailer for Drupal implementation with support for SMTP keep-alive
 * and setting a custom Return-Path.
 *
 * @Mail(
 *   id = "phpmailer",
 *   label = @Translation("PHPMailer"),
 *   description = @Translation("Sends messages using the PHPMailer library, instead of Drupal's mail() function.")
 * )
 */
class DrupalPHPMailer extends PHPMailer implements MailInterface {

  /**
   * Stores the Return-Path, which may be different from Sender.
   */
  public $ReturnPath = '';

  /**
   * Verbose debug output level configured for Drupal.
   *
   * In order to be able to log error messages with actual error information and
   * see what actually went wrong for a particular message, PHPMailer::SMTPDebug
   * always needs to be enabled.
   *
   * DrupalPHPMailer::SmtpSend() overrides PHPMailer::SmtpSend() to capture the
   * debug output string and make it available for \Drupal::logger() calls.
   *
   * @see PHPMailer::SMTPDebug
   * @see SMTP::do_debug
   * @see DrupalPHPMailer::SmtpSend()
   * @see DrupalPHPMailer::drupalDebugOutput
   *
   * @var int
   */
  public $drupalDebug = 0;

  /**
   * Overrides PHPMailer::SMTPDebug to capture SMTP communication errors by default.
   */
  public $SMTPDebug = 2;

  /**
   * Stores the verbose debug output of the SMTP communication.
   *
   * @var string
   */
  public $drupalDebugOutput = '';

  /**
   * Constructor.
   */
  public function __construct() {
    // Throw exceptions instead of dying (since 5.0.0).
    if (method_exists(get_parent_class($this), '__construct')) {
      parent::__construct(TRUE);
    }
    $module_config = \Drupal::config('phpmailer.settings');

    $this->IsSMTP();
    $this->Reset();

    $this->Host = $module_config->get('smtp_host', '');
    if ($backup = $module_config->get('smtp_hostbackup', '')) {
      $this->Host .= ';' . $backup;
    }
    $this->Port = $module_config->get('smtp_port', '25');
    $smtp_protocol = $module_config->get('smtp_protocol', '');
    $this->SMTPSecure = $smtp_protocol;

    if (!empty($smtp_protocol)) {
      $ssl_verify_peer = $module_config->get('smtp_ssl_verify_peer');
      $this->SMTPOptions['ssl']['verify_peer'] = isset($ssl_verify_peer) ? $ssl_verify_peer : 1;
      $ssl_verify_peer_name = $module_config->get('smtp_ssl_verify_peer_name');
      $this->SMTPOptions['ssl']['verify_peer_name'] = isset($ssl_verify_peer_name) ? $ssl_verify_peer_name : 1;
      $ssl_allow_self_signed = $module_config->get('smtp_ssl_allow_self_signed');
      $this->SMTPOptions['ssl']['allow_self_signed'] = isset($ssl_allow_self_signed) ? $ssl_allow_self_signed : 0;
    }

    // Use SMTP authentication if both username and password are given.
    $this->Username = $module_config->get('smtp_username', '');
    $this->Password = $module_config->get('smtp_password', '');
    $this->SMTPAuth = (bool) ($this->Username != '' && $this->Password != '');

    $this->SMTPKeepAlive = $module_config->get('smtp_keepalive', 0);

    $this->Timeout = 30;

    $this->drupalDebug = $module_config->get('smtp_debug', 0);
    if ($this->drupalDebug > $this->SMTPDebug && \Drupal::currentUser()->hasPermission('administer phpmailer settings')) {
      $this->SMTPDebug = $this->drupalDebug;
    }
  }

  /**
   * Send mail via SMTP.
   *
   * Wrapper around PHPMailer::SmtpSend() with exception handling.
   */
  public function SmtpSend($header, $body) {
    if ($this->SMTPDebug) {
      // Clear possibly previously captured debug output.
      $this->drupalDebugOutput = '';
      ob_start();
    }

    try {
      $result = parent::SmtpSend($header, $body);

      // Close connection when not using SMTP keep-alive.
      if (!$this->SMTPKeepAlive) {
        $this->SmtpClose();
      }
    }
    catch (phpmailerException $exception) {}

    if ($this->SMTPDebug) {
      if ($this->drupalDebug && ($this->drupalDebugOutput = ob_get_contents())) {
        drupal_set_message($this->drupalDebugOutput);
        if (\Drupal::config('phpmailer.settings')->get('smtp_debug_log', 0)) {
          \Drupal::logger('phpmailer')->debug('Output of communication with SMTP server:<br /><pre>{output}</pre>', ['output' => print_r($this->drupalDebugOutput, TRUE)]);
        }
      }
      ob_end_clean();
    }

    // Reinitialize properties.
    $this->Reset();

    if (isset($exception)) {
      // Pass exception to caller.
      throw $exception;
    }
    return $result;
  }

  /**
   * (Re-)initialize properties after sending mail.
   */
  public function Reset() {
    $this->ClearAllRecipients();
    $this->ClearReplyTos();
    $this->ClearAttachments();
    $this->ClearCustomHeaders();

    $this->Priority    = 3;
    /**
     * @todo Need to find out where this variable comes from.
     */
//    $this->CharSet     = variable_get('smtp_charset', 'utf-8');
    $this->ContentType = 'text/plain';
    $this->Encoding    = '8bit';

    // Set default From name.
    $from_name = \Drupal::config('phpmailer.settings')->get('smtp_fromname');
    if ($from_name == '') {
      // Fall back on the site name.
      $from_name = \Drupal::config('system.site')->get('name');
    }
    $this->FromName   = $from_name;
    $this->Sender     = '';
    $this->MessageID  = '';
    $this->ReturnPath = '';
  }

  /**
   * Overrides PHPMailer::__destruct().
   */
  public function __destruct() {
    // Disable debug output if SMTP keep-alive is enabled.
    // PHP is most likely shutting down altogether (this class is instantiated
    // as a static singleton). Since logging facilities (e.g., database
    // connection) quite potentially have been shut down already, simply turn
    // off SMTP debugging. Without this override, debug output would be printed
    // on the screen and CLI output.
    if ($this->SMTPKeepAlive && isset($this->smtp->do_debug)) {
      $this->smtp->do_debug = 0;
    }
    parent::__destruct();
  }

  /**
   * Provide more user-friendly error messages.
   *
   * Note: messages should not end with a dot.
   */
  public function SetLanguage($langcode = 'en', $lang_path = 'language/') {
    // Retrieve English defaults to ensure all message keys are set.
    parent::SetLanguage('en');

    // Overload with Drupal translations.
    $this->language = [
      'authenticate'        => t('SMTP error: Could not authenticate.'),
      'connect_host'        => t('SMTP error: Could not connect to host.'),
      'data_not_accepted'   => t('SMTP error: Data not accepted.'),
      'smtp_connect_failed' => t('SMTP error: Could not connect to SMTP host.'),
      'smtp_error'          => t('SMTP server error: '),

      // Messages used during email generation.
      'empty_message'       => t('Message body empty'),
      'encoding'            => t('Unknown encoding: '),
      'variable_set'        => t('Cannot set or reset variable: '),

      'file_access'         => t('File error: Could not access file: '),
      'file_open'           => t('File error: Could not open file: '),

      // Non-administrative messages.
      'from_failed'         => t('The following From address failed: '),
      'invalid_address'     => t('Invalid address'),
      'provide_address'     => t('You must provide at least one recipient e-mail address.'),
      'recipients_failed'   => t('The following recipients failed: '),
    ] + $this->language;
    return TRUE;
  }

  /**
   * Public wrapper around PHPMailer::RFCDate().
   */
  public static function RFCDate() {
    $tz = date('Z');
    $tzs = ($tz < 0) ? '-' : '+';
    $tz = abs($tz);
    $tz = (int)($tz / 3600) * 100 + ($tz % 3600) / 60;
    $result = sprintf("%s %s%04d", date('D, j M Y H:i:s'), $tzs, $tz);

    return $result;
  }

  /**
   * Concatenates and wraps the e-mail body for plain-text mails.
   *
   * Mails contain HTML by default. When using PHPMailer without MimeMail
   * module, plain-text e-mails are sent, which require the same processing as
   * in the DefaultMailSystem implementation.
   *
   * @see DefaultMailSystem::format()
   *
   * @param $message
   *   A message array, as described in hook_mail_alter().
   *
   * @return
   *   The formatted $message.
   */
  public function format(array $message) {
    // Join the body array into one string.
    $message['body'] = implode("\n\n", $message['body']);
    // Convert any HTML to plain-text.
    $message['body'] = MailFormatHelper::htmlToText($message['body']);
    // Wrap the mail body for sending.
    $message['body'] = MailFormatHelper::wrapMail($message['body']);
    return $message;
  }

  /**
   * Sends an e-mail message composed by drupal_mail().
   *
   * @param $message
   *   A message array, as described in hook_mail_alter().
   * @return
   *   TRUE if the mail was successfully accepted, otherwise FALSE.
   *
   * @see PHPMailer::Send()
   */
  public function mail(array $message) {
    try {
      // Parse 'From' e-mail address.
      $from = phpmailer_parse_address($message['from']);
      $from = reset($from);
      $this->From = $from['mail'];
      if ($from['name'] != '') {
        $this->FromName = $from['name'];
      }
      unset($message['headers']['From']);

      // @todo This \still\ might not be the correct way to do this.
      $phpmailer_debug_email = \Drupal::config('system.maintenance')->get('phpmailer_debug_email');
      if (empty($phpmailer_debug_email)) {
        // Set recipients.
        foreach (phpmailer_parse_address($message['to']) as $address) {
          $this->AddAddress($address['mail'], $address['name']);
        }
        // Extract CCs and BCCs from headers.
        if (!empty($message['headers']['Cc'])) {
          foreach (phpmailer_parse_address($message['headers']['Cc']) as $address) {
            $this->AddCC($address['mail'], $address['name']);
          }
        }
        if (!empty($message['headers']['Bcc'])) {
          foreach (phpmailer_parse_address($message['headers']['Bcc']) as $address) {
            $this->AddBCC($address['mail'], $address['name']);
          }
        }
      }
      else {
        // Reroute to debug e-mail address.
        // @todo This might not be the correct way to do this.
        $this->AddAddress($phpmailer_debug_email);
      }
      unset($message['headers']['Cc'], $message['headers']['Bcc']);

      // Extract Reply-To from headers.
      if (isset($message['headers']['Reply-To'])) {
        foreach (phpmailer_parse_address($message['headers']['Reply-To']) as $address) {
          $this->AddReplyTo($address['mail'], $address['name']);
        }
        unset($message['headers']['Reply-To']);
      }
      // @todo This might not be the correct way to do this.
      elseif (\Drupal::config('phpmailer.settings')->get('smtp_always_replyto')) {
        // If no Reply-To header has been explicitly set, use the From address to
        // be able to respond to e-mails sent via Google Mail.
        $this->AddReplyTo($from['mail'], $from['name']);
      }

      // Extract Content-Type and charset.
      if (isset($message['headers']['Content-Type'])) {
        $content_type = explode(';', $message['headers']['Content-Type']);
        $this->ContentType = trim(array_shift($content_type));
        foreach ($content_type as $param) {
          $param = explode('=', $param, 2);
          $key = trim($param[0]);
          if ($key == 'charset') {
            $this->CharSet = trim($param[1]);
          }
          else {
            $this->ContentType .= '; ' . $key . '=' . trim($param[1]);
          }
        }
        unset($message['headers']['Content-Type']);
      }

      // Set additional properties.
      $properties = [
        'X-Priority'                => 'Priority',
        'Content-Transfer-Encoding' => 'Encoding',
        'Sender'                    => 'Sender',
        'Message-ID'                => 'MessageID',
        'Return-Path'               => 'ReturnPath',
      ];
      foreach ($properties as $source => $property) {
        if (isset($message['headers'][$source])) {
          $this->$property = $message['headers'][$source];
          unset($message['headers'][$source]);
        }
      }

      // This one is always set by PHPMailer.
      unset($message['headers']['MIME-Version']);

      // Add remaining header lines.
      // Note: Any header lines MUST already be checked by the caller for unwanted
      // newline characters to avoid header injection.
      // @see PHPMailer::SecureHeader()
      foreach ($message['headers'] as $key => $value) {
        $this->AddCustomHeader("$key:$value");
      }

      $this->Subject = $message['subject'];
      $this->Body = $message['body'];

      return $this->Send();
    }
    catch (phpmailerException $e) {
      // Log the error including verbose debug information.
      // Since DBLog module is the most common case, we use HTML to format the
      // message for visual inspection. For sites running with Syslog or other
      // logging modules, we put the actual values on separate lines (\n), so the
      // surrounding HTML markup doesn't get too disturbing.

      // Message is a safe t() string from DrupalPHPMailer::SetLanguage().
      $output = $e->getMessage();
      // Attempt to delimit summary from full message.
      $output .= " \n";
      $arguments = [];
      // Append SMTP communication output.
      if ($this->drupalDebugOutput) {
        // PHPMailer debug output contains HTML linebreaks. PRE is more readable.
        $this->drupalDebugOutput = str_replace('<br />', '', $this->drupalDebugOutput);
        $output .= '<p><strong>Server response:</strong></p>';
        $output .= "<pre>\n@smtp_output\n</pre>";
        $arguments += [
          '@smtp_output' => $this->drupalDebugOutput,
        ];
      }
      // We need to log the message in order to be able to debug why the server
      // responded with an error. The mail body may contain passwords and other
      // sensitive information, which should not be logged. Since all kind of
      // mails are processed and Drupal provides no way to mark sensible data,
      // it is technically impossible prevent logging in all cases.
      // Remove $params; they've already been processed and may contain sensible
      // data.
      unset($message['params']);

      // Subject.
      $output .= "<p><strong>Subject:</strong> \n@subject\n</p>";
      $arguments += [
        '@subject' => $message['subject'],
      ];
      unset($message['subject']);
      // Body.
      $output .= '<p><strong>Body:</strong></p>';
      $output .= "<pre>\n@body\n</pre>";
      $arguments += [
        '@body' => $message['body'],
      ];
      unset($message['body']);
      // Rest of $message.
      $output .= '<p><strong>Message:</strong></p>';
      $output .= "<pre>\n@message\n</pre>";
      $arguments += [
        '@message' => var_export($message, TRUE),
      ];
      \Drupal::logger('phpmailer')->error($output, $arguments);
      return FALSE;
    }
  }

}
