<?php

namespace Drupal\Tests\tmgmt_deepl\Functional;

use Drupal\Tests\tmgmt\Functional\TMGMTTestBase;
use Drupal\tmgmt\JobItemInterface;
use Drupal\tmgmt\TranslatorInterface;
use Drupal\tmgmt_deepl\Plugin\tmgmt\Translator\DeeplProTranslator;
use Drupal\Core\Url;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Basic tests for the DeepL Pro translator.
 *
 * @group tmgmt_deepl
 */
class DeeplProTranslatorTest extends TMGMTTestBase {

  use StringTranslationTrait;
  /**
   * A tmgmt_translator with a server mock.
   *
   * @var \Drupal\tmgmt\TranslatorInterface
   */
  protected TranslatorInterface $translator;

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = ['tmgmt', 'tmgmt_deepl', 'tmgmt_deepl_test'];

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();
    $this->addLanguage('de');
    // Override plugin params to query tmgmt_deepl_test mock service instead
    // of DeepL Pro Translate service.
    $this->translator = $this->createTranslator([
      'plugin' => 'deepl_pro',
      'settings' => [
        'test_url' => Url::fromUri('base://tmgmt_deepl_test/pro/translate', ['absolute' => TRUE])->toString(),
        'test_url_usage' => Url::fromUri('base://tmgmt_deepl_test/pro/usage', ['absolute' => TRUE])->toString(),
        'auth_key' => 'correct deepl pro key',
      ],
    ]);
  }

  /**
   * Tests basic API methods of the plugin.
   *
   * @todo Add test for continuous integration / fix breaking tests.
   */
  public function testDeeplPro(): void {
    $plugin = $this->translator->getPlugin();
    $this->assertTrue($plugin instanceof DeeplProTranslator, 'Plugin is a DeeplProTranslator');

    $job = $this->createJob();
    $job->set('translator', $this->translator->id());
    $job->save();

    $item = $job->addItem('test_source', 'test', '1');
    $item->set('data', ['wrapper' => ['#text' => 'Hello world']]);
    $item->save();

    $this->assertFalse($job->isTranslatable(), 'Check if the translator is not available at this point because we did not define the API parameters.');

    // Make sure the translator returns the correct supported target languages.
    $this->translator->clearLanguageCache();
    $languages = $this->translator->getSupportedTargetLanguages('EN');

    $this->assertTrue(isset($languages['bg']));
    $this->assertTrue(isset($languages['cs']));
    $this->assertTrue(isset($languages['da']));
    $this->assertTrue(isset($languages['de']));
    $this->assertTrue(isset($languages['el']));
    $this->assertTrue(isset($languages['EN-GB']));
    $this->assertTrue(isset($languages['EN-US']));
    $this->assertTrue(isset($languages['es']));
    $this->assertTrue(isset($languages['et']));
    $this->assertTrue(isset($languages['fi']));
    $this->assertTrue(isset($languages['fr']));
    $this->assertTrue(isset($languages['hu']));
    $this->assertTrue(isset($languages['id']));
    $this->assertTrue(isset($languages['it']));
    $this->assertTrue(isset($languages['ja']));
    $this->assertTrue(isset($languages['ko']));
    $this->assertTrue(isset($languages['lt']));
    $this->assertTrue(isset($languages['lv']));
    $this->assertTrue(isset($languages['nb']));
    $this->assertTrue(isset($languages['nl']));
    $this->assertTrue(isset($languages['pl']));
    $this->assertTrue(isset($languages['pt-pt']));
    $this->assertTrue(isset($languages['pt-br']));
    $this->assertTrue(isset($languages['PT']));
    $this->assertTrue(isset($languages['ro']));
    $this->assertTrue(isset($languages['ru']));
    $this->assertTrue(isset($languages['sk']));
    $this->assertTrue(isset($languages['sl']));
    $this->assertTrue(isset($languages['sv']));
    $this->assertTrue(isset($languages['tr']));
    $this->assertTrue(isset($languages['uk']));
    $this->assertTrue(isset($languages['zh']));

    // As we requested source language english it should not be included.
    $this->assertFalse(isset($languages['en']));

    $this->assertTrue($job->canRequestTranslation()->getSuccess());

    $job->requestTranslation();

    $batch = &batch_get();
    if ($batch) {
      $batch['progressive'] = FALSE;
      batch_process();
    }

    // Now it should be needs review.
    foreach ($job->getItems() as $job_item) {
      $this->assertTrue($job_item->isNeedsReview());
    }

    $items = $job->getItems();
    $item = end($items);
    if ($item instanceof JobItemInterface) {
      $data = $item->getData();
      $this->assertEquals('Hallo Welt', $data['dummy']['deep_nesting']['#translation']['#text']);
    }
  }

  /**
   * Tests the UI of the plugin.
   */
  public function testDeeplUi(): void {
    $url = Url::fromRoute('entity.tmgmt_translator.edit_form', ['tmgmt_translator' => $this->translator->id()]);
    $this->loginAsAdmin();

    // Save form with default settings and correct key.
    $this->drupalGet($url);
    $this->submitForm([], $this->t('Save'));
    $this->assertSession()->pageTextContains($this->t('@label configuration has been updated.', ['@label' => $this->translator->label()]));

    // Save form with wrong key.
    $edit = [
      'settings[auth_key]' => 'wrong key',
    ];
    $this->drupalGet($url);
    $this->submitForm($edit, $this->t('Save'));
    $this->assertSession()->pageTextContains($this->t('The "DeepL API authentication key" is not correct.'));
  }

}
