<?php

namespace Drupal\Tests\tmgmt_config\Functional;

use Drupal\Core\Url;
use Drupal\Tests\tmgmt\Functional\TmgmtEntityTestTrait;
use Drupal\Tests\tmgmt\Functional\TMGMTTestBase;
use Drupal\tmgmt\Entity\JobItem;

/**
 * Tests the user interface for entity translation lists.
 *
 * @group tmgmt
 */
class ConfigSourceListTest extends TMGMTTestBase {
  use TmgmtEntityTestTrait;

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = array('tmgmt_config', 'tmgmt_content', 'config_translation', 'views', 'views_ui', 'field_ui');

  protected $nodes = array();

  function setUp(): void {
    parent::setUp();
    $this->loginAsAdmin();

    $this->loginAsTranslator(array('translate configuration'));

    $this->addLanguage('de');
    $this->addLanguage('it');

    $this->drupalCreateContentType(array(
      'type' => 'article',
      'name' => 'Article',
    ));

    $this->drupalCreateContentType(array(
      'type' => 'page',
      'name' => 'Page',
    ));

    $this->drupalCreateContentType(array(
      'type' => 'simplenews_issue',
      'name' => 'Newsletter issue',
    ));
  }

  function testNodeTypeSubmissions() {

    // Simple submission.
    $this->drupalGet('admin/tmgmt/sources/config/node_type');
    $edit = array(
      'items[node.type.article]' => TRUE,
    );
    $this->submitForm($edit, t('Request translation'));

    // Verify that we are on the translate tab.
    $this->assertSession()->pageTextContains(t('One job needs to be checked out.'));
    $this->assertSession()->pageTextContains(t('Article content type (English to ?, Unprocessed)'));

    // Submit.
    $this->submitForm([], t('Submit to provider'));

    // Make sure that we're back on the originally defined destination URL.
    $this->assertSession()->addressEquals('admin/tmgmt/sources/config/node_type');

    $this->assertSession()->pageTextContains(t('Test translation created.'));
    $this->assertSession()->pageTextContains(t('The translation of Article content type to German is finished and can now be reviewed.'));

    // Submission of two different entity types.
    $this->drupalGet('admin/tmgmt/sources/config/node_type');
    $edit = array(
      'items[node.type.article]' => TRUE,
      'items[node.type.page]' => TRUE,
    );
    $this->submitForm($edit, t('Request translation'));

    // Verify that we are on the translate tab.
    // This is still one job, unlike when selecting more languages.
    $this->assertSession()->pageTextContains(t('One job needs to be checked out.'));
    $this->assertSession()->pageTextContains(t('Article content type and 1 more (English to ?, Unprocessed)'));
    $this->assertSession()->pageTextContains('1 item conflicts with pending item and will be dropped on submission. Conflicting item: Article content type.');

    // Submit.
    $this->submitForm([], t('Submit to provider'));

    // Make sure that we're back on the originally defined destination URL.
    $this->assertSession()->addressEquals('admin/tmgmt/sources/config/node_type');

    $this->assertSession()->pageTextContains(t('Test translation created.'));
    $this->assertSession()->pageTextNotContains(t('The translation of Article content type to German is finished and can now be reviewed.'));
    $this->assertSession()->pageTextContains(t('The translation of Page content type to German is finished and can now be reviewed.'));
  }

  function testViewTranslation() {

    // Check if we have appropriate message in case there are no entity
    // translatable content types.
    $this->drupalGet('admin/tmgmt/sources/config/view');
    $this->assertSession()->pageTextContains(t('View overview (Config Entity)'));

    // Request a translation for archive.
    $edit = array(
      'items[views.view.archive]' => TRUE,
    );
    $this->submitForm($edit, t('Request translation'));

    // Verify that we are on the translate tab.
    $this->assertSession()->pageTextContains(t('One job needs to be checked out.'));
    $this->assertSession()->pageTextContains(t('Archive view (English to ?, Unprocessed)'));

    // Submit.
    $this->submitForm([], t('Submit to provider'));

    // Make sure that we're back on the originally defined destination URL.
    $this->assertSession()->addressEquals('admin/tmgmt/sources/config/view');

    $this->assertSession()->pageTextContains(t('Test translation created.'));
    $this->assertSession()->pageTextContains(t('The translation of Archive view to German is finished and can now be reviewed.'));

    // Request a translation for more archive, recent comments, content and job
    // overview.
    $edit = array(
      'items[views.view.archive]' => TRUE,
      'items[views.view.content_recent]' => TRUE,
      'items[views.view.content]' => TRUE,
      'items[views.view.tmgmt_job_overview]' => TRUE,
    );
    $this->submitForm($edit, t('Request translation'));

    // Verify that we are on the translate tab.
    $this->assertSession()->pageTextContains(t('One job needs to be checked out.'));
    $this->assertSession()->pageTextContains(t('Archive view and 3 more (English to ?, Unprocessed)'));
    $this->assertSession()->pageTextContains('1 item conflicts with pending item and will be dropped on submission. Conflicting item: Archive view.');

    // Submit.
    $this->submitForm([], t('Submit to provider'));

    // Make sure that we're back on the originally defined destination URL.
    $this->assertSession()->addressEquals('admin/tmgmt/sources/config/view');

    $this->assertSession()->pageTextContains(t('Test translation created.'));
    $this->assertSession()->pageTextNotContains(t('The translation of Archive view to German is finished and can now be reviewed.'));
    $this->assertSession()->pageTextContains(t('The translation of Recent content view to German is finished and can now be reviewed.'));
    $this->assertSession()->pageTextContains(t('The translation of Content view to German is finished and can now be reviewed.'));
    $this->assertSession()->pageTextContains(t('The translation of Job overview view to German is finished and can now be reviewed.'));

    // Make sure that the Cart page works.
    $edit = array(
      'items[views.view.tmgmt_job_items]' => TRUE,
    );
    $this->submitForm($edit, t('Add to cart'));
    $this->clickLink('cart');

    // Verify that we are on the Cart page.
    $cart_tab_active = $this->xpath('//ul[@class="tabs primary"]/li[@class="is-active"]/a')[0];
    $this->assertEquals('Cart(active tab)', $cart_tab_active->getText());
    $this->assertSession()->titleEquals('Cart | Drupal');
    $this->assertSession()->pageTextContains('Request translation');
  }

  function testNodeTypeFilter() {

    $this->drupalGet('admin/tmgmt/sources/config/node_type');
    $this->assertSession()->pageTextContains(t('Content type overview (Config Entity)'));

    // Simple filtering.
    $this->drupalGet('admin/tmgmt/sources/config/node_type');
    $filters = array(
      'search[name]' => '',
      'search[langcode]' => '',
      'search[target_language]' => '',
    );
    $this->submitForm($filters, t('Search'));

    // Random text in the name label.
    $this->drupalGet('admin/tmgmt/sources/config/node_type');
    $filters = array(
      'search[name]' => $this->randomMachineName(5),
      'search[langcode]' => '',
      'search[target_language]' => '',
    );
    $this->submitForm($filters, t('Search'));
    $this->assertSession()->pageTextContains(t('No source items matching given criteria have been found.'));

    // Searching for article.
    $this->drupalGet('admin/tmgmt/sources/config/node_type');
    $filters = array(
      'search[name]' => 'article',
      'search[langcode]' => '',
      'search[target_language]' => '',
    );
    $this->submitForm($filters, t('Search'));
    $rows = $this->xpath('//tbody/tr/td[2]/a');
    foreach ($rows as $value) {
      $this->assertEquals('Article', $value->getText());
    }

    // Searching for article, with english source language and italian target language.
    $this->drupalGet('admin/tmgmt/sources/config/node_type');
    $filters = array(
      'search[name]' => 'article',
      'search[langcode]' => 'en',
      'search[target_language]' => 'it',
    );
    $this->submitForm($filters, t('Search'));
    $rows = $this->xpath('//tbody/tr/td[2]/a');
    foreach ($rows as $value) {
      $this->assertEquals('Article', $value->getText());
    }

    // Searching by keywords (shorter terms).
    $this->drupalGet('admin/tmgmt/sources/config/node_type');
    $filters = array(
      'search[name]' => 'art',
      'search[langcode]' => 'en',
      'search[target_language]' => 'it',
    );
    $this->submitForm($filters, t('Search'));
    $rows = $this->xpath('//tbody/tr/td[2]/a');
    foreach ($rows as $value) {
      $this->assertEquals('Article', $value->getText());
    }
  }

  /**
   * Test for simple configuration translation.
   */
  function testSimpleConfigTranslation() {
    $this->loginAsTranslator(array('translate configuration'));

    // Go to the translate tab.
    $this->drupalGet('admin/tmgmt/sources/config/_simple_config');

    // Assert some basic strings on that page.
    $this->assertSession()->pageTextContains(t('Simple configuration overview (Config Entity)'));

    // Request a translation for Site information settings.
    $edit = array(
      'items[system.site_information_settings]' => TRUE,
    );
    $this->submitForm($edit, t('Request translation'));

    // Verify that we are on the translate tab.
    $this->assertSession()->pageTextContains(t('One job needs to be checked out.'));
    $this->assertSession()->pageTextContains('System information (English to ?, Unprocessed)');

    // Submit.
    $this->submitForm([], t('Submit to provider'));

    // Make sure that we're back on the originally defined destination URL.
    $this->assertSession()->addressEquals('admin/tmgmt/sources/config/_simple_config');

    $overview_url = Url::fromRoute('tmgmt.source_overview', array('plugin' => 'config', 'item_type' => '_simple_config'))->toString();

    // Translated languages should now be listed as Needs review.
    $url = JobItem::load(1)->toUrl()->setOption('query', ['destination' => $overview_url])->toString();
    $imgs = $this->xpath('//a[@href=:href]/img', [':href' => $url]);
    $this->assertEquals('Active job item: Needs review', $imgs[0]->getAttribute('title'));

    $this->assertSession()->pageTextContains(t('Test translation created.'));
    $this->assertSession()->pageTextContains('The translation of System information to German is finished and can now be reviewed.');

    // Verify that the pending translation is shown.
    $review = $this->xpath('//table[@id="edit-items"]/tbody/tr[@class="even"][1]/td[@class="langstatus-de"]/a');
    $destination = $this->getAbsoluteUrl($review[0]->getAttribute('href'));
    $this->drupalGet($destination);
    $this->submitForm([], t('Save'));

    // Request a translation for Account settings
    $edit = array(
      'items[entity.user.admin_form]' => TRUE,
    );
    $this->submitForm($edit, t('Request translation'));

    // Verify that we are on the checkout page.
    $this->assertSession()->pageTextContains(t('One job needs to be checked out.'));
    $this->assertSession()->pageTextContains('Account settings (English to ?, Unprocessed)');
    $this->submitForm([], t('Submit to provider'));

    // Make sure that we're back on the originally defined destination URL.
    $this->assertSession()->addressEquals('admin/tmgmt/sources/config/_simple_config');

    // Translated languages should now be listed as Needs review.
    $links = $this->xpath('//table[@id="edit-items"]/tbody/tr/td/a');
    $this->assertEquals(2, count($links));

    // Save one translation.
    $this->drupalGet('admin/tmgmt/items/1');
    $this->submitForm([], t('Save as completed'));

    // Test if the filter works.
    $this->drupalGet('admin/tmgmt/sources/config/_simple_config');
    $filters = array(
      'search[name]' => 'system',
    );
    $this->submitForm($filters, t('Search'));

    // Check if the list has 2 rows.
    $this->assertCount(2, $this->xpath('//tbody/tr'));

    $this->drupalGet('admin/tmgmt/sources/config/_simple_config');
    $filters = array(
      'search[target_language]' => 'de',
      'search[target_status]' => 'translated',
    );
    $this->submitForm($filters, t('Search'));

    // Just 1 simple configuration was translated.
    $this->assertCount(1, $this->xpath('//tbody/tr'));

    // Filter with name and target_status.
    $this->drupalGet('admin/tmgmt/sources/config/_simple_config');
    $filters = array(
      'search[name]' => 'settings',
      'search[target_language]' => 'de',
      'search[target_status]' => 'untranslated',
    );
    $this->submitForm($filters, t('Search'));

    // There is 1 simple configuration untranslated with name 'settings'.
    $this->assertCount(1, $this->xpath('//tbody/tr'));

    $this->drupalGet('admin/tmgmt/sources/config/_simple_config');
    $filters = array(
      'search[name]' => 'sys',
      'search[target_language]' => 'de',
      'search[target_status]' => 'translated',
    );
    $this->submitForm($filters, t('Search'));

    // There are 2 simple configurations with name 'sys' but just 1 is translated.
    $this->assertCount(1, $this->xpath('//tbody/tr'));
  }

  /**
   * Test for field configuration translation from source list.
   */
  function testFieldConfigList() {
    $this->drupalGet('admin/tmgmt/sources/config/field_config');

    // Test submission.
    $this->submitForm(['items[field.field.node.article.body]' => TRUE], t('Request translation'));
    $this->assertSession()->pageTextContains(t('One job needs to be checked out.'));
    $this->submitForm([], t('Submit to provider'));

    // Make sure that we're back on the originally defined destination URL.
    $this->assertSession()->addressEquals('admin/tmgmt/sources/config/field_config');
    $this->assertSession()->pageTextContains(t('Test translation created.'));
    $this->assertSession()->pageTextContains(t('The translation of Body to German is finished and can now be reviewed.'));
  }

}
