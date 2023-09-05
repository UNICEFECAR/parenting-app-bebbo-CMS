<?php

namespace Drupal\Tests\tmgmt\Functional;

/**
 * Tests the translator add, edit and overview user interfaces.
 *
 * @group tmgmt
 */
class TranslatorUITest extends TMGMTTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = array('tmgmt_file');

  /**
   * {@inheritdoc}
   */
  function setUp(): void {
    parent::setUp();

    // Login as administrator to add/edit and view translators.
    $this->loginAsAdmin();
  }

  /**
   * Tests UI for creating a translator.
   */
  public function testTranslatorUI() {

    // Test translator creation UI.
    $this->drupalGet('admin/tmgmt/translators/add');
    $this->submitForm([
      'label' => 'Test translator',
      'description' => 'Test translator description',
      'name' => 'translator_test',
      'settings[scheme]' => 'private',
    ], t('Save'));
    $this->assertSession()->pageTextContains('Test translator configuration has been created.');
    // Test translator edit page.
    $this->drupalGet('admin/tmgmt/translators/manage/translator_test');
    $this->assertSession()->fieldValueEquals('label', 'Test translator');
    $this->assertSession()->fieldValueEquals('description', 'Test translator description');
    $this->assertSession()->fieldValueEquals('name', 'translator_test');
    $this->assertSession()->checkboxChecked('edit-settings-scheme-private');
    $this->submitForm([
      'label' => 'Test translator changed',
      'description' => 'Test translator description changed',
    ], t('Save'));
    $this->assertSession()->pageTextContains('Test translator changed configuration has been updated.');

    // Test translator overview page.
    $this->drupalGet('admin/tmgmt/translators');
    $this->assertSession()->responseContains('<img class="tmgmt-logo-overview"');
    $this->assertSession()->pageTextContains('Test translator changed');
    $this->assertSession()->linkExists(t('Edit'));
    $this->assertSession()->linkExists(t('Delete'));

    // Check if the edit link is displayed before the clone link.
    $content = $this->assertSession()->elementExists('css', '.dropbutton-wrapper')->getHtml();
    $edit_position = strpos($content, 'Edit');
    $clone_position = strpos($content, 'Clone');
    $this->assertTrue($edit_position < $clone_position);
  }

}
