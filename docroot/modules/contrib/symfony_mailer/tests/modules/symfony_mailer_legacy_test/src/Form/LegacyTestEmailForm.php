<?php

namespace Drupal\symfony_mailer_legacy_test\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Mail\MailManagerInterface;
use Drupal\Core\Theme\ThemeManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Test module form to send a test legacy email.
 */
class LegacyTestEmailForm extends FormBase {

  /**
   * An email 'to' address to use in this form and all tests run.
   */
  const ADDRESS_TO = 'symfony_mailer-legacy_test-to@example.com';

  /**
   * An email 'cc' address to use in this form and all tests run.
   */
  const ADDRESS_CC = 'symfony_mailer-legacy_test-cc@example.com';

  /**
   * An email 'bcc' address to use in this form and all tests run.
   */
  const ADDRESS_BCC = 'symfony_mailer-legacy_test-bcc@example.com';

  /**
   * The mail manager service.
   *
   * @var \Drupal\Core\Mail\MailManagerInterface
   */
  protected $mailManager;

  /**
   * The theme manager service.
   *
   * @var \Drupal\Core\Theme\ThemeManagerInterface
   */
  protected $themeManager;

  /**
   * Constructs TestMailForm.
   *
   * @param \Drupal\Core\Mail\MailManagerInterface $mail_manager
   *   The mail manager service.
   * @param \Drupal\Core\Theme\ThemeManagerInterface $theme_manager
   *   The theme manager service.
   */
  public function __construct(MailManagerInterface $mail_manager, ThemeManagerInterface $theme_manager) {
    $this->mailManager = $mail_manager;
    $this->themeManager = $theme_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('plugin.manager.mail'),
      $container->get('theme.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'symfony_mailer_legacy_test_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = [];
    $form['submit'] = [
      '#type' => 'submit',
      '#value' => 'Send test email',
    ];
    $current_theme = $this->themeManager->getActiveTheme()->getName();
    $form['current_theme'] = [
      '#markup' => 'Current theme: ' . $current_theme,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->mailManager->mail('symfony_mailer_legacy_test', 'legacy_test', self::ADDRESS_TO, 'en');
  }

}
