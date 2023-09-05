<?php

namespace Drupal\Tests\tmgmt_content\Kernel;

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\paragraphs\Entity\ParagraphsType;

/**
 * Content entity update paragraphs test.
 *
 * @group tmgmt
 */
class ContentEntityUpdateParagraphTest extends ContentEntityTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = [
    'node',
    'entity_reference_revisions',
    'paragraphs',
    'file',
  ];

  /**
   * {@inheritdoc}
   *
   * Install an Article node bundle and a Text paragraph type. Install a
   * translatable text field on the paragraph and an untranslatable
   * entity_reference_revision field on the Article bundle with the text
   * paragraph as its target.
   */
  public function setUp(): void {
    parent::setUp();

    $this->installSchema('node', ['node_access']);
    // Create article content type.
    NodeType::create(['type' => 'article', 'name' => 'Article'])->save();

    $this->installEntitySchema('paragraph');
    \Drupal::moduleHandler()->loadInclude('paragraphs', 'install');

    // Create a paragraph type.
    $paragraph_type = ParagraphsType::create([
      'label' => 'Text',
      'id' => 'text',
    ]);
    $paragraph_type->save();

    // Add a text field to the paragraph.
    $field_storage = FieldStorageConfig::create([
      'field_name' => 'text',
      'entity_type' => 'paragraph',
      'type' => 'string',
      'cardinality' => '-1',
      'settings' => [],
    ]);
    $field_storage->save();
    $field = FieldConfig::create([
      'field_storage' => $field_storage,
      'bundle' => 'text',
      'translatable' => TRUE,
      'settings' => [
        'handler' => 'default:paragraph',
        'handler_settings' => ['target_bundles' => NULL],
      ],
    ]);
    $field->save();

    // Create the reference to the paragraph test.
    $field_storage = FieldStorageConfig::create([
      'field_name' => 'paragraph_reference',
      'entity_type' => 'node',
      'type' => 'entity_reference_revisions',
      'cardinality' => '-1',
      'settings' => [
        'target_type' => 'paragraph',
      ],
    ]);
    $field_storage->save();
    $field = FieldConfig::create([
      'field_storage' => $field_storage,
      'bundle' => 'article',
      'translatable' => FALSE,
    ]);
    $field->save();

    $content_translation_manager = \Drupal::service('content_translation.manager');
    $content_translation_manager->setEnabled('node', 'article', TRUE);
    $content_translation_manager->setEnabled('paragraph', 'text', TRUE);
  }

  /**
   * Tests that paragraph items with existing translations gets new translation.
   */
  public function testUpdateParagraphTranslation() {
    // Create the initial paragraph in defualt langauge.
    $paragraph = Paragraph::create([
      'text' => 'Initial Source Paragraph #1',
      'type' => 'text',
    ]);
    $paragraph->save();

    // Create the initial test node with a reference to the paragraph.
    $node = Node::create([
      'langcode' => 'en',
      'title' => 'Initial Source Node #1',
      'type' => 'article',
      'paragraph_reference' => $paragraph,
    ]);
    $node->save();

    $this->assertRevisionCount(1, 'node', $node->id());
    $this->assertRevisionCount(1, 'paragraph', $paragraph->id());

    // Add an initial translation so that we can test that we're able to update
    // a Content source translation with an already existing translation.
    $node_storage = \Drupal::entityTypeManager()->getStorage('node');
    $node_de = $node->addTranslation('de', ['title' => 'Initial host translation DE'] + $node->toArray());
    $node_de = $node_storage->createRevision($node_de, FALSE);
    $node_de->paragraph_reference->entity->getTranslation('de')->set('text', 'Initial paragraph translation DE');
    $node_de->isDefaultRevision(TRUE);
    $node_de->save();
    $node_de = Node::load($node_de->id())->getTranslation('de');
    $this->assertEquals('Initial host translation DE', $node_de->title->value);
    $this->assertEquals('Initial paragraph translation DE', $node_de->paragraph_reference->entity->getTranslation('de')->text->value);

    $this->assertRevisionCount(2, 'node', $node->id());
    $this->assertRevisionCount(2, 'paragraph', $paragraph->id());

    // Create a new job and job_item for the node.
    $job = tmgmt_job_create('en', 'de');
    $job->translator = 'test_translator';
    $job->save();
    $job_item = tmgmt_job_item_create('content', 'node', $node->id(), ['tjid' => $job->id()]);
    $job_item->save();

    // Now request a translation and save it back.
    $job->requestTranslation();
    $items = $job->getItems();
    $item = reset($items);
    $item->acceptTranslation();

    // Check that the translations were saved correctly.
    $node = Node::load($node->id());
    $translation = $node->getTranslation('de');
    $this->assertEquals('de(de-ch): Initial Source Node #1', $translation->title->value);
    $this->assertEquals('de(de-ch): Initial Source Paragraph #1', $translation->paragraph_reference->entity->getTranslation('de')->text->value);

    $this->assertRevisionCount(3, 'node', $node->id());
    $this->assertRevisionCount(3, 'paragraph', $paragraph->id());

    // Update the node and paragraph so that we can test if the translations are
    // updated.
    $node_storage = \Drupal::entityTypeManager()->getStorage('node');
    $node = $node->set('title', 'Updated Source Node #2');
    $node = $node_storage->createRevision($node, FALSE);
    $node->paragraph_reference->entity->set('text', 'Updated Source Paragraph #2');
    $node->isDefaultRevision(TRUE);
    $node->save();

    $this->assertRevisionCount(4, 'node', $node->id());
    $this->assertRevisionCount(4, 'paragraph', $paragraph->id());

    $node = Node::load($node->id());

    // Create a new job but this time we'll reset the entity cache before
    // accepting the translation.
    $job = tmgmt_job_create('en', 'de');
    $job->translator = 'test_translator';
    $job->save();
    $job_item = tmgmt_job_item_create('content', 'node', $node->id(), ['tjid' => $job->id()]);
    $job_item->save();

    // Reset the cache since we cannot know the state of the cache when
    // importing the translation.
    \Drupal::service('entity.memory_cache')->reset();

    // Now request a translation and save it back.
    $job->requestTranslation();

    $items = $job->getItems();
    $item = reset($items);
    $item->acceptTranslation();

    // Check that the translations were saved correctly.
    $node = Node::load($node->id());
    $translation = $node->getTranslation('de');
    $this->assertEquals('de(de-ch): Updated Source Node #2', $translation->title->value);
    $this->assertEquals('de(de-ch): Updated Source Paragraph #2', $translation->paragraph_reference->entity->getTranslation('de')->text->value);

    $this->assertRevisionCount(5, 'node', $node->id());
    $this->assertRevisionCount(5, 'paragraph', $paragraph->id());
  }

  /**
   * Asserts the revision count of a certain entity.
   *
   * @param int $expected
   *   The expected count.
   * @param string $entity_type_id
   *   The entity type ID, e.g. node.
   * @param int $entity_id
   *   The entity ID.
   */
  protected function assertRevisionCount($expected, $entity_type_id, $entity_id) {
    $id_field = \Drupal::entityTypeManager()->getDefinition($entity_type_id)->getKey('id');

    $revision_count = \Drupal::entityQuery($entity_type_id)
      ->accessCheck(FALSE)
      ->condition($id_field, $entity_id)
      ->allRevisions()
      ->count()
      ->execute();
    $this->assertEquals($expected, $revision_count);
  }

}
