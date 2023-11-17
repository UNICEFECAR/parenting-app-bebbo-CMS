<?php

namespace Drupal\Tests\symfony_mailer\Functional;

use Drupal\symfony_mailer_legacy_test\Form\LegacyTestEmailForm;

/**
 * Tests Symfony Mailer compatibility mode.
 *
 * @group symfony_mailer
 */
class LegacyEmailTest extends SymfonyMailerTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = [
    'symfony_mailer_legacy_test',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->adminUser = $this->drupalCreateUser([
      'administer mailer',
      'administer themes',
      'view the administration theme',
    ]);
  }

  /**
   * Test sending a legacy email rendered via hook_mail().
   */
  public function testSendLegacyEmail() {
    $this->drupalLogin($this->adminUser);
    // Trigger sending a legacy email via hook_mail().
    $this->drupalGet('admin/symfony_mailer_legacy_test/send');
    $this->submitForm([], 'Send test email');
    $this->readMail();

    // Check email recipients.
    $this->assertTo(LegacyTestEmailForm::ADDRESS_TO);
    $this->assertCc(LegacyTestEmailForm::ADDRESS_CC);
    $this->assertBcc(LegacyTestEmailForm::ADDRESS_BCC);

    // Check email contents.
    $this->assertSubject("Legacy email sent via hook_mail().");
    $this->assertBodyContains("This email is sent via hook_mail().");
    $this->assertBodyContains("This email was altered via hook_mail_alter().");
    $this->assertBodyContains("This is the default legacy test email template.");
    $this->assertBodyContains("Rendered in theme: stark");
  }

  /**
   * Test sending a legacy email with custom email body template.
   */
  public function testSendLegacyEmailWithTheme() {
    $this->drupalLogin($this->adminUser);
    // Switch the current default theme and admin theme, and test if template
    // renders in the default theme instead of the admin theme, even though the
    // admin theme is active when the mail gets triggered.
    $themes = ['test_legacy_email_theme', 'stark'];
    \Drupal::service('theme_installer')->install($themes);
    $this->config('system.theme')
      ->set('default', 'test_legacy_email_theme')
      ->set('admin', 'stark')
      ->save();

    /** @var \Drupal\Core\Extension\ThemeHandlerInterface $theme_handler */
    $theme_handler = $this->container->get('theme_handler');
    $this->assertTrue($theme_handler->themeExists('test_legacy_email_theme'));

    // Trigger sending a legacy email via hook_mail().
    $this->drupalGet('admin/symfony_mailer_legacy_test/send');
    $this->assertSession()->pageTextContains('Current theme: stark');
    $this->submitForm([], 'Send test email');
    $this->readMail();

    // Check email recipients.
    $this->assertTo(LegacyTestEmailForm::ADDRESS_TO);
    $this->assertCc(LegacyTestEmailForm::ADDRESS_CC);
    $this->assertBcc(LegacyTestEmailForm::ADDRESS_BCC);

    // Check email contents.
    $this->assertSubject("Legacy email sent via hook_mail().");
    $this->assertBodyContains("This email is sent via hook_mail().");
    $this->assertBodyContains("This email was altered via hook_mail_alter().");
    $this->assertBodyContains("This is the overridden legacy test email template.");
    $this->assertBodyContains("Rendered in theme: test_legacy_email_theme");
  }

}
