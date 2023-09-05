<?php

namespace Drupal\Tests\tmgmt\Functional;

use Drupal\tmgmt\Entity\Job;
use Drupal\tmgmt\Entity\Translator;
use Drupal\tmgmt\JobInterface;
use Drupal\tmgmt\JobItemInterface;

/**
 * Verifies continuous functionality of the user interface
 *
 * @group tmgmt
 */
class TMGMTUiContinuousTest extends TMGMTTestBase {
  use TmgmtEntityTestTrait;

  protected static $modules = ['tmgmt_content'];

  /**
   * {@inheritdoc}
   */
  function setUp(): void {
    parent::setUp();

    // Login as admin to be able to set environment variables.
    $this->loginAsAdmin([
      'translate any entity',
      'create content translations',
    ]);
    $this->addLanguage('de');
    $this->addLanguage('es');

    $this->drupalPlaceBlock('system_breadcrumb_block');

    $this->createNodeType('page', 'Page', TRUE);
    $this->createNodeType('article', 'Article', TRUE);
  }

  /**
   * Tests of the job item review process.
   */
  public function testContinuous() {
    // Test that continuous jobs are shown in the job overview.
    $this->container->get('module_installer')->install(['tmgmt_file'], TRUE);
    $non_continuous_translator = Translator::create([
      'name' => strtolower($this->randomMachineName()),
      'label' => $this->randomMachineName(),
      'plugin' => 'file',
      'remote_languages_mappings' => [],
      'settings' => [],
    ]);
    $non_continuous_translator->save();

    $continuous_job = $this->createJob('en', 'de', 0, [
      'label' => $this->randomMachineName(),
      'job_type' => 'continuous',
    ]);

    $this->drupalGet('admin/tmgmt/jobs', ['query' => ['state' => JobInterface::STATE_CONTINUOUS]]);
    $this->assertSession()->pageTextContains($continuous_job->label());

    $this->drupalGet('admin/tmgmt/jobs', ['query' => ['state' => 'job_item_' . JobItemInterface::STATE_ACTIVE]]);
    $this->assertSession()->pageTextNotContains($continuous_job->label());

    // Test that there are source items checkboxes on a continuous job form.
    $this->drupalGet('admin/tmgmt/jobs/' . $continuous_job->id());
    $this->assertSession()->pageTextContains($continuous_job->label());
    $this->assertSession()->checkboxNotChecked('edit-continuous-settings-content-node-enabled');
    $this->assertSession()->checkboxNotChecked('edit-continuous-settings-content-node-bundles-article');
    $this->assertSession()->checkboxNotChecked('edit-continuous-settings-content-node-bundles-page');

    // Enable Article source item for continuous job.
    $edit_continuous_job = [
      'continuous_settings[content][node][enabled]' => TRUE,
      'continuous_settings[content][node][bundles][article]' => TRUE,
    ];
    $this->submitForm($edit_continuous_job, t('Save job'));

    // Test that continuous settings configuration is saved correctly.
    $updated_continuous_job = Job::load($continuous_job->id());
    $job_continuous_settings = $updated_continuous_job->getContinuousSettings();
    $this->assertEquals(1, $job_continuous_settings['content']['node']['enabled'], 'Continuous settings configuration for node is saved correctly.');
    $this->assertEquals(1, $job_continuous_settings['content']['node']['bundles']['article'], 'Continuous settings configuration for article is saved correctly.');
    $this->assertEquals(0, $job_continuous_settings['content']['node']['bundles']['page'], 'Continuous settings configuration for page is saved correctly.');

    // Test that continuous settings checkboxes are checked correctly.
    $this->clickLink('Manage');
    $this->assertSession()->pageTextContains($continuous_job->label());
    $this->assertSession()->checkboxChecked('edit-continuous-settings-content-node-enabled');
    $this->assertSession()->checkboxChecked('edit-continuous-settings-content-node-bundles-article');
    $this->assertSession()->checkboxNotChecked('edit-continuous-settings-content-node-bundles-page');

    // Create continuous job through the form.
    $this->loginAsTranslator([
      'access administration pages',
      'create translation jobs',
      'submit translation jobs',
      'access user profiles',
    ], TRUE);
    $owner = $this->drupalCreateUser($this->translator_permissions);
    $this->drupalGet('admin/tmgmt/continuous_jobs/continuous_add');
    $this->assertSession()->statusCodeEquals(403);
    $this->loginAsAdmin();
    $this->drupalGet('admin/tmgmt/continuous_jobs/continuous_add');
    $this->assertSession()->pageTextNotContains($non_continuous_translator->label());
    $continuous_job_label = strtolower($this->randomMachineName());

    $edit_job = [
      'label[0][value]' => $continuous_job_label,
      'target_language' => 'de',
      'uid' => $owner->getDisplayName(),
      'translator' => $this->default_translator->id(),
    ];
    $this->submitForm($edit_job, t('Save job'));
    $this->assertSession()->pageTextContains($continuous_job_label);

    // Test that previous created job is continuous job.
    $this->drupalGet('admin/tmgmt/jobs');
    $this->assertSession()->pageTextContains($continuous_job_label);
    // Test that job overview page with status to continuous does not have
    // Submit link.
    $this->drupalGet('admin/tmgmt/jobs', ['query' => ['state' => JobInterface::STATE_CONTINUOUS]]);
    $this->assertSession()->linkNotExists('Submit', 'There is no Submit link on job overview with status to continuous.');

    // Test that all unnecessary fields and buttons do not exist on continuous
    // job edit form.
    $this->clickLink('Manage', 0);
    $this->assertSession()->pageTextContains($continuous_job_label);
    $this->assertSession()->fieldValueEquals('edit-uid', $owner->getDisplayName() . ' (' . $owner->id() . ')');
    $this->assertSession()->responseNotContains('<label for="edit-translator">Provider</label>');
    $this->assertSession()->responseNotContains('<label for="edit-word-count">Total word count</label>');
    $this->assertSession()->responseNotContains('<label for="edit-tags-count">Total HTML tags count</label>');
    $this->assertSession()->responseNotContains('<label for="edit-created">Created</label>');
    $this->assertSession()->responseNotContains('id="edit-job-items-wrapper"');
    $this->assertSession()->responseNotContains('<div class="tmgmt-color-legend clearfix">');
    $this->assertSession()->responseNotContains('id="edit-messages"');
    $this->assertSession()->fieldNotExists('edit-abort-job');
    $this->assertSession()->fieldNotExists('edit-submit');
    $this->assertSession()->fieldNotExists('edit-resubmit-job');

    // Remove continuous jobs and assert there is no filter displayed.
    $this->loginAsAdmin();
    $continuous_job->delete();
    $this->drupalGet('admin/tmgmt/jobs');
    $this->clickLink(t('Delete'));
    $this->submitForm([], t('Delete'));
    $this->drupalGet('admin/tmgmt/job_items');
    $this->assertSession()->pageTextNotContains(t('Job type'));
    $this->assertSession()->fieldNotExists('job_type');

    // Test that the empty text is displayed.
    $this->drupalGet('admin/tmgmt/job_items', array('query' => array('state' => 5)));
    $this->assertSession()->pageTextContains(t('No job items for the current selection.'));
  }


  /**
   * Tests access to add continuous job link.
   */
  public function testAddContinuousLink() {
    $this->drupalLogin($this->createUser(['create translation jobs']));
    $this->drupalGet('admin/tmgmt/jobs');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextNotContains('Add continuous job');
    $this->drupalLogin($this->admin_user);
    $this->drupalGet('admin/tmgmt/jobs');
    $this->assertSession()->pageTextContains('Add continuous job');
    \Drupal::service('module_installer')->uninstall(['tmgmt_test']);
    $this->drupalGet('admin/tmgmt/jobs');
    $this->assertSession()->pageTextNotContains('Add continuous job');
    // The 'Add continuous job' is currently not showing up without clearing the
    // cache after we add a continuous translator.
    // @see https://www.drupal.org/node/2685445
    // \Drupal::service('module_installer')->install(['tmgmt_test']);
    // $this->drupalGet('admin/tmgmt/jobs');
    // $this->assertSession()->pageTextContains('Add continuous job', 'Link is showing if there is a continuous translator.');
  }

}
