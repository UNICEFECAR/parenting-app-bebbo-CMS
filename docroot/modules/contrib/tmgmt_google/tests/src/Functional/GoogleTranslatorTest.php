<?php

namespace Drupal\Tests\tmgmt_google\Functional;

use Drupal\node\Entity\Node;
use Drupal\Tests\tmgmt\Functional\TMGMTTestBase;
use Drupal\tmgmt\Entity\Job;
use Drupal\tmgmt\JobItemInterface;
use Drupal\tmgmt_google\Plugin\tmgmt\Translator\GoogleTranslator;
use Drupal\tmgmt\TranslatorInterface;
use Drupal\Core\Url;

/**
 * Basic tests for the Google translator.
 *
 * @group tmgmt_google
 */
class GoogleTranslatorTest extends TMGMTTestBase {

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
  protected static $modules = ['tmgmt_google', 'tmgmt_google_test'];

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();
    $this->addLanguage('de');
    // Override plugin params to query tmgmt_google_test mock service instead
    // of Google Translate service.
    $url = Url::fromUri('base://tmgmt_google_test', array('absolute' => TRUE))->toString();
    $this->translator = $this->createTranslator([
      'plugin' => 'google',
      'settings' => ['url' => $url],
    ]);
  }

  /**
   * Tests basic API methods of the plugin.
   */
  public function testGoogle(): void {
    $plugin = $this->translator->getPlugin();
    $this->assertInstanceOf(GoogleTranslator::class, $plugin, 'Plugin is a GoogleTranslator');

    $job = $this->createJob();
    $job->translator = $this->translator->id();
    $job->save();
    $item = $job->addItem('test_source', 'test', '1');
    $item->data = array(
      'wrapper' => array(
        '#text' => 'Hello world & welcome',
      ),
    );
    $item->save();

    $this->assertFalse($job->isTranslatable(), 'Check if the translator is not available at this point because we did not define the API parameters.');

    // Save a wrong api key.
    $this->translator->setSetting('api_key', 'wrong key');
    $this->translator->save();

    $languages = $this->translator->getSupportedTargetLanguages('en');
    $this->assertEmpty($languages, 'We can not get the languages using wrong api parameters.');

    // Save a correct api key.
    $this->translator->setSetting('api_key', 'correct key');
    $this->translator->save();

    // Make sure the translator returns the correct supported target languages.
    $this->translator->clearLanguageCache();
    $languages = $this->translator->getSupportedTargetLanguages('en');

    $this->assertTrue(isset($languages['de']));
    $this->assertTrue(isset($languages['fr']));
    // As we requested source language english it should not be included.
    $this->assertNotTrue(isset($languages['en']));

    $this->assertTrue($job->canRequestTranslation()->getSuccess());

    $job->requestTranslation();

    // Now it should be needs review.
    foreach ($job->getItems() as $item) {
      $this->assertTrue($item->isNeedsReview());
    }
    $items = $job->getItems();
    $item = end($items);
    $data = $item->getData();
    $this->assertEquals('Hallo Welt & willkommen', $data['dummy']['deep_nesting']['#translation']['#text']);

    // Test continuous integration.
    $this->config('tmgmt.settings')
      ->set('submit_job_item_on_cron', TRUE)
      ->save();

    // Continuous settings configuration.
    $continuous_settings = [
      'content' => [
        'node' => [
          'enabled' => 1,
          'bundles' => [
            'test' => 1,
          ],
        ],
      ],
    ];

    $continuous_job = $this->createJob('en', 'de', 0, [
      'label' => 'Continuous job',
      'job_type' => Job::TYPE_CONTINUOUS,
      'translator' => $this->translator,
      'continuous_settings' => $continuous_settings,
    ]);
    $continuous_job->save();

    // Create an english node.
    $node = Node::create([
      'title' => $this->randomMachineName(),
      'uid' => 0,
      'type' => 'test',
      'langcode' => 'en',
    ]);
    $node->save();

    $continuous_job->addItem('test_source', $node->getEntityTypeId(), $node->id());

    $continuous_job_items = $continuous_job->getItems();
    $continuous_job_item = reset($continuous_job_items);
    $this->assertEquals(JobItemInterface::STATE_INACTIVE, $continuous_job_item->getState());

    tmgmt_cron();

    $items = $continuous_job->getItems();
    $item = reset($items);
    $data = $item->getData();
    $this->assertEquals('Hallo Welt & willkommen', $data['dummy']['deep_nesting']['#translation']['#text']);
    $this->assertEquals(Job::STATE_CONTINUOUS, $continuous_job->getState());
    $this->assertEquals(JobItemInterface::STATE_REVIEW, $item->getState());
  }

  /**
   * Tests the UI of the plugin.
   */
  public function testGoogleUi(): void {
    $url = Url::fromRoute('entity.tmgmt_translator.edit_form', ['tmgmt_translator' => $this->translator->id()]);
    $this->loginAsAdmin();

    // Try to connect with invalid credentials.
    $edit = [
      'settings[api_key]' => 'wrong key',
    ];
    $this->drupalGet($url);
    $this->submitForm($edit, t('Connect'));
    $this->assertSession()->pageTextContains(t('The "Google API key" is not correct.'));

    // Test connection with valid credentials.
    $edit = [
      'settings[api_key]' => 'correct key',
    ];
    $this->drupalGet($url);
    $this->submitForm($edit, t('Connect'));
    $this->assertSession()->pageTextContains('Successfully connected!');

    // Assert that default remote languages mappings were updated.
    $this->assertTrue($this->assertSession()->optionExists('edit-remote-languages-mappings-en', 'en')->isSelected());
    $this->assertTrue($this->assertSession()->optionExists('edit-remote-languages-mappings-de', 'de')->isSelected());

    $this->submitForm([], t('Save'));
    $this->assertSession()->pageTextContains(t('@label configuration has been updated.', ['@label' => $this->translator->label()]));
  }

}
