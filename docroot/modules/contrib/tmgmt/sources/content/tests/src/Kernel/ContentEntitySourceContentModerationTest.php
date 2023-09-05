<?php

namespace Drupal\Tests\tmgmt_content\Kernel;

use Drupal\entity_test\Entity\EntityTestMulRev;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\Tests\content_moderation\Traits\ContentModerationTestTrait;

/**
 * Content entity Source unit tests.
 *
 * @group tmgmt
 */
class ContentEntitySourceContentModerationTest extends ContentEntityTestBase {

  use ContentModerationTestTrait;

  /**
   * The test entity type.
   *
   * @var string
   */
  protected $entityTypeId = 'entity_test_mulrev';

  /**
   * The workflow entity.
   *
   * @var \Drupal\workflows\WorkflowInterface
   */
  protected $workflow;

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = ['content_moderation', 'tmgmt_content', 'workflows', 'language'];

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('content_moderation_state');
    $this->installEntitySchema('user');
    $this->installConfig(['content_moderation', 'filter']);
    $this->installEntitySchema('entity_test_mulrev');

    ConfigurableLanguage::createFromLangcode('es')->save();

    $this->workflow = $this->createEditorialWorkflow();
    $this->workflow->getTypePlugin()->addEntityTypeAndBundle($this->entityTypeId, $this->entityTypeId);
    $this->workflow->save();
    $this->container->get('content_translation.manager')->setEnabled($this->entityTypeId, $this->entityTypeId, TRUE);
  }

  /**
   * Tests the Content Moderation integration.
   */
  public function testModerationState() {
    $this->config('tmgmt_content.settings')
      ->set('default_moderation_states', [$this->workflow->id() => 'published'])
      ->save();
    $values = array(
      'langcode' => 'en',
      'user_id' => 1,
      'moderation_state' => 'published',
      'name' => $this->randomMachineName()
    );
    $entity_test = EntityTestMulRev::create($values);
    $entity_test->save();

    $job = tmgmt_job_create('en', 'de');
    $job->translator = 'test_translator';
    $job->save();
    $job_item = tmgmt_job_item_create('content', $this->entityTypeId, $entity_test->id(), ['tjid' => $job->id()]);
    $job_item->save();

    $job->requestTranslation();
    $job->acceptTranslation();
    $items = $job->getItems();
    $item = reset($items);
    $item->isAccepted();
    $entity_test = EntityTestMulRev::load($entity_test->id());
    $this->assertTrue($entity_test->hasTranslation('de'));

    $translation = $entity_test->getTranslation('de');
    $this->assertEquals('published', $translation->get('moderation_state')->value);

    $this->config('tmgmt_content.settings')
      ->set('default_moderation_states', [$this->workflow->id() => 'draft'])
      ->save();
    $entity_test = EntityTestMulRev::load($entity_test->id());
    $job = tmgmt_job_create('en', 'es');
    $job->translator = 'test_translator';
    $job->save();
    $job_item = $job->addItem('content', $this->entityTypeId, $entity_test->id());

    $job->requestTranslation();
    $job->acceptTranslation();
    $items = $job->getItems();
    $item = reset($items);
    $this->assertTrue($item->isAccepted());

    // The default revision does not yet have a spanish translation.
    $entity_test = EntityTestMulRev::load($entity_test->id());
    $this->assertFalse($entity_test->hasTranslation('es'));

    // Fetch the latest revision affecting spanish and check the moderation
    // status.
    $entity_test_revision_id = \Drupal::entityTypeManager()->getStorage($this->entityTypeId)->getLatestTranslationAffectedRevisionId($entity_test->id(), 'es');
    $entity_test_revision = \Drupal::entityTypeManager()->getStorage($this->entityTypeId)->loadRevision($entity_test_revision_id);
    $this->assertTrue($entity_test_revision->hasTranslation('es'));

    $translation = $entity_test_revision->getTranslation('es');
    $this->assertEquals('draft', $translation->get('moderation_state')->value);
  }

}
