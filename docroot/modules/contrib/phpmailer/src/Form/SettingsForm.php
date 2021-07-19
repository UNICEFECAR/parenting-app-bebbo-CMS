<?php

namespace Drupal\phpmailer\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Mail\MailManagerInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines a form that configures devel settings.
 */
class SettingsForm extends ConfigFormBase {

  /**
   * The mail manager.
   *
   * @var \Drupal\Core\Mail\MailManagerInterface
   */
  protected $mailManager;

  /**
   * The language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * Constructor.
   *
   * @param \Drupal\Core\Mail\MailManagerInterface $mail_manager
   *   The mail manager.
   * @param \Drupal\Core\Language\LanguageManagerInterface $language_manager
   *   The language manager.
   */
  public function __construct(MailManagerInterface $mail_manager, LanguageManagerInterface $language_manager) {
    $this->mailManager = $mail_manager;
    $this->languageManager = $language_manager;

  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('plugin.manager.mail'),
      $container->get('language_manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'phpmailer_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['phpmailer.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('phpmailer.settings');

    $form['smtp_on'] = [
      '#type' => 'checkbox',
      '#title' => t('Activate PHPMailer to send e-mails'),
      '#default_value' => $config->get('smtp_on'),
      '#description' => t('When enabled, PHPMailer will be used to deliver all site e-mails.'),
    ];
    /**
     * @todo This part needs to be figured out.
     */
    // Only allow to send all e-mails if Mime Mail is not configured the same
    // (mimemail_alter is the counterpart to smtp_on).
//    if (\Drupal::moduleHandler()->moduleExists('mimemail') && variable_get('mimemail_alter', 0)) {
//      $form['smtp_on']['#disabled'] = TRUE;
//      $form['smtp_on']['#default_value'] = 0;
//      $form['smtp_on']['#description'] = t('MimeMail has been detected. To enable PHPMailer for mail transport, go to the <a href="@url">MimeMail settings page</a> and select PHPMailer from the available e-mail engines.', ['@url' => url('admin/config/system/mimemail')]);
//    }
//    elseif (!$config->get('smtp_on') && empty($form_state['input'])) {
    if (!$config->get('smtp_on') && empty($form_state->input)) {
      drupal_set_message(t('PHPMailer is currently disabled.'), 'warning');
    }

    $form['server']['smtp_host'] = [
      '#type' => 'textfield',
      '#title' => t('Primary SMTP server'),
      '#default_value' => $config->get('smtp_host'),
      '#description' => t('The host name or IP address of your primary SMTP server.  Use %gmail-smtp for Google Mail.', ['%gmail-smtp' => 'smtp.gmail.com']),
      '#required' => TRUE,
    ];
    $form['server']['smtp_hostbackup'] = [
      '#type' => 'textfield',
      '#title' => t('Backup SMTP server'),
      '#default_value' => $config->get('smtp_hostbackup'),
      '#description' => t('Optional host name or IP address of a backup server, if the primary server fails.  You may override the default port below by appending it to the host name separated by a colon.  Example: %hostname', ['%hostname' => 'localhost:465']),
    ];
    $form['server']['smtp_port'] = [
      '#type' => 'textfield',
      '#title' => t('SMTP port'),
      '#size' => 5,
      '#maxlength' => 5,
      '#default_value' => $config->get('smtp_port'),
      '#description' => t('The standard SMTP port is 25. Secure connections (including Google Mail), typically use 465.'),
      '#required' => TRUE,
    ];
    $form['server']['smtp_protocol'] = [
      '#type' => 'select',
      '#title' => t('Use secure protocol'),
      '#default_value' => $config->get('smtp_protocol'),
      '#options' => ['' => t('No'), 'ssl' => t('SSL'), 'tls' => t('TLS')],
      '#description' => t('Whether to use an encrypted connection to communicate with the SMTP server.  Google Mail requires SSL.'),
    ];
    if (!function_exists('openssl_open')) {
      $form['server']['smtp_protocol']['#default_value'] = '';
      $form['server']['smtp_protocol']['#disabled'] = TRUE;
      $form['server']['smtp_protocol']['#description'] .= ' ' . t('Note: This option has been disabled since your PHP installation does not seem to have support for OpenSSL.');
      $config->set('smtp_protocol', '')->save();
    }

    $form['auth'] = [
      '#type' => 'details',
      '#title' => t('SMTP authentication'),
      '#description' => t('Leave both fields empty, if your SMTP server does not require authentication.'),
      '#collapsible' => TRUE,
      '#collapsed' => TRUE,
    ];
    $form['auth']['smtp_username'] = [
      '#type' => 'textfield',
      '#title' => t('Username'),
      '#default_value' => $config->get('smtp_username'),
      '#description' => t('For Google Mail, enter your username, including "@gmail.com".'),
    ];
    if (!$config->get('smtp_hide_password')) {
      $form['auth']['smtp_password'] = [
        '#type' => 'textfield',
        '#title' => t('Password'),
        '#default_value' => $config->get('smtp_password'),
      ];
      $form['auth']['smtp_hide_password'] = [
        '#type' => 'checkbox',
        '#title' => t('Hide password'),
        '#default_value' => 0,
        '#description' => t("Check this option to permanently hide the plaintext password from peeking eyes. You may still change the password afterwards, but it won't be displayed anymore."),
      ];
    }
    else {
      $have_password = ($config->get('smtp_password') != '');
      $form['auth']['smtp_password'] = [
        '#type' => 'password',
        '#title' => $have_password ? t('Change password') : t('Password'),
        '#description' => $have_password ? t('Leave empty, if you do not intend to change the current password.') : '',
      ];
    }

    $form['advanced'] = [
      '#type' => 'details',
      '#title' => t('Advanced SMTP settings'),
    ];
    $form['advanced']['smtp_fromname'] = [
      '#type' => 'textfield',
      '#title' => t('"From" name'),
      '#default_value' => $config->get('smtp_fromname'),
      '#description' => t('Enter a name that should appear as the sender for all messages.  If left blank the site name will be used instead: %sitename.', ['%sitename' => $config->get('site_name')]),
    ];
    $form['advanced']['smtp_always_replyto'] = [
      '#type' => 'checkbox',
      '#title' => t('Always set "Reply-To" address'),
      '#default_value' => $config->get('smtp_always_replyto'),
      '#description' => t('Enables setting the "Reply-To" address to the original sender of the message, if unset.  This is required when using Google Mail, which would otherwise overwrite the original sender.'),
    ];
    $form['advanced']['smtp_keepalive'] = [
      '#type' => 'checkbox',
      '#title' => t('Keep connection alive'),
      '#default_value' => $config->get('smtp_keepalive'),
      '#description' => t('Whether to reuse an existing connection during a request.  Improves performance when sending a lot of e-mails at once.'),
    ];
    $form['advanced']['smtp_debug'] = [
      '#type' => 'select',
      '#title' => t('Debug level'),
      '#default_value' => $config->get('smtp_debug'),
      '#options' => [0 => t('Disabled'), 1 => t('Errors only'), 2 => t('Server responses'), 4 => t('Full communication')],
      '#description' => t("Debug the communication with the SMTP server.  You normally shouldn't enable this unless you're trying to debug e-mail sending problems."),
    ];
    $form['advanced']['smtp_debug_log'] = [
      '#type' => 'checkbox',
      '#title' => t("Record debugging output in Drupal's log"),
      '#default_value' => $config->get('smtp_debug_log'),
      '#description' => t("If this is checked, the debugging out put that is produced will also be recorded in Drupal's database log."),
      '#states' => [
        'visible' => [
          ':input[name=smtp_debug]' => ['!value' => 0],
        ],
      ],
    ];

    $form['advanced']['ssl_settings'] = [
      '#type' => 'fieldset',
      '#title' => t('Advanced SSL settings'),
      '#description' => t('If you are attempting to connect to a broken or malconfigured secure mail server, you can try toggling one or more of these options. <strong>If changing any of these options allows connection to the server, you should consider either fixing the SSL certifcate, or using a different SMTP server, as the connection may be insecure.</strong>'),
    ];
    $ssl_verify_peer = $config->get('smtp_ssl_verify_peer');
    $form['advanced']['ssl_settings']['smtp_ssl_verify_peer'] = [
      '#type' => 'checkbox',
      '#title' => t('Verfy peer'),
      '#default_value' => isset($ssl_verify_peer) ? $ssl_verify_peer : 1,
      '#description' => t('If this is checked, it will require verification of the SSL certificate being used on the mail server.'),
    ];
    $ssl_verify_peer_name = $config->get('smtp_ssl_verify_peer_name');
    $form['advanced']['ssl_settings']['smtp_ssl_verify_peer_name'] = [
      '#type' => 'checkbox',
      '#title' => t('Verfy peer name'),
      '#default_value' => isset($ssl_verify_peer_name) ? $ssl_verify_peer_name : 1,
      '#description' => t("If this is checked, it will require verification of the mail server's name in the SSL certificate."),
    ];
    $ssl_allow_self_signed = $config->get('smtp_ssl_allow_self_signed');
    $form['advanced']['ssl_settings']['smtp_ssl_allow_self_signed'] = [
      '#type' => 'checkbox',
      '#title' => t('Allow self signed'),
      '#default_value' => isset($ssl_allow_self_signed) ? $ssl_allow_self_signed : 0,
      '#description' => t('If this is checked, it will allow conecting to a mail server with a self-signed SSL certificate. (This requires "Verfy peer" to be enabled.)'),
      '#states' => [
        'enabled' => [
          ':input[name="ssl_verify_peer"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['test'] = [
      '#type' => 'details',
      '#title' => t('Test configuration'),
    ];
    $form['test']['phpmailer_test'] = [
      '#type' => 'textfield',
      '#title' => t('Recipient'),
      '#default_value' => '',
      '#description' => t('Type in an address to have a test e-mail sent there.'),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    if ($form_state->getValue('smtp_on') && intval($form_state->getValue('smtp_port') == 0)) {
      $form_state->setErrorByName('smtp_port', $this->t('You must enter a valid SMTP port number.'));
    }
    parent::validateForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValues();

    // Check to see if the module has been activated or inactivated.
    if ($values['smtp_on']) {
      if (!phpmailer_active()) {
        // This module is inactive and is being activated.
        /**
         * @todo This part needs to be figured out.
         */
//        $mailsystem_config = $this->config('mailsystem.settings');
        $mail_config = $this->configFactory->getEditable('system.mail');
        $mail_system = $mail_config->get('interface.default');
        if ($mail_system != 'phpmailer') {
          $mail_system = 'phpmailer';
          $mail_config->set('interface.default', $mail_system)->save();
        }

        drupal_set_message(t('PHPMailer will be used to deliver all site e-mails.'));
        \Drupal::logger('phpmailer')->notice('PHPMailer has been enabled.');
      }
    }
    elseif (phpmailer_active()) {
      // This module is active and is being inactivated.
      /**
       * @todo This part needs to be figured out.
       */
//      $mailsystem_config = $this->config('mailsystem.settings');
      $mail_config = $this->configFactory->getEditable('system.mail');
      $mail_system = $mail_config->get('interface.default');
      if ($mail_system == 'phpmailer') {
        $mail_system = 'php_mail';
        $mail_config->set('interface.default', $mail_system)->save();
      }

      drupal_set_message(t('PHPMailer has been disabled.'));
      \Drupal::logger('phpmailer')->notice('PHPMailer has been disabled.');
    }

    // Save the configuration changes.
    $phpmailer_config = $this->config('phpmailer.settings');
    $phpmailer_config->set('smtp_on', $values['smtp_on'])
      ->set('smtp_host', $values['smtp_host'])
      ->set('smtp_hostbackup', $values['smtp_hostbackup'])
      ->set('smtp_port', $values['smtp_port'])
      ->set('smtp_protocol', $values['smtp_protocol'])
      ->set('smtp_ssl_verify_peer', $values['smtp_ssl_verify_peer'])
      ->set('smtp_ssl_verify_peer_name', $values['smtp_ssl_verify_peer_name'])
      ->set('smtp_ssl_allow_self_signed', $values['smtp_ssl_allow_self_signed'])
      ->set('smtp_username', $values['smtp_username'])
      ->set('smtp_fromname', $values['smtp_fromname'])
      ->set('smtp_always_replyto', $values['smtp_always_replyto'])
      ->set('smtp_keepalive', $values['smtp_keepalive'])
      ->set('smtp_debug', $values['smtp_debug'])
      ->set('smtp_debug_log', $values['smtp_debug_log']);

    // Only save the password, if it is not empty.
    if (!empty($values['smtp_password'])) {
      $phpmailer_config->set('smtp_password', $values['smtp_password']);
    }

    $phpmailer_config->save();

    /**
     * @todo This part needs to be figured out.
     */
    // Send a test email message, if an email address was entered.
    if ($values['phpmailer_test']) {
      // Since this is being sen to an email address that may not necessarily be
      // tied to a user account, use the site's default language.
      $langcode = $this->languageManager->getDefaultLanguage()->getId();
      // If PHPMailer is enabled, send via regular drupal_mail().
      if (phpmailer_active()) {
//        $this->mailManager->mail('phpmailer', 'test', $values['phpmailer_test'], $language_code, $params, $from, $send_now);
        \Drupal::service('plugin.manager.mail')->mail('phpmailer', 'test', $values['phpmailer_test'], $langcode);
      }
      // Otherwise, prepare and send the test mail manually.
      else {
        // Prepare the message without sending.
        $message = \Drupal::service('plugin.manager.mail')->mail('phpmailer', 'test', $values['phpmailer_test'], $langcode, [], NULL, FALSE);
        // Send the message.
        module_load_include('inc', 'phpmailer', 'includes/phpmailer.drupal');
        $ret_val = phpmailer_send($message);
      }
      $watchdog_url = Url::fromRoute('dblog.overview');
      $watchdog_url = \Drupal::l(t('Check the logs'), $watchdog_url);
      drupal_set_message(t('A test e-mail has been sent to %email. @watchdog-url for any error messages.', [
        '%email' => $values['phpmailer_test'],
        '@watchdog-url' => $watchdog_url,
      ]));
    }

    parent::submitForm($form, $form_state);
  }

}
