<?php

namespace Drupal\Tests\tmgmt_demo\Functional;

use Drupal\filter\Entity\FilterFormat;
use Drupal\Tests\tmgmt\Functional\TMGMTTestBase;

/**
 * Tests the demo module for TMGMT.
 *
 * @group TMGMT
 */
class TMGMTDemoTest extends TMGMTTestBase {

  /**
   * Modules to enable.
   *
   * @var string[]
   */
  protected static $modules = array('tmgmt_demo', 'ckeditor5');

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();
    $basic_html_format = FilterFormat::load('basic_html');
    $restricted_html_format = FilterFormat::create(array(
      'format' => 'restricted_html',
      'name' => 'Restricted HTML',
    ));
    $restricted_html_format->save();
    $full_html_format = FilterFormat::create(array(
      'format' => 'full_html',
      'name' => 'Full HTML',
    ));
    $full_html_format->save();
    $this->loginAsAdmin([
      'access content overview',
      'administer tmgmt',
      'translate any entity',
      'edit any translatable_node content',
      $basic_html_format->getPermissionName(),
      $restricted_html_format->getPermissionName(),
      $full_html_format->getPermissionName(),
    ]);
  }

  /**
   * Asserts translation jobs can be created.
   */
  public function testInstalled() {
    // Try and translate node 1.
    $this->drupalGet('node');
    $this->assertSession()->pageTextContains('First node');
    $this->assertSession()->pageTextContains('Second node');
    $this->assertSession()->pageTextContains('TMGMT Demo');
    $this->clickLink(t('First node'));
    $this->clickLink(t('Translate'));
    $edit = [
      'languages[de]' => TRUE,
      'languages[fr]' => TRUE,
    ];
    $this->submitForm($edit, t('Request translation'));
    $this->assertSession()->pageTextContains(t('2 jobs need to be checked out.'));
    // Try and translate node 2.
    $this->drupalGet('admin/content');
    $this->clickLink(t('Second node'));
    $this->clickLink(t('Translate'));
    $this->submitForm($edit, t('Request translation'));
    $this->assertSession()->pageTextContains(t('2 jobs need to be checked out.'));

    // Test local translator.
    $edit = [
      'translator' => 'local',
    ];
    $this->submitForm($edit, 'Submit to provider and continue');
    $this->assertSession()->pageTextContains('The translation job has been submitted.');

    // Check to see if no items are selected and the error message pops up.
    $this->drupalGet('admin/tmgmt/sources');
    $this->submitForm([], t('Request translation'));
    $this->assertSession()->pageTextContainsOnce(t("You didn't select any source items."));
    $this->submitForm([], t('Search'));
    $this->assertSession()->pageTextNotContains(t("You didn't select any source items."));
    $this->submitForm([], t('Cancel'));
    $this->assertSession()->pageTextNotContains(t("You didn't select any source items."));
    $this->submitForm([], t('Add to cart'));
    $this->assertSession()->pageTextContainsOnce(t("You didn't select any source items."));

    // Test if the formats are set properly.
    $this->drupalGet('node/1/edit');
    $this->assertTrue($this->assertSession()->optionExists('edit-body-0-format--2', 'basic_html')->isSelected());
    $this->drupalGet('node/2/edit');
    $this->assertTrue($this->assertSession()->optionExists('edit-body-0-format--2', 'restricted_html')->isSelected());
    $this->drupalGet('node/3/edit');
    $this->assertTrue($this->assertSession()->optionExists('edit-body-0-format--2', 'full_html')->isSelected());
  }

}
