<?php

namespace Drupal\Tests\tmgmt\Functional;

use Drupal\Core\Language\LanguageInterface;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\tmgmt\Entity\Job;
use Drupal\tmgmt\Entity\JobItem;
use Drupal\tmgmt\Entity\Translator;
use Drupal\filter\Entity\FilterFormat;
use Drupal\tmgmt\JobInterface;
use Drupal\tmgmt\JobItemInterface;

/**
 * Verifies basic functionality of the user interface
 *
 * @group tmgmt
 */
class TMGMTUiTest extends TMGMTTestBase {
  use TmgmtEntityTestTrait;

  /**
   * {@inheritdoc}
   */
  function setUp(): void {
    parent::setUp();

    $filtered_html_format = FilterFormat::create(array(
      'format' => 'filtered_html',
      'name' => 'Filtered HTML',
    ));
    $filtered_html_format->save();

    $this->addLanguage('de');
    $this->addLanguage('es');
    $this->addLanguage('el');

    // Login as translator only with limited permissions to run these tests.
    $this->loginAsTranslator(array(
      'access administration pages',
      'create translation jobs',
      'submit translation jobs',
      $filtered_html_format->getPermissionName(),
    ), TRUE);
    $this->drupalPlaceBlock('system_breadcrumb_block');

    $this->createNodeType('page', 'Page', TRUE);
    $this->createNodeType('article', 'Article', TRUE);
  }

  /**
   * Test the page callbacks to create jobs and check them out.
   *
   * This includes
   * - Varying checkout situations with form detail values.
   * - Unsupported checkout situations where translator is not available.
   * - Exposed filters for job overview
   * - Deleting a job
   *
   * @todo Separate the exposed filter admin overview test.
   */
  function testCheckoutForm() {
    // Add a first item to the job. This will auto-create the job.
    $job = tmgmt_job_match_item('en', '');
    $job->addItem('test_source', 'test', 1);

    // Go to checkout form.
    $this->drupalGet($job->toUrl());

    // Test primary buttons.
    $this->assertSession()->responseContains('Save job" class="button js-form-submit form-submit"');

    // Check checkout form.
    $this->assertSession()->pageTextContains('test_source:test:1');

    // Assert that the messages element is not shown.
    $this->assertSession()->pageTextNotContains('Translation Job messages');
    $this->assertSession()->pageTextNotContains('Checkout progress');

    // Add two more job items.
    $job->addItem('test_source', 'test', 2);
    $job->addItem('test_source', 'test', 3);

    // Go to checkout form.
    $this->drupalGet($job->toUrl());

    // Check checkout form.
    $this->assertSession()->pageTextContains('test_source:test:1');
    $this->assertSession()->pageTextContains('test_source:test:2');
    $this->assertSession()->pageTextContains('test_source:test:3');

    // @todo: Test ajax functionality.

    // Attempt to translate into greek.
    $edit = array(
      'target_language' => 'el',
      'settings[action]' => 'translate',
    );
    $this->submitForm($edit, t('Submit to provider'));
    $this->assertSession()->pageTextContains(t('@translator can not translate from @source to @target.', array('@translator' => 'Test provider', '@source' => 'English', '@target' => 'Greek')));

    // Job still needs to be in state new.
    /** @var \Drupal\tmgmt\JobInterface $job */
    $job = \Drupal::entityTypeManager()->getStorage('tmgmt_job')->loadUnchanged($job->id());
    $this->assertTrue($job->isUnprocessed());

    // The owner must be the one that submits the job.
    $this->assertTrue($job->isAuthor());
    $this->drupalLogin($this->translator_user);
    $this->drupalGet('admin/tmgmt/jobs/' . $job->id());

    $edit = array(
      'target_language' => 'es',
      'settings[action]' => 'translate',
    );
    $this->submitForm($edit, t('Submit to provider'));
    /** @var \Drupal\tmgmt\JobInterface $job */
    $job = \Drupal::entityTypeManager()->getStorage('tmgmt_job')->loadUnchanged($job->id());
    $this->assertTrue($job->isAuthor());

    // Job needs to be in state active.
    $job = \Drupal::entityTypeManager()->getStorage('tmgmt_job')->loadUnchanged($job->id());
    $this->assertTrue($job->isActive());
    foreach ($job->getItems() as $job_item) {
      /* @var $job_item \Drupal\tmgmt\JobItemInterface */
      $this->assertTrue($job_item->isNeedsReview());
    }
    $this->assertSession()->pageTextContains(t('Test translation created'));
    $this->assertSession()->pageTextNotContains(t('Test provider called'));

    // Test redirection.
    $this->assertSession()->pageTextContains(t('Job overview'));

    // Another job.
    $previous_tjid = $job->id();
    $job = tmgmt_job_match_item('en', '');
    $job->addItem('test_source', 'test', 9);
    $this->assertNotEquals($previous_tjid, $job->id());

    // Go to checkout form.
    $this->drupalGet($job->toUrl());

     // Check checkout form.
    $this->assertSession()->pageTextContains('You can provide a label for this job in order to identify it easily later on.');
    $this->assertSession()->pageTextContains('test_source:test:9');

    $edit = array(
      'target_language' => 'es',
      'settings[action]' => 'submit',
    );
    $this->submitForm($edit, t('Submit to provider'));
    $this->assertSession()->pageTextContains(t('Test submit'));
    $job = \Drupal::entityTypeManager()->getStorage('tmgmt_job')->loadUnchanged($job->id());
    $this->assertTrue($job->isActive());

    // Another job.
    $job = tmgmt_job_match_item('en', 'es');
    $item10 = $job->addItem('test_source', 'test', 10);

    // Go to checkout form.
    $this->drupalGet($job->toUrl());

     // Check checkout form.
    $this->assertSession()->pageTextContains('You can provide a label for this job in order to identify it easily later on.');
    $this->assertSession()->pageTextContains('test_source:test:10');

    $edit = array(
      'settings[action]' => 'reject',
    );
    $this->submitForm($edit, t('Submit to provider'));
    $this->assertSession()->pageTextContains(t('This is not supported'));
    $job = \Drupal::entityTypeManager()->getStorage('tmgmt_job')->loadUnchanged($job->id());
    $this->assertTrue($job->isRejected());

    // Check displayed job messages.
    $args = array('@view' => 'view-tmgmt-job-messages');
    $this->assertCount(2, $this->xpath('//div[contains(@class, @view)]//tbody/tr', $args));

    // Check that the author for each is the current user.
    $message_authors = $this->xpath('//div[contains(@class, @view)]//td[contains(@class, @field)]/*[self::a or self::span]  ', $args + array('@field' => 'views-field-name'));
    $this->assertCount(2, $message_authors);
    foreach ($message_authors as $message_author) {
      $this->assertEquals($this->translator_user->getDisplayName(), $message_author->getText());
    }

    // Make sure that rejected jobs can be re-submitted.
    $this->assertTrue($job->isSubmittable());
    $edit = array(
      'settings[action]' => 'translate',
    );
    $this->submitForm($edit, t('Submit to provider'));
    $this->assertSession()->pageTextContains(t('Test translation created'));

    // Now that this job item is in the reviewable state, test primary buttons.
    $this->drupalGet('admin/tmgmt/items/' . $item10->id());
    $this->assertSession()->responseContains('Save" class="button js-form-submit form-submit"');
    $this->submitForm([], t('Save'));
    $this->clickLink('View');
    $this->assertSession()->responseContains('Save as completed" class="button button--primary js-form-submit form-submit"');
    $this->submitForm([], t('Save'));
    $this->assertSession()->responseContains('Save job" class="button button--primary js-form-submit form-submit"');
    $this->submitForm([], t('Save job'));

    // HTML tags count.
    \Drupal::state()->set('tmgmt.test_source_data', array(
      'title' => array(
        'deep_nesting' => array(
          '#text' => '<p><em><strong>Six dummy HTML tags in the title.</strong></em></p>',
          '#label' => 'Title',
        ),
      ),
      'body' => array(
        'deep_nesting' => array(
          '#text' => '<p>Two dummy HTML tags in the body.</p>',
          '#label' => 'Body',
        )
      ),
      'phantom' => array(
        'deep_nesting' => array(
          '#text' => 'phantom text',
          '#label' => 'phantom label',
          '#translate' => FALSE,
          '#format' => 'filtered_html',
        ),
      ),
    ));
    $item4 = $job->addItem('test_source', 'test', 4);
    // Manually active the item as the test expects that.
    $item4->active();
    $this->drupalGet('admin/tmgmt/items/' . $item4->id());
    // Test if the phantom wrapper is not displayed because of #translate FALSE.
    $this->assertSession()->responseNotContains('tmgmt-ui-element-phantom-wrapper');

    $this->drupalGet('admin/tmgmt/jobs');

    // Total number of tags should be 8 for this job.
    $rows = $this->xpath('//table[@class="views-table views-view-table cols-10"]/tbody/tr');
    $found = FALSE;
    foreach ($rows as $row) {
      if (trim($row->find('css', 'td:nth-child(2)')->getText()) == 'test_source:test:10') {
        $found = TRUE;
        $this->assertEquals(8, $row->find('css', 'td:nth-child(8)')->getText());
      }
    }
    $this->assertTrue($found);

    // Another job.
    $job = tmgmt_job_match_item('en', 'es');
    $job->addItem('test_source', 'test', 11);

    // Go to checkout form.
    $this->drupalGet($job->toUrl());

     // Check checkout form.
    $this->assertSession()->pageTextContains('You can provide a label for this job in order to identify it easily later on.');
    $this->assertSession()->pageTextContains('test_source:test:11');

    $edit = array(
      'settings[action]' => 'fail',
    );
    $this->submitForm($edit, t('Submit to provider'));
    $this->assertSession()->pageTextContains(t('Service not reachable'));
    \Drupal::entityTypeManager()->getStorage('tmgmt_job')->resetCache();
    $job = Job::load($job->id());
    $this->assertTrue($job->isUnprocessed());

    // Verify that we are still on the form.
    $this->assertSession()->pageTextContains('You can provide a label for this job in order to identify it easily later on.');

    // Another job.
    $job = tmgmt_job_match_item('en', 'es');
    $job->addItem('test_source', 'test', 12);

    // Go to checkout form.
    $this->drupalGet($job->toUrl());

    // Check checkout form.
    $this->assertSession()->pageTextContains('You can provide a label for this job in order to identify it easily later on.');
    $this->assertSession()->pageTextContains('test_source:test:12');

    $edit = array(
      'settings[action]' => 'not_translatable',
    );
    $this->submitForm($edit, t('Submit to provider'));
    // @todo Update to correct failure message.
    $this->assertSession()->pageTextContains(t('Fail'));
    $job = \Drupal::entityTypeManager()->getStorage('tmgmt_job')->loadUnchanged($job->id());
    $this->assertTrue($job->isUnprocessed());

    // Test default settings.
    $this->default_translator->setSetting('action', 'reject');
    $this->default_translator->save();
    $job = tmgmt_job_match_item('en', 'es');
    $job->addItem('test_source', 'test', 13);

    // Go to checkout form.
    $this->drupalGet($job->toUrl());

     // Check checkout form.
    $this->assertSession()->pageTextContains('You can provide a label for this job in order to identify it easily later on.');
    $this->assertSession()->pageTextContains('test_source:test:13');

    // The action should now default to reject.
    $this->submitForm([], t('Submit to provider'));
    $this->assertSession()->pageTextContains(t('This is not supported.'));
    $job4 = \Drupal::entityTypeManager()->getStorage('tmgmt_job')->loadUnchanged($job->id());
    $this->assertTrue($job4->isRejected());

    $this->drupalGet('admin/tmgmt/jobs');

    // Test if sources languages are correct.
    $sources = $this->xpath('//table[@class="views-table views-view-table cols-10"]/tbody/tr/td[@class="views-field views-field-source-language-1"][contains(., "English")]');
    $this->assertCount(4, $sources);

    // Test if targets languages are correct.
    $targets = $this->xpath('//table[@class="views-table views-view-table cols-10"]/tbody/tr/td[@class="views-field views-field-target-language"][contains(., "Spanish") or contains(., "German")]');
    $this->assertCount(4, $targets);

    // Check that the first action is 'manage'.
    $first_action = $this->xpath('//tbody/tr[2]/td[10]/div/div/ul/li[1]/a');
    $this->assertEquals('Manage', $first_action[0]->getText());

    // Test for Unavailable/Unconfigured Translators.
    $this->default_translator->setSetting('action', 'not_translatable');
    $this->default_translator->save();
    $this->drupalGet('admin/tmgmt/jobs/' . $job->id());
    $this->submitForm(['target_language' => 'de'], t('Submit to provider'));
    $this->assertSession()->pageTextContains(t('Test provider can not translate from English to German.'));

    // Test for Unavailable/Unconfigured Translators.
    $this->default_translator->setSetting('action', 'not_available');
    $this->default_translator->save();
    $this->drupalGet('admin/tmgmt/jobs/' . $job->id());
    $this->assertSession()->pageTextContains(t('Test provider is not available. Make sure it is properly configured.'));
    $this->submitForm([], t('Submit to provider'));
    $this->assertSession()->pageTextContains(t('@translator is not available. Make sure it is properly configured.', array('@translator' => 'Test provider')));

    // Login as translator with permission to delete inactive job.
    $this->loginAsTranslator(['delete translation jobs']);
    $this->drupalGet('admin/tmgmt/jobs', array('query' => array(
      'state' => 'All',
    )));

    // Translated languages should now be listed as Needs review.
    $start_rows = $this->xpath('//tbody/tr');
    $this->assertCount(4, $start_rows);
    $this->drupalGet($job4->toUrl('delete-form'));
    $this->assertSession()->pageTextContains('Are you sure you want to delete the translation job test_source:test:11 and 2 more?');
    $this->submitForm([], t('Delete'));
    $this->drupalGet('admin/tmgmt/jobs', array('query' => array(
      'state' => 'All',
    )));
    $end_rows = $this->xpath('//tbody/tr');
    $this->assertCount(3, $end_rows);
    $this->drupalGet('admin/tmgmt/items/' . $item4->id());
    $this->clickLink('Abort');
    $this->submitForm([], t('Confirm'));
    $this->assertSession()->pageTextContains('Aborted');
    $this->assertSession()->linkNotExists('Abort');

    // Create active job.
    $job_active = $this->createJob();
    $job_active->save();
    $job_active->setState(Job::STATE_ACTIVE);

    // Even if 'delete translation jobs' permission is granted active job
    // cannot be deleted.
    $this->drupalGet($job_active->toUrl('delete-form'));
    $this->assertSession()->statusCodeEquals(403);
  }

  /**
   * Tests the tmgmt_job_checkout() function.
   */
  function testCheckoutFunction() {
    $job = $this->createJob();

    /** @var \Drupal\tmgmt\JobCheckoutManager $job_checkout_manager */
    $job_checkout_manager = \Drupal::service('tmgmt.job_checkout_manager');

    // Check out a job when only the test translator is available. That one has
    // settings, so a checkout is necessary.
    $jobs = $job_checkout_manager->checkoutMultiple(array($job));
    $this->assertEquals($job->id(), $jobs[0]->id());
    $this->assertTrue($job->isUnprocessed());
    $job->delete();

    // Hide settings on the test translator.
    $default_translator = Translator::load('test_translator');
    $default_translator
      ->setSetting('expose_settings', FALSE)
      ->save();

    // Create a job but do not save yet, to simulate how this works in the UI.
    $job = tmgmt_job_create('en', 'de', 0, []);

    $jobs = $job_checkout_manager->checkoutMultiple(array($job));
    $this->assertEmpty($jobs);
    $this->assertTrue($job->isActive());

    // A job without target (not specified) language needs to be checked out.
    $job = $this->createJob('en', LanguageInterface::LANGCODE_NOT_SPECIFIED);
    $jobs = $job_checkout_manager->checkoutMultiple(array($job));
    $this->assertEquals($job->id(), $jobs[0]->id());
    $this->assertTrue($job->isUnprocessed());

    // Create a second file translator. This should check
    // out immediately.
    $job = $this->createJob();

    $second_translator = $this->createTranslator();
    $second_translator
      ->setSetting('expose_settings', FALSE)
      ->save();

    $jobs = $job_checkout_manager->checkoutMultiple(array($job));
    $this->assertEquals($job->id(), $jobs[0]->id());
    $this->assertTrue($job->isUnprocessed());
  }

  /**
   * Tests the UI of suggestions.
   */
  public function testSuggestions() {
    // Prepare a job and a node for testing.
    $job = $this->createJob();
    $job->addItem('test_source', 'test', 1);
    $job->addItem('test_source', 'test', 7);

    // Go to checkout form.
    $this->drupalGet($job->toUrl());

    $this->assertSession()->responseContains('20');

    // Verify that suggestions are immediately visible.
    $this->assertSession()->pageTextContains('test_source:test_suggestion:1');
    $this->assertSession()->pageTextContains('test_source:test_suggestion:7');
    $this->assertSession()->pageTextContains('Test suggestion for test source 1');
    $this->assertSession()->pageTextContains('Test suggestion for test source 7');

    // Add the second suggestion.
    $edit = array('suggestions_table[2]' => TRUE);
    $this->submitForm($edit, t('Add suggestions'));

    // Total word count should now include the added job.
    $this->assertSession()->responseContains('31');
    // The suggestion for 7 was added, so there should now be a suggestion
    // for the suggestion instead.
    $this->assertSession()->pageTextNotContains('Test suggestion for test source 7');
    $this->assertSession()->pageTextContains('test_source:test_suggestion_suggestion:7');

    // The HTML test source does not provide suggestions, ensure that the
    // suggestions UI does not show up if there are none.
    $job = $this->createJob();
    $job->addItem('test_html_source', 'test', 1);

    $this->drupalGet($job->toUrl());
    $this->assertSession()->pageTextNotContains('Suggestions');
  }

  /**
   * Test the process of aborting and resubmitting the job.
   */
  function testAbortJob() {
    $job = $this->createJob();
    $job->addItem('test_source', 'test', 1);
    $job->addItem('test_source', 'test', 2);
    $job->addItem('test_source', 'test', 3);

    $this->drupalGet($job->toUrl());
    $edit = array(
      'target_language' => 'es',
      'settings[action]' => 'translate',
    );
    $this->submitForm($edit, t('Submit to provider'));

    // Abort job.
    $this->drupalGet($job->toUrl());
    $this->submitForm([], t('Abort job'));
    $this->submitForm([], t('Confirm'));
    $this->assertSession()->pageTextContains('The user ordered aborting the Job through the UI.');
    $this->assertSession()->addressEquals('admin/tmgmt/jobs/' . $job->id());
    // Reload job and check its state.
    \Drupal::entityTypeManager()->getStorage('tmgmt_job')->resetCache();
    $job = Job::load($job->id());
    $this->assertTrue($job->isAborted());
    foreach ($job->getItems() as $item) {
      $this->assertTrue($item->isAborted());
    }

    // Resubmit the job.
    $this->submitForm([], t('Resubmit'));
    $this->submitForm([], t('Confirm'));
    // Test for the log message.
    $this->assertSession()->responseContains(t('This job is a duplicate of the previously aborted job <a href=":url">#@id</a>',
      array(':url' => $job->toUrl()->toString(), '@id' => $job->id())));

    // Load the resubmitted job and check for its status and values.
    $url_parts = explode('/', $this->getUrl());
    $resubmitted_job = Job::load(array_pop($url_parts));

    $this->assertTrue($resubmitted_job->isUnprocessed());
    $this->assertEquals($job->getTranslator()->id(), $resubmitted_job->getTranslator()->id());
    $this->assertEquals($job->getSourceLangcode(), $resubmitted_job->getSourceLangcode());
    $this->assertEquals($job->getTargetLangcode(), $resubmitted_job->getTargetLangcode());
    $this->assertEquals($job->get('settings')->getValue(), $resubmitted_job->get('settings')->getValue());

    // Test if job items were duplicated correctly.
    foreach ($job->getItems() as $item) {
      // We match job items based on "id #" string. This is not that straight
      // forward, but it works as the test source text is generated as follows:
      // Text for job item with type #type and id #id.
      $_items = $resubmitted_job->getItems(array('data' => array('value' => 'id ' . $item->getItemId(), 'operator' => 'CONTAINS')));
      $_item = reset($_items);
      $this->assertNotEquals($item->getJobId(), $_item->getJobId());
      $this->assertEquals($item->getPlugin(), $_item->getPlugin());
      $this->assertEquals($item->getItemId(), $_item->getItemId());
      $this->assertEquals($item->getItemType(), $_item->getItemType());
      // Make sure counts have been recalculated.
      $this->assertTrue($_item->getWordCount() > 0);
      $this->assertTrue($_item->getCountPending() > 0);
      $this->assertEquals(0, $_item->getCountTranslated());
      $this->assertEquals(0, $_item->getCountAccepted());
      $this->assertEquals(0, $_item->getCountReviewed());
    }

    $this->loginAsAdmin();
    // Navigate back to the aborted job and check for the log message.
    $this->drupalGet('admin/tmgmt/jobs/' . $job->id());

    // Assert that the progress is N/A since the job was aborted.
    $element = $this->xpath('//div[@class="view-content"]/table[@class="views-table views-view-table cols-8"]/tbody//tr[1]/td[4]')[0];
    $this->assertEquals(t('Aborted'), $element->getText());
    $this->assertSession()->responseContains(t('Job has been duplicated as a new job <a href=":url">#@id</a>.',
      array(':url' => $resubmitted_job->toUrl()->toString(), '@id' => $resubmitted_job->id())));
    $this->submitForm([], t('Delete'));
    $this->submitForm([], t('Delete'));
    $this->assertSession()->pageTextContains('The translation job ' . $resubmitted_job->label() . ' has been deleted.');
    $this->drupalGet('admin/tmgmt/jobs/2/delete');
    $this->submitForm([], t('Delete'));
    $this->drupalGet('admin/tmgmt/jobs/');
    $this->assertSession()->pageTextContains('No jobs available.');

    // Create a translator.
    $translator = $this->createTranslator();

    // Create a job and attach to the translator.
    $job = $this->createJob();
    $job->translator = $translator;
    $job->save();
    $job->setState(Job::STATE_ACTIVE);

    // Add item to the job.
    $job->addItem('test_source', 'test', 1);
    $this->drupalGet('admin/tmgmt/jobs');

    // Try to abort the job and save.
    $this->clickLink(t('Manage'));
    $this->submitForm([], t('Abort job'));
    $this->submitForm([], t('Confirm'));

    // Go back to the job page.
    $this->drupalGet('admin/tmgmt/jobs', array('query' => array(
      'state' => JobInterface::STATE_ABORTED,
    )));

    // Check that job is aborted now.
    $this->assertJobStateIcon(1, 'Aborted');


    // Ensure that a job can still be viewed when the target language was
    // deleted.
    ConfigurableLanguage::load('de')->delete();
    $this->drupalGet('admin/tmgmt/jobs/' . $job->id());
  }

  /**
   * Test the cart functionality.
   */
  function testCart() {

    $this->addLanguage('fr');
    $job_items = array();
    // Create a few job items and add them to the cart.
    for ($i = 1; $i < 6; $i++) {
      $job_item = tmgmt_job_item_create('test_source', 'test', $i);
      $job_item->save();
      $job_items[$i] = $job_item;
    }

    $this->loginAsTranslator();
    foreach ($job_items as $job_item) {
      $this->drupalGet('tmgmt-add-to-cart/' . $job_item->id());
    }

    // Check if the items are displayed in the cart.
    $this->drupalGet('admin/tmgmt/cart');
    foreach ($job_items as $job_item) {
      $this->assertSession()->pageTextContains($job_item->label());
    }

    // Test the remove items from cart functionality.
    $this->submitForm([
      'items[1]' => TRUE,
      'items[2]' => FALSE,
      'items[3]' => FALSE,
      'items[4]' => TRUE,
      'items[5]' => FALSE,
    ], t('Remove selected item'));
    $this->assertSession()->pageTextContains($job_items[2]->label());
    $this->assertSession()->pageTextContains($job_items[3]->label());
    $this->assertSession()->pageTextContains($job_items[5]->label());
    $this->assertSession()->pageTextNotContains($job_items[1]->label());
    $this->assertSession()->pageTextNotContains($job_items[4]->label());
    $this->assertSession()->pageTextContains(t('Job items were removed from the cart.'));

    // Test that removed job items from cart were deleted as well.
    $existing_items = JobItem::loadMultiple();
    $this->assertTrue(!isset($existing_items[$job_items[1]->id()]));
    $this->assertTrue(!isset($existing_items[$job_items[4]->id()]));


    $this->submitForm([], t('Empty cart'));
    $this->assertSession()->pageTextNotContains($job_items[2]->label());
    $this->assertSession()->pageTextNotContains($job_items[3]->label());
    $this->assertSession()->pageTextNotContains($job_items[5]->label());
    $this->assertSession()->pageTextContains(t('All job items were removed from the cart.'));

    // No remaining job items.
    $existing_items = JobItem::loadMultiple();
    $this->assertTrue(empty($existing_items));

    $language_sequence = array('en', 'en', 'fr', 'fr', 'de', 'de');
    for ($i = 1; $i < 7; $i++) {
      $job_item = tmgmt_job_item_create('test_source', 'test', $i);
      $job_item->save();
      $job_items[$i] = $job_item;
      $languages[$job_items[$i]->id()] = $language_sequence[$i - 1];
    }
    \Drupal::state()->set('tmgmt.test_source_languages', $languages);
    foreach ($job_items as $job_item) {
      $this->drupalGet('tmgmt-add-to-cart/' . $job_item->id());
    }

    $this->drupalGet('admin/tmgmt/cart');
    $this->submitForm([
      'items[' . $job_items[1]->id() . ']' => TRUE,
      'items[' . $job_items[2]->id() . ']' => TRUE,
      'items[' . $job_items[3]->id() . ']' => TRUE,
      'items[' . $job_items[4]->id() . ']' => TRUE,
      'items[' . $job_items[5]->id() . ']' => TRUE,
      'items[' . $job_items[6]->id() . ']' => FALSE,
      'target_language[]' => ['en', 'de'],
    ], t('Request translation'));

    $this->assertSession()->pageTextContains(t('@count jobs need to be checked out.', array('@count' => 4)));

    // We should have four jobs with following language combinations:
    // [fr, fr] => [en]
    // [de] => [en]
    // [en, en] => [de]
    // [fr, fr] => [de]

    $storage = \Drupal::entityTypeManager()->getStorage('tmgmt_job');
    $jobs = $storage->loadByProperties(['source_language' => 'fr', 'target_language' => 'en']);
    $job = reset($jobs);
    $this->assertCount(2, $job->getItems());

    $jobs = $storage->loadByProperties(['source_language' => 'de', 'target_language' => 'en']);
    $job = reset($jobs);
    $this->assertCount(1, $job->getItems());

    $jobs = $storage->loadByProperties(['source_language' => 'en', 'target_language' => 'de']);
    $job = reset($jobs);
    $this->assertCount(2, $job->getItems());

    $jobs = $storage->loadByProperties(['source_language' => 'fr', 'target_language' => 'de']);
    $job = reset($jobs);
    $this->assertCount(2, $job->getItems());

    $this->drupalGet('admin/tmgmt/cart');
    // Both fr and one de items must be gone.
    $this->assertSession()->pageTextNotContains($job_items[1]->label());
    $this->assertSession()->pageTextNotContains($job_items[2]->label());
    $this->assertSession()->pageTextNotContains($job_items[3]->label());
    $this->assertSession()->pageTextNotContains($job_items[4]->label());
    $this->assertSession()->pageTextNotContains($job_items[5]->label());
    // One de item is in the cart as it was not selected for checkout.
    $this->assertSession()->pageTextContains($job_items[6]->label());

    // Check to see if no items are selected and the error message pops up.
    $this->drupalGet('admin/tmgmt/cart');
    $this->submitForm(['items[' . $job_items[6]->id() . ']' => FALSE], t('Request translation'));
    $this->assertSession()->pageTextContainsOnce(t("You didn't select any source items."));
  }

  /**
   * Test titles of various TMGMT pages.
   *
   * @todo Miro wants to split this test to specific tests (check)
   */
  function testPageTitles() {
    $this->loginAsAdmin();
    $translator = $this->createTranslator();
    $job = $this->createJob();
    $job->translator = $translator;
    $job->settings = array();
    $job->save();
    $item = $job->addItem('test_source', 'test', 1);

    // Tmgtm settings.
    $this->drupalGet('/admin/tmgmt/settings');
    $this->assertSession()->titleEquals('Settings | Drupal');
    // Manage translators.
    $this->drupalGet('/admin/tmgmt/translators');
    $this->assertSession()->titleEquals('Providers | Drupal');
    // Add Translator.
    $this->drupalGet('/admin/tmgmt/translators/add');
    $this->assertSession()->titleEquals('Add Provider | Drupal');
    // Delete Translators.
    $this->drupalGet('/admin/tmgmt/translators/manage/' . $translator->id() . '/delete');
    $this->assertSession()->titleEquals((string) t('Are you sure you want to delete the provider @label? | Drupal', ['@label' => $translator->label()]));
    // Edit Translators.
    $this->drupalGet('/admin/tmgmt/translators/manage/' . $translator->id());
    $this->assertSession()->titleEquals('Edit provider | Drupal');
    // Delete Job.
    $this->drupalGet('/admin/tmgmt/jobs/' . $job->id() . '/delete');
    $this->assertSession()->titleEquals((string) t('Are you sure you want to delete the translation job @label? | Drupal', ['@label' => $job->label()]));
    // Resubmit Job.
    $this->drupalGet('/admin/tmgmt/jobs/' . $job->id() . '/resubmit');
    $this->assertSession()->titleEquals('Resubmit as a new job? | Drupal');
    // Abort Job.
    $this->drupalGet('/admin/tmgmt/jobs/' . $job->id() . '/abort');
    $this->assertSession()->titleEquals('Abort this job? | Drupal');
    // Edit Job Item.
    $this->drupalGet('/admin/tmgmt/items/' . $job->id());
    $this->assertSession()->titleEquals((string) t('Job item @label | Drupal', ['@label' => $item->label()]));
    // Assert the breadcrumb.
    $this->assertSession()->linkExists(t('Home'));
    $this->assertSession()->linkExists(t('Administration'));
    $this->assertSession()->linkExists(t('Job overview'));
    $this->assertSession()->linkExists($job->label());
    // Translation Sources.
    $this->drupalGet('admin');
    $this->clickLink(t('Translation'));
    $this->assertSession()->titleEquals('Translation | Drupal');
    $this->clickLink(t('Cart'));
    $this->assertSession()->titleEquals('Cart | Drupal');
    $this->clickLink(t('Jobs'));
    $this->assertSession()->titleEquals('Job overview | Drupal');
    $this->clickLink(t('Sources'));
    $this->assertSession()->titleEquals('Translation Sources | Drupal');
  }

  /**
   * Test the deletion and abortion of job item.
   *
   * @todo There will be some overlap with Aborting items & testAbortJob.
   */
  function testJobItemDelete() {
    $this->loginAsAdmin();

    // Create a translator.
    $translator = $this->createTranslator();
    // Create a job and attach to the translator.
    $job = $this->createJob();
    $job->translator = $translator;
    $job->settings = array();
    $job->save();
    $job->setState(Job::STATE_ACTIVE);

    // Add item to the job.
    $item = $job->addItem('test_source', 'test', 1);
    $item->setState(JobItem::STATE_ACTIVE);

    // Check that there is no delete link on item review form.
    $this->drupalGet('admin/tmgmt/items/' . $item->id());
    $this->assertSession()->fieldNotExists('edit-delete');

    $this->drupalGet('admin/tmgmt/jobs/' . $job->id());

    // Check that there is no delete link.
    $this->assertSession()->linkNotExists('Delete');

    // Check for abort link.
    $this->assertSession()->linkExists('Abort');

    $this->clickLink('Abort');
    $this->assertSession()->pageTextContains(t('Are you sure you want to abort the job item test_source:test:1?'));

    // Check if cancel button is present or not.
    $this->assertSession()->linkExists('Cancel');

    // Abort the job item.
    $this->submitForm([], t('Confirm'));

    // Reload job and check its state and state of its item.
    \Drupal::entityTypeManager()->getStorage('tmgmt_job')->resetCache();
    $job = Job::load($job->id());
    $this->assertTrue($job->isFinished());
    $items = $job->getItems();
    $item = reset($items);
    $this->assertTrue($item->isAborted());

    // Check that there is no delete button on item review form.
    $this->drupalGet('admin/tmgmt/items/' . $item->id());
    $this->assertSession()->fieldNotExists('edit-delete');

    // Check that there is no accept button on item review form.
    $this->assertSession()->fieldNotExists('edit-accept');

    $this->drupalGet('admin/tmgmt/jobs/' . $job->id());

    // Check that there is no delete link on job overview.
    $this->assertSession()->linkNotExists('Delete');
  }

  /**
   * Test the settings of TMGMT.
   *
   * @todo some settings have no test coverage in their effect.
   * @todo we will need to switch them in context of the other lifecycle tests.
   */
  public function testSettings() {
    $this->loginAsAdmin();

    $settings = \Drupal::config('tmgmt.settings');
    $this->assertTrue($settings->get('quick_checkout'));
    $this->assertTrue($settings->get('anonymous_access'));
    $this->assertEquals('_never', $settings->get('purge_finished'));
    $this->assertTrue($settings->get('word_count_exclude_tags'));
    $this->assertEquals(20, $settings->get('source_list_limit'));
    $this->assertEquals(50, $settings->get('job_items_cron_limit'));
    $this->assertTrue($settings->get('respect_text_format'));
    $this->assertFalse($settings->get('submit_job_item_on_cron'));

    $this->drupalGet('admin/tmgmt/settings');
    $edit = [
      'tmgmt_quick_checkout' => FALSE,
      'tmgmt_anonymous_access' => FALSE,
      'tmgmt_purge_finished' => 0,
      'respect_text_format' => FALSE,
      'tmgmt_submit_job_item_on_cron' => TRUE,
    ];
    $this->submitForm($edit, t('Save configuration'));

    $settings = \Drupal::config('tmgmt.settings');
    $this->assertFalse($settings->get('quick_checkout'));
    $this->assertFalse($settings->get('anonymous_access'));
    $this->assertEquals(0, $settings->get('purge_finished'));
    $this->assertFalse($settings->get('respect_text_format'));
    $this->assertTrue($settings->get('submit_job_item_on_cron'));
  }

  /**
   * Tests of the job item review process.
   */
  public function testProgress() {
    // Test that there are no jobs at the beginning.
    $this->drupalGet('admin/tmgmt/jobs');
    $this->assertSession()->pageTextContains('No jobs available.');
    $this->assertSession()->optionExists('edit-state', 'Items - In progress');
    $this->assertSession()->optionExists('edit-state', 'Items - Needs review');
    $this->assertSession()->optionExists('edit-state', 'Items - Translation is requested from the elders of the Internet');

    // Make sure the legend label is displayed for the test translator state.
    $this->assertSession()->pageTextContains('Translation is requested from the elders of the Internet');
    $this->drupalGet('admin/tmgmt/sources');

    // Create Jobs.
    $job1 = $this->createJob();
    $job1->save();
    $job1->setState(Job::STATE_UNPROCESSED);

    $job2 = $this->createJob();
    $job2->save();
    $job2->setState(Job::STATE_ACTIVE);

    $job3 = $this->createJob();
    $job3->save();
    $job3->setState(Job::STATE_REJECTED);

    $job4 = $this->createJob();
    $job4->save();
    $job4->setState(Job::STATE_ABORTED);

    $job5 = $this->createJob();
    $job5->save();
    $job5->setState(Job::STATE_FINISHED);

    // Test their icons.
    $this->drupalGet('admin/tmgmt/jobs', array('query' => array(
      'state' => 'All',
    )));
    $this->assertCount(5, $this->xpath('//tbody/tr'));
    $this->assertJobStateIcon(1, 'Unprocessed');
    $this->assertJobStateIcon(2, 'In progress');
    $this->assertJobStateIcon(3, 'Rejected');
    $this->assertJobStateIcon(4, 'Aborted');
    $this->assertJobStateIcon(5, 'Finished');

    // Test the row amount for each state selected.
    $this->drupalGet('admin/tmgmt/jobs', ['query' => ['state' => 'open_jobs']]);
    $this->assertCount(3, $this->xpath('//tbody/tr'));

    $this->drupalGet('admin/tmgmt/jobs', ['query' => ['state' => JobInterface::STATE_UNPROCESSED]]);
    $this->assertCount(1, $this->xpath('//tbody/tr'));

    $this->drupalGet('admin/tmgmt/jobs', ['query' => ['state' => JobInterface::STATE_REJECTED]]);
    $this->assertCount(1, $this->xpath('//tbody/tr'));

    $this->drupalGet('admin/tmgmt/jobs', array('query' => array('state' => JobInterface::STATE_ABORTED)));
    $this->assertCount(1, $this->xpath('//tbody/tr'));

    $this->drupalGet('admin/tmgmt/jobs', array('query' => array('state' => JobInterface::STATE_FINISHED)));
    $this->assertCount(1, $this->xpath('//tbody/tr'));

    \Drupal::state()->set('tmgmt.test_source_data', array(
      'title' => array(
        'deep_nesting' => array(
          '#text' => '<p><em><strong>Six dummy HTML tags in the title.</strong></em></p>',
          '#label' => 'Title',
        ),
      ),
      'body' => array(
        'deep_nesting' => array(
          '#text' => '<p>Two dummy HTML tags in the body.</p>',
          '#label' => 'Body',
        )
      ),
    ));

    // Add 2 items to job1 and submit it to provider.
    $item1 = $job1->addItem('test_source', 'test', 1);
    $job1->addItem('test_source', 'test', 2);
    $this->drupalGet('admin/tmgmt/job_items', array('query' => array('state' => 'All')));
    $this->assertCount(2, $this->xpath('//tbody/tr'));
    $this->assertJobItemOverviewStateIcon(1, 'Inactive');
    $this->assertSession()->linkExists($job1->label());
    $this->drupalGet($job1->toUrl());
    $edit = array(
      'target_language' => 'de',
      'settings[action]' => 'submit',
    );
    $this->submitForm($edit, t('Submit to provider'));

    // Translate body of one item.
    $this->drupalGet('admin/tmgmt/items/' . $item1->id());
    $this->submitForm(['body|deep_nesting[translation]' => 'translation'], t('Save'));
    // Check job item state is still in progress.
    $this->assertJobItemStateIcon(1, 'In progress');
    $this->drupalGet('admin/tmgmt/job_items', array('query' => array('state' => JobItemInterface::STATE_ACTIVE)));
    $this->assertCount(2, $this->xpath('//tbody/tr'));
    $this->assertJobItemOverviewStateIcon(1, 'In progress');
    $this->drupalGet('admin/tmgmt/jobs', ['query' => ['state' => 'job_item_' . JobItemInterface::STATE_ACTIVE]]);
    // Check progress bar and icon.
    $this->assertJobProgress(1, 3, 1, 0, 0);
    $this->assertJobStateIcon(1, 'In progress');

    // Set the translator status to tmgmt_test_generating.
    \Drupal::entityTypeManager()->getStorage('tmgmt_job_item')->resetCache();
    $item1 = JobItem::load($item1->id());
    $item1->setTranslatorState('tmgmt_test_generating');
    $item1->save();

    $this->drupalGet('admin/tmgmt/job_items', array('query' => array('state' => 'tmgmt_test_generating')));
    $this->assertCount(1, $this->xpath('//tbody/tr'));
    $this->assertJobItemOverviewStateIcon(1, 'Translation is requested from the elders of the Internet');
    $this->assertSession()->responseContains('earth.svg"');
    $this->drupalGet('admin/tmgmt/jobs', ['query' => ['state' => 'job_item_' . JobItemInterface::STATE_ACTIVE]]);
    $this->assertJobProgress(1, 3, 1, 0, 0);
    $this->assertJobStateIcon(1, 'Translation is requested from the elders of the Internet');
    $this->assertSession()->responseContains('earth.svg"');
    // Also check the translator state.
    $this->drupalGet('admin/tmgmt/jobs', ['query' => ['state' => 'job_item_tmgmt_test_generating']]);
    $this->assertJobProgress(1, 3, 1, 0, 0);
    $this->assertJobStateIcon(1, 'Translation is requested from the elders of the Internet');
    $this->assertSession()->responseContains('earth.svg"');


    // Translate title of one item.
    $this->drupalGet('admin/tmgmt/items/' . $item1->id());
    $this->submitForm(['title|deep_nesting[translation]' => 'translation'], t('Save'));
    // Check job item state changed to needs review.
    $this->assertJobItemStateIcon(1, 'Needs review');
    $this->drupalGet('admin/tmgmt/job_items', array('query' => array('state' => JobItemInterface::STATE_REVIEW)));
    $this->assertCount(1, $this->xpath('//tbody/tr'));
    $this->assertJobItemOverviewStateIcon(1, 'Needs review');

    // Check exposed filter for needs review.
    $this->drupalGet('admin/tmgmt/jobs', ['query' => ['state' => 'job_item_' . JobItemInterface::STATE_REVIEW]]);
    $this->assertCount(1, $this->xpath('//tbody/tr'));
    // Check progress bar and icon.
    $this->assertJobProgress(1, 2, 2, 0, 0);
    $this->assertJobStateIcon(1, 'Needs review');

    // Review the translation one by one.
    $page = $this->getSession()->getPage();
    $this->drupalGet('admin/tmgmt/items/' . $item1->id());
    $page->pressButton('reviewed-body|deep_nesting');
    $this->drupalGet('admin/tmgmt/jobs/' . $job1->id());
    // Check the icon of the job item.
    $this->assertJobItemStateIcon(1, 'Needs review');

    $this->drupalGet('admin/tmgmt/items/' . $item1->id());;
    $page->pressButton('reviewed-title|deep_nesting');
    $this->drupalGet('admin/tmgmt/jobs/' . $job1->id());
    // Check the icon of the job item.
    $this->assertJobItemStateIcon(1, 'Needs review');
    $this->drupalGet('admin/tmgmt/jobs', array('query' => array('state' => 'open_jobs')));
    // Check progress bar and icon.
    $this->assertJobProgress(1, 2, 0, 2, 0);
    $this->assertJobStateIcon(1, 'Needs review');

    // Save one job item as completed.
    $this->drupalGet($item1->toUrl());
    $this->submitForm([], t('Save as completed'));
    // Check job item state changed to accepted.
    $this->assertJobItemStateIcon(1, 'Accepted');
    $this->drupalGet('admin/tmgmt/job_items', array('query' => array('state' => JobItemInterface::STATE_ACCEPTED)));
    $this->assertCount(1, $this->xpath('//tbody/tr'));
    $this->assertJobItemOverviewStateIcon(1, 'Accepted');
    $this->drupalGet('admin/tmgmt/jobs', array('query' => array('state' => 'open_jobs')));
    // Check progress bar and icon.
    $this->assertJobProgress(1, 2, 0, 0, 2);
    $this->assertJobStateIcon(1, 'In progress');

    // Assert the legend.
    $this->drupalGet('admin/tmgmt/items/' . $item1->id());
    $this->assertSession()->responseContains('class="tmgmt-color-legend');
  }

  /**
   * Asserts task item progress bar.
   *
   * @param int $row
   *   The row of the item you want to check.
   * @param int $state
   *   The expected state.
   */
  private function assertJobStateIcon($row, $state) {
    if ($state == 'Unprocessed' || $state == 'Rejected' || $state == 'Aborted' || $state == 'Finished') {
      $result = $this->xpath('//table/tbody/tr[' . $row . ']/td[6]')[0];
      $this->assertEquals($state, trim($result->getHtml()));
    }
    else {
      $result = $this->xpath('//table/tbody/tr[' . $row . ']/td[1]/img')[0];
      $this->assertEquals($state, $result->getAttribute('title'));
    }
  }

  /**
   * Asserts task item progress bar.
   *
   * @param int $row
   *   The row of the item you want to check.
   * @param int $state
   *   The expected state.
   *
   */
  protected function assertJobItemStateIcon($row, $state) {
    if ($state == 'Inactive' || $state == 'Aborted' || $state == 'Accepted') {
      $result = $this->xpath('//div[@id="edit-job-items-wrapper"]//tbody/tr[' . $row . ']/td[4]')[0];
      $this->assertEquals($state, trim($result->getHtml()));
    }
    else {
      $result = $this->xpath('//div[@id="edit-job-items-wrapper"]//tbody/tr[' . $row . ']/td[1]/img')[0];
      $this->assertEquals($state, $result->getAttribute('title'));
    }
  }

  /**
   * Asserts job item overview progress bar.
   *
   * @param int $row
   *   The row of the item you want to check.
   * @param int $state
   *   The expected state.
   *
   */
  private function assertJobItemOverviewStateIcon($row, $state) {
    if ($state == 'Inactive' || $state == 'Aborted' || $state == 'Accepted') {
      $result = $this->xpath('//table/tbody/tr[' . $row . ']/td[7]')[0];
      $this->assertEquals($state, trim($result->getHtml()));
    }
    else {
      $result = $this->xpath('//table/tbody/tr[' . $row . ']/td[1]/img')[0];
      $this->assertEquals($state, $result->getAttribute('title'));
    }
  }


  /**
   * Asserts task item progress bar.
   *
   * @param int $row
   *   The row of the item you want to check.
   * @param int $pending
   *   The amount of pending items.
   * @param int $reviewed
   *   The amount of reviewed items.
   * @param int $translated
   *   The amount of translated items.
   * @param int $accepted
   *   The amount of accepted items.
   *
   */
  private function assertJobProgress($row, $pending, $translated, $reviewed, $accepted) {
    $result = $this->xpath('//table/tbody/tr[' . $row . ']/td[6]')[0];
    $div_number = 1;
    if ($pending > 0) {
      $this->assertEquals('tmgmt-progress tmgmt-progress-pending', $result->find('css', "div > div:nth-child($div_number)")->getAttribute('class'));
      $div_number++;
    }
    else {
      $this->assertNotEquals('tmgmt-progress tmgmt-progress-pending', $result->find('css', "div > div:nth-child($div_number)")->getAttribute('class'));
    }
    if ($translated > 0) {
      $this->assertEquals('tmgmt-progress tmgmt-progress-translated', $result->find('css', "div > div:nth-child($div_number)")->getAttribute('class'));
      $div_number++;
    }
    else {
      $child = $result->find('css', "div > div:nth-child($div_number)");
      $this->assertTrue(!$child || $child->getAttribute('class') != 'tmgmt-progress tmgmt-progress-translated');
    }
    if ($reviewed > 0) {
      $this->assertEquals('tmgmt-progress tmgmt-progress-reviewed', $result->find('css', "div > div:nth-child($div_number)")->getAttribute('class'));
      $div_number++;
    }
    else {
      $child = $result->find('css', "div > div:nth-child($div_number)");
      $this->assertTrue(!$child || $child->getAttribute('class') != 'tmgmt-progress tmgmt-progress-reviewed');
    }
    if ($accepted > 0) {
      $this->assertEquals('tmgmt-progress tmgmt-progress-accepted', $result->find('css', "div > div:nth-child($div_number)")->getAttribute('class'));
    }
    else {
      $child = $result->find('css', "div > div:nth-child($div_number)");
      $this->assertTrue(!$child || $child->getAttribute('class') != 'tmgmt-progress tmgmt-progress-accepted');
    }
    $title = t('Pending: @pending, translated: @translated, reviewed: @reviewed, accepted: @accepted.', array(
      '@pending' => $pending,
      '@translated' => $translated,
      '@reviewed' => $reviewed,
      '@accepted' => $accepted,
    ));
    $this->assertEquals($title, $result->find('css', 'div')->getAttribute('title'));
  }

}
