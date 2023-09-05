<?php

namespace Drupal\Tests\tmgmt\Kernel;

use Drupal\tmgmt\ContinuousTranslatorInterface;
use Drupal\tmgmt\Entity\Job;
use Drupal\tmgmt\Entity\JobItem;
use Drupal\tmgmt\Entity\RemoteMapping;
use Drupal\tmgmt\Entity\Translator;
use Drupal\tmgmt\JobInterface;
use Drupal\tmgmt\JobItemInterface;

/**
 * Basic crud operations for jobs and translators
 *
 * @group tmgmt
 */
class CrudTest extends TMGMTKernelTestBase {

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();
    \Drupal::service('router.builder')->rebuild();
    $this->installEntitySchema('tmgmt_remote');
  }

  /**
   * Test crud operations of translators.
   */
  function testTranslators() {
    $translator = $this->createTranslator();

    $loaded_translator = Translator::load($translator->id());
    $this->assertEquals($translator->id(), $loaded_translator->id());
    $this->assertEquals($translator->label(), $loaded_translator->label());
    $this->assertEquals($translator->getSettings(), $loaded_translator->getSettings());

    // Update the settings.
    $translator->setSetting('new_key', $this->randomString());
    $translator->save();

    $loaded_translator = Translator::load($translator->id());
    $this->assertEquals($translator->id(), $loaded_translator->id());
    $this->assertEquals($translator->label(), $loaded_translator->label());
    $this->assertEquals($translator->getSettings(), $loaded_translator->getSettings());

    // Delete the translator, make sure the translator is gone.
    $translator->delete();
    $this->assertNull(Translator::load($translator->id()));
  }

  /**
   * Tests job item states for 'reject' / 'submit' settings action job states.
   */
  public function testRejectedJob() {
    $job = $this->createJob();

    // Change job state to 'reject' through the API and request a translation.
    $job->translator = $this->default_translator->id();
    $job->settings->action = 'reject';
    $job->save();
    $job_item = $job->addItem('test_source', 'type', 1);
    $job->requestTranslation();

    // Check that job is rejected and job item is NOT active.
    $job = \Drupal::entityTypeManager()->getStorage('tmgmt_job')->loadUnchanged($job->id());
    $this->assertTrue($job->isRejected());
    $job_item = \Drupal::entityTypeManager()->getStorage('tmgmt_job_item')->loadUnchanged($job_item->id());
    $this->assertTrue($job_item->isInactive());

    // Change job state to 'submit' through the API and request a translation.
    $job->settings->action = 'submit';
    $job->save();
    $job->requestTranslation();

    // Check that job is active and job item IS active.
    $this->assertTrue($job->isActive());
    $this->assertTrue($job_item->isActive());
  }

  /**
   * Test crud operations of jobs.
   */
  function testJobs() {
    $job = $this->createJob();

    $this->assertEquals(Job::TYPE_NORMAL, $job->getJobType());

    $loaded_job = Job::load($job->id());

    $this->assertEquals($job->getSourceLangcode(), $loaded_job->getSourceLangcode());
    $this->assertEquals($job->getTargetLangcode(), $loaded_job->getTargetLangcode());

    // Assert that the created and changed information has been set to the
    // default value.
    $this->assertTrue($loaded_job->getCreatedTime() > 0);
    $this->assertTrue($loaded_job->getChangedTime() > 0);
    $this->assertEquals(0, $loaded_job->getState());

    // Update the settings.
    $job->reference = 7;
    $this->assertEquals(SAVED_UPDATED, $job->save());

    $loaded_job = Job::load($job->id());

    $this->assertEquals($job->getReference(), $loaded_job->getReference());

    // Test the job items.
    $item1 = $job->addItem('test_source', 'type', 5);
    $item2 = $job->addItem('test_source', 'type', 4);

    // Load and compare the items.
    $items = $job->getItems();
    $this->assertCount(2, $items);

    $this->assertEquals($item1->getPlugin(), $items[$item1->id()]->getPlugin());
    $this->assertEquals($item1->getItemType(), $items[$item1->id()]->getItemType());
    $this->assertEquals($item1->getItemId(), $items[$item1->id()]->getItemId());
    $this->assertEquals($item2->getPlugin(), $items[$item2->id()]->getPlugin());
    $this->assertEquals($item2->getItemType(), $items[$item2->id()]->getItemType());
    $this->assertEquals($item2->getItemId(), $items[$item2->id()]->getItemId());

    // Delete the job and make sure it is gone.
    $job->delete();
    $this->assertEmpty(Job::load($job->id()));
  }

  function testRemoteMappings() {

    $data_key = '5][test_source][type';

    $translator = $this->createTranslator();
    $job = $this->createJob();
    $job->translator = $translator->id();
    $job->save();
    $item1 = $job->addItem('test_source', 'type', 5);
    $item2 = $job->addItem('test_source', 'type', 4);

    $mapping_data = array(
      'remote_identifier_2' => 'id12',
      'remote_identifier_3' => 'id13',
      'amount' => 1043,
      'currency' => 'EUR',
    );

    $result = $item1->addRemoteMapping($data_key, 'id11', $mapping_data);
    $this->assertEquals(SAVED_NEW, $result);

    $job_mappings = $job->getRemoteMappings();
    $item_mappings = $item1->getRemoteMappings();

    $job_mapping = array_shift($job_mappings);
    $item_mapping = array_shift($item_mappings);

    $_job = $job_mapping->getJob();
    $this->assertEquals($job->id(), $_job->id());

    $_job = $item_mapping->getJob();
    $this->assertEquals($job->id(), $_job->id());

    $_item1 = $item_mapping->getJobItem();
    $this->assertEquals($item1->id(), $_item1->id());

    $remote_mappings = RemoteMapping::loadByRemoteIdentifier('id11', 'id12', 'id13');
    $remote_mapping = array_shift($remote_mappings);
    $this->assertEquals($item1->id(), $remote_mapping->id());
    $this->assertEquals($mapping_data['amount'], $remote_mapping->getAmount());
    $this->assertEquals($mapping_data['currency'], $remote_mapping->getCurrency());

    $this->assertCount(1, RemoteMapping::loadByRemoteIdentifier('id11'));
    $this->assertCount(0, RemoteMapping::loadByRemoteIdentifier('id11', ''));
    $this->assertCount(0, RemoteMapping::loadByRemoteIdentifier('id11', NULL, ''));
    $this->assertCount(1, RemoteMapping::loadByRemoteIdentifier(NULL, NULL, 'id13'));

    $this->assertEquals('id11', $remote_mapping->getRemoteIdentifier1());
    $this->assertEquals('id12', $remote_mapping->getRemoteIdentifier2());
    $this->assertEquals('id13', $remote_mapping->getRemoteIdentifier3());

    // Test remote data.
    $item_mapping->addRemoteData('test_data', 'test_value');
    $item_mapping->save();
    $item_mapping = RemoteMapping::load($item_mapping->id());
    $this->assertEquals('test_value', $item_mapping->getRemoteData('test_data'));

    // Add mapping to the other job item as well.
    $item2->addRemoteMapping($data_key, 'id21', array('remote_identifier_2' => 'id22', 'remote_identifier_3' => 'id23'));

    // Test deleting.

    // Delete item1.
    $item1->delete();
    // Test if mapping for item1 has been removed as well.

    $this->assertCount(0, RemoteMapping::loadByLocalData(NULL, $item1->id()));

    // We still should have mapping for item2.
    $this->assertCount(1, RemoteMapping::loadByLocalData(NULL, $item2->id()));

    // Now delete the job and see if remaining mappings were removed as well.
    $job->delete();
    $this->assertCount(0, RemoteMapping::loadByLocalData(NULL, $item2->id()));
  }

  /**
   * Test crud operations of job items.
   */
  function testJobItems() {
    $job = $this->createJob();

    // Add some test items.
    $item1 = $job->addItem('test_source', 'type', 5);
    $item2 = $job->addItem('test_source', 'test_with_long_label', 4);

    // Test single load callback.
    $item = JobItem::load($item1->id());
    $this->assertEquals($item1->getPlugin(), $item->getPlugin());
    $this->assertEquals($item1->getItemType(), $item->getItemType());
    $this->assertEquals($item1->getItemId(), $item->getItemId());

    // Test multiple load callback.
    $items = JobItem::loadMultiple(array($item1->id(), $item2->id()));

    $this->assertCount(2, $items);

    $this->assertEquals($item1->getPlugin(), $items[$item1->id()]->getPlugin());
    $this->assertEquals($item1->getItemType(), $items[$item1->id()]->getItemType());
    $this->assertEquals($item1->getItemId(), $items[$item1->id()]->getItemId());
    $this->assertEquals($item2->getPlugin(), $items[$item2->id()]->getPlugin());
    $this->assertEquals($item2->getItemType(), $items[$item2->id()]->getItemType());
    $this->assertEquals($item2->getItemId(), $items[$item2->id()]->getItemId());
    // Test the second item label length - it must not exceed the
    // TMGMT_JOB_LABEL_MAX_LENGTH.
    $this->assertTrue(Job::LABEL_MAX_LENGTH >= strlen($items[$item2->id()]->label()));

    $translator = Translator::load('test_translator');
    $translator->setAutoAccept(TRUE)->save();
    $job = tmgmt_job_create('en', 'de');
    $job->translator = 'test_translator';
    $job->save();
    $job_item = tmgmt_job_item_create('test_source', 'test_type', 1, ['tjid' => $job->id()]);
    $job_item->save();
    // Add translated data to the job item.
    $translation['dummy']['deep_nesting']['#text'] = 'Invalid translation that will cause an exception';
    $job_item->addTranslatedData($translation);
    // If it was set to Auto Accept but there was an error, the Job Item should
    // be set as Needs Review.
    $this->assertEquals(JobItemInterface::STATE_REVIEW, $job_item->getState());
    // There should be a message if auto accept has failed.
    $messages = $job->getMessages();
    $last_message = end($messages);
    $this->assertEquals('Failed to automatically accept translation, error: The translation cannot be saved.', $last_message->getMessage());
  }

  /**
   * Tests adding translated data and revision handling.
   */
  function testAddingTranslatedData() {
    $translator = $this->createTranslator();
    $job = $this->createJob();
    $job->translator = $translator->id();
    $job->save();

    // Add some test items.
    $item1 = $job->addItem('test_source', 'test_with_long_label', 5);
    // Test the job label - it must not exceed the TMGMT_JOB_LABEL_MAX_LENGTH.
    $this->assertTrue(Job::LABEL_MAX_LENGTH >= strlen($job->label()));

    $key = array('dummy', 'deep_nesting');

    $translation['dummy']['deep_nesting']['#text'] = 'translated 1';
    $item1->addTranslatedData($translation);
    $data = $item1->getData($key);

    // Check job messages.
    $messages = $job->getMessages();
    $this->assertCount(1, $messages);
    $last_message = end($messages);
    $this->assertEquals('The translation of <a href=":source_url">@source</a> to @language is finished and can now be <a href=":review_url">reviewed</a>.', $last_message->message->value);

    // Initial state - translation has been received for the first time.
    $this->assertEquals('translated 1', $data['#translation']['#text']);
    $this->assertTrue(empty($data['#translation']['#text_revisions']));
    $this->assertEquals('remote', $data['#translation']['#origin']);
    $this->assertEquals(\Drupal::time()->getRequestTime(), $data['#translation']['#timestamp']);

    // Set status back to pending as if the data item was rejected.
    $item1->updateData(array('dummy', 'deep_nesting'), array('#status' => TMGMT_DATA_ITEM_STATE_PENDING));
    // Add same translation text.
    $translation['dummy']['deep_nesting']['#text'] = 'translated 1';
    $item1->addTranslatedData($translation);
    $data = $item1->getData($key);
    // Check if the status has been updated back to translated.
    $this->assertEquals(TMGMT_DATA_ITEM_STATE_TRANSLATED, $data['#status']);

    // Add translation, however locally customized.
    $translation['dummy']['deep_nesting']['#text'] = 'translated 2';
    $translation['dummy']['deep_nesting']['#origin'] = 'local';
    $translation['dummy']['deep_nesting']['#timestamp'] = \Drupal::time()->getRequestTime() - 5;
    $item1->addTranslatedData($translation);
    $data = $item1->getData($key);

    // The translation text is updated.
    $this->assertEquals('translated 2', $data['#translation']['#text']);
    $this->assertEquals(\Drupal::time()->getRequestTime() - 5, $data['#translation']['#timestamp']);

    // Previous translation is among text_revisions.
    $this->assertEquals('translated 1', $data['#translation']['#text_revisions'][0]['#text']);
    $this->assertEquals('remote', $data['#translation']['#text_revisions'][0]['#origin']);
    $this->assertEquals(\Drupal::time()->getRequestTime(), $data['#translation']['#text_revisions'][0]['#timestamp']);
    // Current translation origin is local.
    $this->assertEquals('local', $data['#translation']['#origin']);

    // Check job messages.
    $messages = $job->getMessages();
    $this->assertCount(1, $messages);

    // Add translation - not local.
    $translation['dummy']['deep_nesting']['#text'] = 'translated 3';
    unset($translation['dummy']['deep_nesting']['#origin']);
    unset($translation['dummy']['deep_nesting']['#timestamp']);
    $item1->addTranslatedData($translation);
    $data = $item1->getData($key);

    // The translation text is NOT updated.
    $this->assertEquals('translated 2', $data['#translation']['#text']);
    $this->assertEquals(\Drupal::time()->getRequestTime() - 5, $data['#translation']['#timestamp']);
    // Received translation is the latest revision.
    $last_revision = end($data['#translation']['#text_revisions']);
    $this->assertEquals('translated 3', $last_revision['#text']);
    $this->assertEquals('remote', $last_revision['#origin']);
    $this->assertEquals(\Drupal::time()->getRequestTime(), $last_revision['#timestamp']);
    // Current translation origin is local.
    $this->assertEquals('local', $data['#translation']['#origin']);

    // Check job messages.
    $messages = $job->getMessages();
    $this->assertCount(2, $messages);
    $last_message = end($messages);
    $this->assertEquals('Translation for customized @key received. Revert your changes if you wish to use it.', $last_message->message->value);

    // Revert to previous revision which is the latest received translation.
    $item1->dataItemRevert($key);
    $data = $item1->getData($key);

    // The translation text is updated.
    $this->assertEquals('translated 3', $data['#translation']['#text']);
    $this->assertEquals('remote', $data['#translation']['#origin']);
    $this->assertEquals(\Drupal::time()->getRequestTime(), $data['#translation']['#timestamp']);
    // Latest revision is now the formerly added local translation.
    $last_revision = end($data['#translation']['#text_revisions']);
    $this->assertNotEmpty($last_revision['#text'], 'translated 2');
    $this->assertNotEmpty($last_revision['#origin'], 'remote');
    $this->assertEquals(\Drupal::time()->getRequestTime() - 5, $last_revision['#timestamp']);

    // Check job messages.
    $messages = $job->getMessages();
    $this->assertCount(3, $messages);
    $last_message = end($messages);
    $this->assertEquals('Translation for @key reverted to the latest version.', $last_message->message->value);

    // There should be three revisions now.
    $this->assertCount(3, $data['#translation']['#text_revisions']);

    // Attempt to update the translation with the same text, this should not
    // lead to a new revision.
    $translation['dummy']['deep_nesting']['#text'] = 'translated 3';
    //unset($translation['dummy']['deep_nesting']['#origin']);
    //unset($translation['dummy']['deep_nesting']['#timestamp']);
    $item1->addTranslatedData($translation);
    $data = $item1->getData($key);
    $this->assertCount(3, $data['#translation']['#text_revisions']);

    // Mark the translation as reviewed, a new translation should not update the
    // existing one but create a new translation.
    $item1->updateData($key, array('#status' => TMGMT_DATA_ITEM_STATE_REVIEWED));
    $translation['dummy']['deep_nesting']['#text'] = 'translated 4';
    $item1->addTranslatedData($translation);
    $data = $item1->getData($key);

    // The translation text is NOT updated.
    $this->assertEquals('translated 3', $data['#translation']['#text']);
    // Received translation is the latest revision.
    $this->assertCount(4, $data['#translation']['#text_revisions']);
    $last_revision = end($data['#translation']['#text_revisions']);
    $this->assertEquals('translated 4', $last_revision['#text']);
    $this->assertEquals('remote', $last_revision['#origin']);
    $this->assertEquals(\Drupal::time()->getRequestTime(), $last_revision['#timestamp']);

    // Check job messages.
    $messages = $job->getMessages();
    $this->assertCount(4, $messages);
    $last_message = end($messages);
    $this->assertEquals('Translation for already reviewed @key received and stored as a new revision. Revert to it if you wish to use it.', $last_message->message->value);

    // Add a new job item.
    $new_item = $job->addItem('test_source', 'test_with_long_label', 6);
    $translation['dummy']['deep_nesting']['#text'] = 'translated 1';
    $new_item->addTranslatedData($translation);
    $messages = $job->getMessages();
    $this->assertCount(5, $messages);
    $last_message = end($messages);

    // Assert that the job and job item are loaded correctly.
    $message_job = $last_message->getJob();
    $this->assertInstanceOf(JobInterface::class, $message_job);
    $this->assertEquals($job->id(), $message_job->id());
    $message_job_item = $last_message->getJobItem();
    $this->assertInstanceOf(JobItemInterface::class, $message_job_item);
    $this->assertEquals($new_item->id(), $message_job_item->id());
  }

  /**
   * Test the calculations of the counters.
   */
  function testJobItemsCounters() {
    $job = $this->createJob();

    // Some test data items.
    $data1 = array(
      '#text' => 'The text to be translated.',
    );
    $data2 = array(
      '#text' => 'The text to be translated.',
      '#translation' => '',
    );
    $data3 = array(
      '#text' => 'The text to be translated.',
      '#translation' => 'The translated data. Set by the translator plugin.',
    );
    $data4 = array(
      '#text' => 'Another, longer text to be translated.',
      '#translation' => 'The translated data. Set by the translator plugin.',
      '#status' => TMGMT_DATA_ITEM_STATE_REVIEWED,
    );
    $data5 = array(
      '#label' => 'label',
      'data1' => $data1,
      'data4' => $data4,
    );
    $data6 = array(
      '#text' => '<p>Test the HTML tags count.</p>',
    );

    // No data items.
    $this->assertEquals(0, $job->getCountPending());
    $this->assertEquals(0, $job->getCountTranslated());
    $this->assertEquals(0, $job->getCountReviewed());
    $this->assertEquals(0, $job->getCountAccepted());
    $this->assertEquals(0, $job->getWordCount());

    // Add a test items.
    $job_item1 = tmgmt_job_item_create('plugin', 'type', 4, array('tjid' => $job->id()));
    $job_item1->save();

    // No pending, translated and confirmed data items.
    $job = Job::load($job->id());
    $job_item1 = JobItem::load($job_item1->id());
    drupal_static_reset('tmgmt_job_statistics_load');
    $this->assertEquals(0, $job_item1->getCountPending());
    $this->assertEquals(0, $job_item1->getCountTranslated());
    $this->assertEquals(0, $job_item1->getCountReviewed());
    $this->assertEquals(0, $job_item1->getCountAccepted());
    $this->assertEquals(0, $job->getCountPending());
    $this->assertEquals(0, $job->getCountTranslated());
    $this->assertEquals(0, $job->getCountReviewed());
    $this->assertEquals(0, $job->getCountAccepted());

    // Add an untranslated data item.
    $job_item1->updateData('data_item1', $data1);
    $job_item1->save();

    // One pending data items.
    $job = Job::load($job->id());
    $job_item1 = JobItem::load($job_item1->id());
    drupal_static_reset('tmgmt_job_statistics_load');
    $this->assertEquals(1, $job_item1->getCountPending());
    $this->assertEquals(0, $job_item1->getCountTranslated());
    $this->assertEquals(0, $job_item1->getCountReviewed());
    $this->assertEquals(5, $job_item1->getWordCount());
    $this->assertEquals(1, $job->getCountPending());
    $this->assertEquals(0, $job->getCountReviewed());
    $this->assertEquals(0, $job->getCountTranslated());
    $this->assertEquals(5, $job->getWordCount());


    // Add another untranslated data item.
    // Test with an empty translation set.
    $job_item1->updateData('data_item1', $data2, TRUE);
    $job_item1->save();

    // One pending data items.
    $job = Job::load($job->id());
    $job_item1 = JobItem::load($job_item1->id());
    drupal_static_reset('tmgmt_job_statistics_load');
    $this->assertEquals(1, $job_item1->getCountPending());
    $this->assertEquals(0, $job_item1->getCountTranslated());
    $this->assertEquals(0, $job_item1->getCountReviewed());
    $this->assertEquals(5, $job_item1->getWordCount());
    $this->assertEquals(1, $job->getCountPending());
    $this->assertEquals(0, $job->getCountTranslated());
    $this->assertEquals(0, $job->getCountReviewed());
    $this->assertEquals(5, $job->getWordCount());

    // Add a translated data item.
    $job_item1->updateData('data_item1', $data3, TRUE);
    $job_item1->save();

    // One translated data items.
    drupal_static_reset('tmgmt_job_statistics_load');
    $this->assertEquals(0, $job_item1->getCountPending());
    $this->assertEquals(1, $job_item1->getCountTranslated());
    $this->assertEquals(0, $job_item1->getCountReviewed());
    $this->assertEquals(0, $job->getCountPending());
    $this->assertEquals(0, $job->getCountReviewed());
    $this->assertEquals(1, $job->getCountTranslated());

    // Add a confirmed data item.
    $job_item1->updateData('data_item1', $data4, TRUE);
    $job_item1->save();

    // One reviewed data item.
    drupal_static_reset('tmgmt_job_statistics_load');
    $this->assertEquals(1, $job_item1->getCountReviewed());
    $this->assertEquals(1, $job->getCountReviewed());

    // Add a translated and an untranslated and a confirmed data item
    $job = Job::load($job->id());
    $job_item1 = JobItem::load($job_item1->id());
    $job_item1->updateData('data_item1', $data1, TRUE);
    $job_item1->updateData('data_item2', $data3, TRUE);
    $job_item1->updateData('data_item3', $data4, TRUE);
    $job_item1->save();

    // One pending and translated data items each.
    drupal_static_reset('tmgmt_job_statistics_load');
    $this->assertEquals(1, $job->getCountPending());
    $this->assertEquals(1, $job->getCountTranslated());
    $this->assertEquals(1, $job->getCountReviewed());
    $this->assertEquals(16, $job->getWordCount());

    // Add nested data items.
    $job_item1->updateData('data_item1', $data5, TRUE);
    $job_item1->save();

    // One pending data items.
    $job = Job::load($job->id());
    $job_item1 = JobItem::load($job_item1->id());
    $this->assertEquals('label', $job_item1->getData()['data_item1']['#label']);
    $this->assertCount(3, $job_item1->getData()['data_item1']);

    // Add a greater number of data items
    for ($index = 1; $index <= 3; $index++) {
      $job_item1->updateData('data_item' . $index, $data1, TRUE);
    }
    for ($index = 4; $index <= 10; $index++) {
      $job_item1->updateData('data_item' . $index, $data3, TRUE);
    }
    for ($index = 11; $index <= 15; $index++) {
      $job_item1->updateData('data_item' . $index, $data4, TRUE);
    }
    $job_item1->save();

    // 3 pending and 7 translated data items each.
    $job = Job::load($job->id());
    drupal_static_reset('tmgmt_job_statistics_load');
    $this->assertEquals(3, $job->getCountPending());
    $this->assertEquals(7, $job->getCountTranslated());
    $this->assertEquals(5, $job->getCountReviewed());

    // Check for HTML tags count.
    $job_item1->updateData('data_item1', $data6);
    $job_item1->save();
    $this->assertEquals(2, $job_item1->getTagsCount());

    // Add several job items
    $job_item2 = tmgmt_job_item_create('plugin', 'type', 5, array('tjid' => $job->id()));
    for ($index = 1; $index <= 4; $index++) {
      $job_item2->updateData('data_item' . $index, $data1, TRUE);
    }
    for ($index = 5; $index <= 12; $index++) {
      $job_item2->updateData('data_item' . $index, $data3, TRUE);
    }
    for ($index = 13; $index <= 16; $index++) {
      $job_item2->updateData('data_item' . $index, $data4, TRUE);
    }
    $job_item2->save();

    // 3 pending and 7 translated data items each.
    $job = Job::load($job->id());
    drupal_static_reset('tmgmt_job_statistics_load');
    $this->assertEquals(7, $job->getCountPending());
    $this->assertEquals(15, $job->getCountTranslated());
    $this->assertEquals(9, $job->getCountReviewed());

    // Accept the job items.
    foreach ($job->getItems() as $item) {
      // Set the state directly to avoid triggering translator and source
      // controllers that do not exist.
      $item->setState(JobItem::STATE_ACCEPTED);
      $item->save();
    }
    drupal_static_reset('tmgmt_job_statistics_load');
    $this->assertEquals(0, $job->getCountPending());
    $this->assertEquals(0, $job->getCountTranslated());
    $this->assertEquals(0, $job->getCountReviewed());
    $this->assertEquals(31, $job->getCountAccepted());
  }

  /**
   * Test crud operations of jobs.
   */
  public function testContinuousTranslators() {
    $translator = $this->createTranslator();
    $this->assertTrue($translator->getPlugin() instanceof ContinuousTranslatorInterface);

    $job = $this->createJob('en', 'de', 0, ['job_type' => Job::TYPE_CONTINUOUS]);

    $this->assertEquals(Job::TYPE_CONTINUOUS, $job->getJobType());
    $job->translator = $translator->id();
    $job->save();

    // Add a test item.
    $item = $job->addItem('test_source', 'test', 1);

    /** @var ContinuousTranslatorInterface $plugin */
    $plugin = $job->getTranslatorPlugin();
    $plugin->requestJobItemsTranslation([$item]);

    $this->assertEquals('de(de-ch): Text for job item with type test and id 1.', $item->getData()['dummy']['deep_nesting']['#translation']['#text']);
  }

  /**
   * Tests that with the preliminary state the item does not change.
   */
  public function testPreliminaryState() {
    $translator = $this->createTranslator();
    $job = $this->createJob();
    $job->translator = $translator->id();
    $job->save();

    // Add some test items.
    $item = $job->addItem('test_source', 'test', 1);

    $key = array('dummy', 'deep_nesting');

    // Test with preliminary state.
    $translation['dummy']['deep_nesting']['#text'] = 'translated';
    $item->addTranslatedData($translation, [], TMGMT_DATA_ITEM_STATE_PRELIMINARY);
    $this->assertEquals(TMGMT_DATA_ITEM_STATE_PRELIMINARY, $item->getData($key)['#status']);
    $this->assertTrue($item->isActive());

    // Test with empty state.
    $item->addTranslatedData($translation);
    $this->assertEquals(TMGMT_DATA_ITEM_STATE_PRELIMINARY, $item->getData($key)['#status']);
    $this->assertTrue($item->isActive());

    // Test without state.
    $item->addTranslatedData($translation, [], TMGMT_DATA_ITEM_STATE_TRANSLATED);
    $this->assertEquals(TMGMT_DATA_ITEM_STATE_TRANSLATED, $item->getData($key)['#status']);
    $this->assertTrue($item->isNeedsReview());
  }

}
