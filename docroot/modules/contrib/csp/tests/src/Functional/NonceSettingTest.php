<?php

namespace Drupal\Tests\csp\Functional;

use Drupal\FunctionalJavascriptTests\WebDriverTestBase;

/**
 * Tests the nonce functionality.
 *
 * @group csp
 */
class NonceSettingTest extends WebDriverTestBase {

  /**
   * Set default theme to stark.
   *
   * @var string
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['csp', 'csp_nonce_page_test'];

  /**
   * Check that a nonce value is added to drupalSettings.
   */
  public function testSettingsNonce() {
    // Skip on Drupal 9 which includes core/drupalSettings by default.
    if (version_compare(\Drupal::VERSION, '10.0', '>=')) {
      $this->drupalGet('csp-test-page-no-nonce');
      $jsSettings = $this->getDrupalSettings();
      $this->assertArrayNotHasKey('csp', $jsSettings);
    }

    $this->drupalGet('csp-test-page-nonce');
    $jsSettings = $this->getDrupalSettings();
    $this->assertArrayHasKey('csp', $jsSettings);
    $this->assertArrayHasKey('nonce', $jsSettings['csp']);
  }

}
