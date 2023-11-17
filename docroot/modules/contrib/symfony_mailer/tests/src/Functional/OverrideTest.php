<?php

namespace Drupal\Tests\symfony_mailer\Functional;

use Drupal\Component\Utility\Html;
use Drupal\Tests\Traits\Core\CronRunTrait;

/**
 * Test Mailer overrides.
 *
 * @group symfony_mailer
 */
class OverrideTest extends SymfonyMailerTestBase {

  use CronRunTrait;

  /**
   * URL for override info page.
   */
  const OVERRIDE_INFO = 'admin/config/system/mailer/override';

  /**
   * URL for override import all page.
   */
  const IMPORT_ALL = '/admin/config/system/mailer/override/_/import';

  /**
   * URL for override import page for user module.
   */
  const IMPORT_USER = '/admin/config/system/mailer/override/user/import';

  /**
   * Test mailer override form.
   */
  public function testForm() {
    \Drupal::service('module_installer')->install(['contact', 'user']);
    $session = $this->assertSession();
    $this->drupalLogin($this->adminUser);

    // Check the override info page with defaults.
    $expected = [
      ['Contact form', 'Disabled', 'Contact form recipients', 'Enable & import'],
      ['Personal contact form', 'Disabled', '', 'Enable'],
      ['User', 'Disabled', 'Update notification addresses', "User email settings\nWarning: This overrides the default HTML messages with imported plain text versions"],
      ['*All*', '', '', 'Enable & import'],
    ];
    $this->drupalGet(self::OVERRIDE_INFO);
    $this->checkOverrideInfo($expected);
    $session->linkByHrefExists(self::IMPORT_ALL);

    // Import all.
    $this->drupalGet(self::IMPORT_ALL);
    $session->pageTextContains('Import unavailable for Personal contact form');
    $session->pageTextContains('Import skipped for User: This overrides the default HTML messages with imported plain text versions');
    $session->pageTextContains('Run enable for override Personal contact form');
    $session->pageTextContains('Run import for override Contact form');
    $session->pageTextContains('Run enable for override User');
    $session->pageTextContains('Importing overwrites existing policy.');
    $this->submitForm([], 'Enable & import');

    // Check the override info page again.
    $expected[0][1] = 'Enabled & Imported';
    $expected[0][3] = 'Re-import';
    $expected[1][1] = $expected[2][1] = 'Enabled';
    $expected[1][3] = $expected[2][3] = 'Reset';
    $session->pageTextContains('Completed Enable & import for all overrides');
    $this->checkOverrideInfo($expected);

    // Import all again - nothing to do.
    $this->drupalGet(self::IMPORT_ALL);
    $session->pageTextContains('No available actions');
    $button = $this->getSession()->getPage()->findButton('Enable & import');
    $this->assertTrue($button->hasAttribute('disabled'));
    $this->clickLink('Cancel');

    // Force import the user override.
    $session->linkByHrefExists(self::IMPORT_USER);
    $this->drupalGet(self::IMPORT_USER);
    $session->pageTextContains('This overrides the default HTML messages with imported plain text versions');
    $this->submitForm([], 'Import');

    // Check the override info page again.
    $expected[2][1] = 'Enabled & Imported';
    $expected[2][3] = 'Re-import';
    $session->pageTextContains('Completed import for override User');
    $this->checkOverrideInfo($expected);
  }

  /**
   * Test override of update module.
   */
  public function testUpdate() {
    $this->container->get('module_installer')->install(['update', 'update_test']);
    $this->resetAll();

    // Enable and import, then clear the module setting to ensure we don't rely
    // on it.
    $this->drupalLogin($this->adminUser);
    $this->config('update.settings')->set('notification.emails', [$this->siteEmail])->save();
    $this->drupalGet('/admin/config/system/mailer/override/update/import');
    $this->submitForm([], 'Enable & import');
    $this->config('update.settings')->set('notification.emails', [])->save();

    // Configure update test with an available update.
    $system_info = [
      '#all' => [
        'version' => '8.0.0',
      ],
      'symfony_mailer' => [
        'project' => 'symfony_mailer',
        'version' => '8.x-1.0',
        'hidden' => FALSE,
      ],
    ];
    $xml_map = [
      'drupal' => '0.0',
      'symfony_mailer' => '1_0',
    ];

    $this->config('update_test.settings')
      ->set('system_info', $system_info)
      ->set('xml_map', $xml_map)
      ->save();

    // Trigger the email and check.
    $this->cronRun();
    $this->readMail();
    $this->assertTo($this->siteEmail, $this->siteName);
    $this->assertSubject("New release(s) available for $this->siteName");
    $escaped_site_name = Html::escape($this->siteName);
    $this->assertBodyContains("You need to take action to secure your server $escaped_site_name");
  }

  /**
   * Checks the override info page.
   *
   * @param array $expected
   *   Array of expected table cell contents.
   */
  protected function checkOverrideInfo(array $expected) {
    $this->assertSession()->addressEquals(self::OVERRIDE_INFO);
    foreach ($this->xpath('//tbody/tr') as $row) {
      $expected_row = array_pop($expected);
      foreach ($row->find('xpath', '/td') as $cell) {
        $expected_cell = array_pop($expected_row);
        $this->assertEquals($expected_cell, $cell->getText());
      }
    }
  }

}
