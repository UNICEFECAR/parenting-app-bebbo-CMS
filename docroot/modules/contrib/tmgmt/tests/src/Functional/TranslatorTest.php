<?php

namespace Drupal\Tests\tmgmt\Functional;

use Drupal\tmgmt\Entity\Job;
use Drupal\tmgmt\Entity\JobItem;

/**
 * Verifies functionality of translator handling
 *
 * @group tmgmt
 */
class TranslatorTest extends TMGMTTestBase {

  /**
   * {@inheritdoc}
   */
  function setUp(): void {
    parent::setUp();

    // Login as admin to be able to set environment variables.
    $this->loginAsAdmin();
    $this->addLanguage('de');
    $this->addLanguage('es');
    $this->addLanguage('el');

    // Login as translation administrator to run these tests.
    $this->loginAsTranslator(array(
      'administer tmgmt',
    ), TRUE);
  }


  /**
   * Tests creating and deleting a translator.
   */
  function testTranslatorHandling() {
    // Create a translator for later deletion.
    $translator = parent::createTranslator();
    // Does the translator exist in the listing?
    $this->drupalGet('admin/tmgmt/translators');
    $this->assertSession()->pageTextContains($translator->label());
    $this->assertCount(2, $this->xpath('//tbody/tr'));

    // Create job, attach to the translator and activate.
    $job = $this->createJob();
    $job->settings = array();
    $job->save();
    $job->setState(Job::STATE_ACTIVE);
    $item = $job->addItem('test_source', 'test', 1);
    $this->drupalGet('admin/tmgmt/items/' . $item->id());
    $this->assertSession()->pageTextContains(t('(Undefined)'));
    $job->translator = $translator;
    $job->save();

    // Try to delete the translator, should fail because of active job.
    $delete_url = '/admin/tmgmt/translators/manage/' . $translator->id() . '/delete';
    $this->drupalGet($delete_url);
    $this->assertSession()->statusCodeEquals(403);

    // Create a continuous job.
    $continuous = $this->createJob('en', 'de', 1, ['label' => 'Continuous', 'job_type' => Job::TYPE_CONTINUOUS]);
    $continuous->translator = $translator;
    $continuous->save();

    // Delete a provider using an API call and assert that active job and its
    // job item used by deleted translator were aborted.
    $translator->delete();
    /** @var \Drupal\tmgmt\JobInterface $job */
    $job = Job::load($job->id());
    $continuous = Job::load($continuous->id());
    $this->assertEquals(Job::STATE_ABORTED, $job->getState());
    $item = $job->getMostRecentItem('test_source', 'test', 1);
    $this->assertEquals(JobItem::STATE_ABORTED, $item->getState());
    $this->assertEquals(Job::STATE_ABORTED, $continuous->getState());

    // Delete a finished job.
    $translator = parent::createTranslator();
    $job = $this->createJob();
    $job->translator = $translator;
    $item = $job->addItem('test_source', 'test', 2);
    $job->setState(Job::STATE_FINISHED);
    $job->set('label', $job->label());
    $job->save();
    $delete_url = '/admin/tmgmt/translators/manage/' . $translator->id() . '/delete';
    $this->drupalGet($delete_url);
    $this->submitForm([], 'Delete');
    $this->assertSession()->pageTextContains(t('Add provider'));
    // Check if the list of translators has 1 row.
    $this->assertCount(1, $this->xpath('//tbody/tr'));
    $this->assertSession()->pageTextContains(t('@label has been deleted.', array('@label' => $translator->label())));

    // Check if the clone action works.
    $this->clickLink('Clone');
    $edit = array(
      'name' => $translator->id() . '_clone',
    );
    $this->submitForm($edit, 'Save');
    // Check if the list of translators has 2 row.
    $this->assertCount(2, $this->xpath('//tbody/tr'));
    $this->assertSession()->pageTextContains('configuration has been created');
    // Assert that the job works and there is a text saying that the translator
    // is missing.
    $this->drupalGet('admin/tmgmt/jobs/' . $job->id());
    $this->assertSession()->pageTextContains(t('The job has no provider assigned.'));

    // Assert that also the job items are working.
    $this->drupalGet('admin/tmgmt/items/' . $item->id());
    $this->assertSession()->pageTextContains(t('(Missing)'));

    // Testing the translators form with no installed translator plugins.
    // Uninstall the test module (which provides a translator).
    \Drupal::service('module_installer')->uninstall(array('tmgmt_test'), FALSE);

    // Assert that job deletion works correctly.
    \Drupal::service('module_installer')->install(array('tmgmt_file'), FALSE);
    $this->drupalGet($job->toUrl('delete-form'));
    $this->submitForm([], t('Delete'));
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains(t('The translation job @value has been deleted.', array('@value' => $job->label())));
    \Drupal::service('module_installer')->uninstall(array('tmgmt_file'), FALSE);

    // Get the overview.
    $this->drupalGet('admin/tmgmt/translators');
    $this->assertSession()->pageTextNotContains(t('Add provider'));
    $this->assertSession()->pageTextContains(t('There are no provider plugins available. Please install a provider plugin.'));
  }

  /**
   * Tests remote languages mappings support in the tmgmt core.
   */
  public function testRemoteLanguagesMappings() {
    $mappings = $this->default_translator->getRemoteLanguagesMappings();
    $this->assertEquals(array(
      'en' => 'en-us',
      'de' => 'de-ch',
      'el' => 'el',
      'es' => 'es',
    ), $mappings);

    $this->assertEquals('en-us', $this->default_translator->mapToRemoteLanguage('en'));
    $this->assertEquals('de-ch', $this->default_translator->mapToRemoteLanguage('de'));

    $remote_language_mappings = $this->default_translator->get('remote_languages_mappings');
    $remote_language_mappings['de'] = 'de-de';
    $remote_language_mappings['en'] = 'en-uk';
    $this->default_translator->set('remote_languages_mappings', $remote_language_mappings);
    $this->default_translator->save();

    $this->assertEquals('en-uk', $this->default_translator->mapToRemoteLanguage('en'));
    $this->assertEquals('de-de', $this->default_translator->mapToRemoteLanguage('de'));

    // Test the fallback.
    $this->container->get('state')->set('tmgmt_test_translator_map_languages', FALSE);
    $this->container->get('plugin.manager.tmgmt.translator')->clearCachedDefinitions();

    $this->assertEquals('en', $this->default_translator->mapToRemoteLanguage('en'));
    $this->assertEquals('de', $this->default_translator->mapToRemoteLanguage('de'));
  }

}
