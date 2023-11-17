<?php

namespace Drupal\Tests\symfony_mailer\Functional;

use Drupal\Component\Utility\Html;

/**
 * Test the test email.
 *
 * @group symfony_mailer
 */
class TestEmailTest extends SymfonyMailerTestBase {

  /**
   * Test sending a test email.
   */
  public function testTest() {
    $this->drupalLogin($this->adminUser);
    $this->drupalGet('admin/config/system/mailer/test');

    $this->assertPolicyListingIntro('Drupal Symfony Mailer', 'Subject, Body');
    $this->assertPolicyListingRow(1, self::TYPE_ALL, '', 'symfony_mailer');
    $this->assertPolicyListingRow(2, 'Test email', 'Body<br>Subject: Test email from [site:name]', 'symfony_mailer.test');

    $this->submitForm([], 'Send');
    $this->assertSession()->pageTextContains('An attempt has been made to send an email to you.');
    $this->readMail();
    $this->assertTo($this->adminUser->getEmail(), $this->adminUser->getDisplayName());
    $this->assertSubject("Test email from $this->siteName");
    $escaped_site_name = Html::escape($this->siteName);
    $this->assertBodyContains("This is a test email from <a href=\"$this->baseUrl/\">$escaped_site_name</a>.");

    // Check that inline styles are preserved in the email.
    // The padding is added in email-wrap.html.twig.
    $this->assertBodyContains('style="padding: 0px 0px 0px 0px;"');
    // This style comes from test.email.css.
    $this->assertBodyContains('style="padding-top: 3px; padding-bottom: 3px; text-align: center; color: white; background-color: #0678be;"');
  }

}
