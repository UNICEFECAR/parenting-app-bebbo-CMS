<?php

namespace Drupal\Tests\tmgmt_locale\Functional;

use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Url;
use Drupal\locale\Gettext;
use Drupal\Tests\tmgmt\Functional\TMGMTTestBase;

/**
 * Locale Source UI tests.
 *
 * @group tmgmt
 */
class LocaleSourceUiTest extends TMGMTTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = array('tmgmt_locale');

  /**
   * {@inheritdoc}
   */
  function setUp(): void {
    parent::setUp();
    $this->langcode = 'de';
    $this->context = 'default';
    $file = new \stdClass();
    $npath = \Drupal::service('extension.list.module')->getPath('tmgmt_locale');
    $file->uri =  \Drupal::service('file_system')->realpath($npath . '/tests/test.xx.po');
    $file->langcode = $this->langcode;
    Gettext::fileToDatabase($file, array());
    $this->addLanguage($this->langcode);
    $this->addLanguage('gsw-berne');
  }

  public function testOverview() {
    $this->loginAsTranslator();
    $this->drupalGet('admin/tmgmt/sources/locale/default');

    $this->assertSession()->pageTextContains('Hello World');
    $this->assertSession()->pageTextContains('Example');
    $rows = $this->xpath('//tbody/tr');
    $found = FALSE;
    foreach ($rows as $row) {
      if ($row->find('css', 'td:nth-child(2)')->getText() == 'Hello World') {
        $found = TRUE;
        $this->assertEquals('tmgmt_locale', (string) $row->find('css', 'td:nth-child(3)')->getText());
        $this->assertEquals(t('Translation up to date'), (string) $row->find('css', 'td:nth-child(5) img')->getAttribute('title'));
        $this->assertEquals(t('Not translated'), (string) $row->find('css', 'td:nth-child(6) img')->getAttribute('title'));
      }
    }
    $this->assertTrue($found);

    // Filter on the label.
    $edit = array('search[label]' => 'Hello');
    $this->submitForm($edit, t('Search'));

    $this->assertSession()->pageTextContains('Hello World');
    $this->assertSession()->pageTextNotContains('Example');

    $locale_object = \Drupal::database()->query('SELECT * FROM {locales_source} WHERE source = :source LIMIT 1', array(':source' => 'Hello World'))->fetchObject();

    // First add source to the cart to test its functionality.
    $edit = array(
      'items[' . $locale_object->lid . ']' => TRUE,
    );
    $this->submitForm($edit, t('Add to cart'));
    $this->assertSession()->responseContains(t('@count content source was added into the <a href=":url">cart</a>.', array('@count' => 1, ':url' => Url::fromRoute('tmgmt.cart')->toString())));
    $edit['target_language[]'] = array('gsw-berne');
    $this->drupalGet('admin/tmgmt/cart');
    $this->submitForm($edit, t('Request translation'));

    // Assert that the job item is displayed.
    $this->assertSession()->pageTextContains('Hello World');
    $this->assertSession()->pageTextContains(t('Locale'));
    $this->assertSession()->pageTextContains('2');
    $this->submitForm(['target_language' => 'gsw-berne'], t('Submit to provider'));

    // Test for the translation flag title.
    $this->drupalGet('admin/tmgmt/sources/locale/default');
    $this->assertSession()->responseContains(t('Active job item: Needs review'));

    // Review and accept the job item.
    $job_items = tmgmt_job_item_load_latest('locale', 'default', $locale_object->lid, 'en');
    $this->drupalGet('admin/tmgmt/items/' . $job_items['gsw-berne']->id());
    $this->assertSession()->responseContains('gsw-berne: Hello World');
    $this->submitForm([], t('Save as completed'));
    $this->drupalGet('admin/tmgmt/sources/locale/default');

    $this->assertSession()->responseNotContains(t('Active job item: Needs review'));
    $rows = $this->xpath('//tbody/tr');
    $found = FALSE;
    foreach ($rows as $row) {
      if ($row->find('css', 'td:nth-child(2)')->getText() == 'Hello World') {
        $found = TRUE;
        $this->assertEquals(t('Translation up to date'), (string) $row->find('css', 'td:nth-child(5) img')->getAttribute('title'));
        $this->assertEquals(t('Translation up to date'), (string) $row->find('css', 'td:nth-child(6) img')->getAttribute('title'));
      }
    }
    $this->assertTrue($found);

    // Test the missing translation filter.
    $this->drupalGet('admin/tmgmt/sources/locale/default');
    // Check that the source language (en) has been removed from the target language
    // select box.
    $elements = $this->xpath('//select[@name=:name]//option[@value=:option]', array(':name' => 'search[target_language]', ':option' => 'en'));
    $this->assertTrue(empty($elements));

    // Filter on the "Not translated to".
    $edit = array('search[missing_target_language]' => 'gsw-berne');
    $this->submitForm($edit, t('Search'));
    // Hello world is translated to "gsw-berne" therefore it must not show up
    // in the list.
    $this->assertSession()->pageTextNotContains('Hello World');

    // Filter on the tmgmt_locale context.
    $this->drupalGet('admin/tmgmt/sources/locale/default');
    $edit = array('search[context]' => 'tmgmt_locale');
    $this->submitForm($edit, t('Search'));
    $this->assertSession()->pageTextContains('Hello World');
    $this->assertSession()->pageTextNotContains('Example');
  }
}
