<?php

namespace Drupal\Tests\tmgmt_content\Kernel;

use Drupal\Core\Entity\EntityStorageException;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\entity_test\Entity\EntityTestMul;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\file\Entity\File;
use Drupal\filter\Entity\FilterFormat;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\tmgmt\ContinuousTranslatorInterface;
use Drupal\tmgmt\Entity\Translator;
use Drupal\tmgmt\JobItemInterface;
use Drupal\tmgmt\Entity\Job;
use Drupal\tmgmt_test\EventSubscriber\TestContinuousEventSubscriber;

/**
 * Content entity Source unit tests.
 *
 * @group tmgmt
 */
class ContentEntitySourceUnitTest extends ContentEntityTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = ['node', 'file', 'image'];

  protected $image_label;

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();

    $filter = FilterFormat::create([
      'format' => 'unallowed_format',
      'name' => 'Unallowed Format',
    ]);
    $filter->save();

    $this->config('tmgmt.settings')
      ->set('allowed_formats', ['text_plain'])
      ->save();

    $this->installSchema('node', ['node_access']);

    // Auto-create fields for testing.
    FieldStorageConfig::create([
      'entity_type' => $this->entityTypeId,
      'field_name' => 'field_test_text',
      'type' => 'text',
      'cardinality' => 1,
    ])->save();
    FieldConfig::create([
      'entity_type' => $this->entityTypeId,
      'field_name' => 'field_test_text',
      'bundle' => $this->entityTypeId,
      'label' => 'Test text-field',
      'translatable' => FALSE,
    ])->save();

    // Make the test field translatable.
    $field_storage = FieldStorageConfig::loadByName($this->entityTypeId, 'field_test_text');
    $field_storage->setCardinality(4);
    $field_storage->save();
    $field = FieldConfig::loadByName($this->entityTypeId, $this->entityTypeId, 'field_test_text');
    $field->setTranslatable(TRUE);
    $field->save();

    // Add an image field and make it translatable.
    $this->installEntitySchema('file');
    $this->installSchema('file', array('file_usage'));

    $this->installConfig(array('node'));

    $field_storage = FieldStorageConfig::create(array(
      'field_name' => 'image_test',
      'entity_type' => $this->entityTypeId,
      'type' => 'image',
      'cardinality' => FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED,
      'translatable' => TRUE,
    ));
    $field_storage->save();
    FieldConfig::create(array(
      'entity_type' => $this->entityTypeId,
      'field_storage' => $field_storage,
      'bundle' => $this->entityTypeId,
      'label' => $this->image_label = $this->randomMachineName(),
    ))->save();
    \Drupal::service('file_system')->copy(DRUPAL_ROOT . '/core/misc/druplicon.png', 'public://example.jpg');
    $this->image = File::create([
      'uri' => 'public://example.jpg',
    ]);
    $this->image->save();

    // Add a translatable text field that should be ignored.
    $field_storage = FieldStorageConfig::create(array(
      'field_name' => 'ignored_field',
      'entity_type' => $this->entityTypeId,
      'type' => 'text',
      'cardinality' => FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED,
      'translatable' => TRUE,
    ));
    $field_storage->save();
    FieldConfig::create(array(
      'entity_type' => $this->entityTypeId,
      'field_storage' => $field_storage,
      'bundle' => $this->entityTypeId,
      'label' => $this->randomMachineName(),
    ))->setThirdPartySetting('tmgmt_content', 'excluded', TRUE)
      ->save();

    // Add a translatable text field that should be checked after the translated
    // entity has been created.
    $field_storage = FieldStorageConfig::create(array(
      'field_name' => 'later_addition',
      'entity_type' => $this->entityTypeId,
      'type' => 'text',
      'cardinality' => 1,
      'translatable' => TRUE,
    ));
    $field_storage->save();
    FieldConfig::create(array(
      'entity_type' => $this->entityTypeId,
      'field_storage' => $field_storage,
      'bundle' => $this->entityTypeId,
      'label' => $this->later_addition_label = $this->randomMachineName(),
    ))->setTranslatable(TRUE)
      ->save();
  }

  /**
   * Create an english test entity.
   */
  public function testEntityTest() {
    $values = array(
      'langcode' => 'en',
      'user_id' => 1,
    );
    $entity_test = EntityTestMul::create($values);
    $entity_test->name->value = $this->randomMachineName();
    $entity_test->field_test_text->appendItem([
      'value' => $this->randomMachineName(),
      'format' => 'text_plain',
    ]);
    $entity_test->field_test_text->appendItem([
      'value' => $this->randomMachineName(),
      'format' => 'text_plain',
    ]);
    $entity_test->field_test_text->appendItem([
      'value' => $this->randomMachineName(),
      'format' => 'unallowed_format',
    ]);

    // Add another item that will be removed again.
    $entity_test->field_test_text->appendItem([
      'value' => $this->randomMachineName(),
      'format' => 'text_plain',
    ]);

    $values = array(
      'target_id' => $this->image->id(),
      'alt' => $this->randomMachineName(),
      'title' => $this->randomMachineName(),
    );
    $entity_test->image_test->appendItem($values);

    $entity_test->ignored_field->appendItem([
      'value' => 'This field should not be translated.',
      'format' => 'text_plain',
    ]);

    $entity_test->save();

    $job = tmgmt_job_create('en', 'de');
    $job->translator = 'test_translator';
    $job->save();
    $job_item = tmgmt_job_item_create('content', $this->entityTypeId, $entity_test->id(), array('tjid' => $job->id()));
    $job_item->save();

    $source_plugin = $this->container->get('plugin.manager.tmgmt.source')->createInstance('content');
    $data = $source_plugin->getData($job_item);

    // Test the name property.
    $this->assertEquals('Name', $data['name']['#label']);
    $this->assertFalse(isset($data['name'][0]['#label']));
    $this->assertFalse(isset($data['name'][0]['value']['#label']));
    $this->assertEquals($entity_test->name->value, $data['name'][0]['value']['#text']);
    $this->assertTrue($data['name'][0]['value']['#translate']);

    // Test the test field.
    $this->assertEquals('Test text-field', $data['field_test_text']['#label']);
    $this->assertEquals('Delta #0', $data['field_test_text'][0]['#label']);
    $this->assertFalse(isset($data['field_test_text'][0]['value']['#label']));
    $this->assertEquals($entity_test->field_test_text->value, $data['field_test_text'][0]['value']['#text']);
    $this->assertTrue($data['field_test_text'][0]['value']['#translate']);
    $this->assertFalse(isset($data['field_test_text'][0]['format']['#label']));
    $this->assertEquals('text_plain', $data['field_test_text'][0]['value']['#format']);
    $this->assertEquals($entity_test->field_test_text->format, $data['field_test_text'][0]['format']['#text']);
    $this->assertFalse($data['field_test_text'][0]['format']['#translate']);
    $this->assertFalse(isset($data['field_test_text'][0]['processed']));

    $this->assertEquals('Delta #1', $data['field_test_text'][1]['#label']);
    $this->assertFalse(isset($data['field_test_text'][1]['value']['#label']));
    $this->assertEquals($entity_test->field_test_text[1]->value, $data['field_test_text'][1]['value']['#text']);
    $this->assertTrue($data['field_test_text'][1]['value']['#translate']);
    $this->assertFalse(isset($data['field_test_text'][1]['format']['#label']));
    $this->assertEquals('text_plain', $data['field_test_text'][1]['value']['#format']);
    $this->assertEquals($entity_test->field_test_text[1]->format, $data['field_test_text'][1]['format']['#text']);
    $this->assertFalse($data['field_test_text'][1]['format']['#translate']);
    $this->assertFalse(isset($data['field_test_text'][1]['processed']));

    $this->assertEquals('Delta #2', $data['field_test_text'][2]['#label']);
    $this->assertFalse(isset($data['field_test_text'][2]['value']['#label']));
    $this->assertEquals($entity_test->field_test_text[2]->value, $data['field_test_text'][2]['value']['#text']);
    $this->assertFalse($data['field_test_text'][2]['value']['#translate']);
    $this->assertFalse(isset($data['field_test_text'][2]['format']['#label']));
    $this->assertEquals($entity_test->field_test_text[2]->format, $data['field_test_text'][2]['format']['#text']);
    $this->assertFalse($data['field_test_text'][2]['format']['#translate']);
    $this->assertFalse(isset($data['field_test_text'][2]['processed']));

    $this->assertEquals('Delta #3', $data['field_test_text'][3]['#label']);
    $this->assertFalse(isset($data['field_test_text'][3]['value']['#label']));
    $this->assertEquals('text_plain', $data['field_test_text'][3]['value']['#format']);
    $this->assertEquals($entity_test->field_test_text[3]->value, $data['field_test_text'][3]['value']['#text']);
    $this->assertTrue($data['field_test_text'][3]['value']['#translate']);
    $this->assertFalse(isset($data['field_test_text'][3]['format']['#label']));
    $this->assertEquals($entity_test->field_test_text[3]->format, $data['field_test_text'][3]['format']['#text']);
    $this->assertFalse($data['field_test_text'][3]['format']['#translate']);
    $this->assertFalse(isset($data['field_test_text'][3]['processed']));

    // Test the image field.
    $image_item = $data['image_test'][0];
    $this->assertEquals($this->image_label, $data['image_test']['#label']);
    $this->assertFalse(isset($image_item['#label']));
    $this->assertFalse($image_item['target_id']['#translate']);
    $this->assertFalse($image_item['width']['#translate']);
    $this->assertFalse($image_item['height']['#translate']);
    $this->assertTrue($image_item['alt']['#translate']);
    $this->assertEquals(t('Alternative text'), $image_item['alt']['#label']);
    $this->assertEquals($entity_test->image_test->alt, $image_item['alt']['#text']);
    $this->assertTrue($image_item['title']['#translate']);
    $this->assertEquals(t('Title'), $image_item['title']['#label']);
    $this->assertEquals($entity_test->image_test->title, $image_item['title']['#text']);

    // Test the ignored field.
    $this->assertFalse(isset($data['ignored_field']));

    // Now request a translation and save it back.
    $job->requestTranslation();

    $entity_test->get('field_test_text')->offsetUnset(3);
    $entity_test->save();

    $items = $job->getItems();
    $item = reset($items);
    $item->acceptTranslation();
    $data = $item->getData();

    // Check that the translations were saved correctly.
    $entity_test = \Drupal::entityTypeManager()->getStorage($this->entityTypeId)->load($entity_test->id());
    $translation = $entity_test->getTranslation('de');

    $this->assertEquals($data['name'][0]['value']['#translation']['#text'], $translation->name->value);
    $this->assertEquals($data['field_test_text'][0]['value']['#translation']['#text'], $translation->field_test_text[0]->value);
    $this->assertEquals($data['field_test_text'][1]['value']['#translation']['#text'], $translation->field_test_text[1]->value);
    $this->assertEquals($data['field_test_text'][2]['value']['#text'], $translation->field_test_text[2]->value);
    $this->assertEquals($data['field_test_text'][3]['value']['#translation']['#text'], $translation->field_test_text[3]->value);

    // Test adding data to the source and translating again.
    $source = $entity_test->getTranslation('en');
    $source->later_addition->appendItem([
      'value' => 'This field data is added after the translation exists.',
      'format' => 'text_plain',
    ]);
    $entity_test->save();

    // Reset the job item.
    $job_item->resetData();
    $job_item->save();

    // Test that the job item was updated correctly.
    $source_plugin = $this->container->get('plugin.manager.tmgmt.source')->createInstance('content');
    $data = $source_plugin->getData($job_item);

    // Test the later addition field.
    $this->assertEquals($this->later_addition_label, $data['later_addition']['#label']);
    $this->assertFalse(isset($data['later_addition'][0]['value']['#label']));
    $this->assertEquals($entity_test->later_addition->value, $data['later_addition'][0]['value']['#text']);
    $this->assertTrue($data['later_addition'][0]['value']['#translate']);
    $this->assertFalse(isset($data['later_addition'][0]['format']['#label']));
    $this->assertEquals('text_plain', $data['later_addition'][0]['value']['#format']);
    $this->assertEquals($entity_test->later_addition->format, $data['later_addition'][0]['format']['#text']);
    $this->assertFalse($data['later_addition'][0]['format']['#translate']);
    $this->assertFalse(isset($data['later_addition'][0]['processed']));

    // Now request a translation again and save it back.
    $job->requestTranslation();
    $items = $job->getItems();
    $item = reset($items);
    $item->acceptTranslation();
    $data = $item->getData();

    // Check that the new translation was saved correctly.
    $entity_test = \Drupal::entityTypeManager()->getStorage($this->entityTypeId)->load($entity_test->id());
    $translation = $entity_test->getTranslation('de');

    // Ensure no item was created for the removed delta.
    $this->assertEquals($data['later_addition'][0]['value']['#translation']['#text'], $translation->later_addition[0]->value);
  }

  /**
   * Test node field extraction.
   */
  public function testNode() {
    // Create an english node.
    $account = $this->createUser();
    $type = $this->drupalCreateContentType();
    $field = FieldStorageConfig::loadByName('node', 'body');
    $field->setTranslatable(TRUE);
    $field->setCardinality(2);
    $field->save();

    $node = Node::create([
      'title' => $this->randomMachineName(),
      'uid' => $account->id(),
      'type' => $type->id(),
      'langcode' => 'en',
    ]);

    $value = [
      'value' => $this->randomMachineName(),
      'summary' => $this->randomMachineName(),
      'format' => 'text_plain',
    ];
    $node->body->appendItem($value);
    $node->body->appendItem($value);
    $node->save();

    $job = tmgmt_job_create('en', 'de');
    $job->save();
    $job_item = tmgmt_job_item_create('content', 'node', $node->id(), array('tjid' => $job->id()));
    $job_item->save();

    $source_plugin = $this->container->get('plugin.manager.tmgmt.source')->createInstance('content');
    $data = $source_plugin->getData($job_item);

    // Test the title property.
    $this->assertEquals('Title', $data['title']['#label']);
    $this->assertFalse(isset($data['title'][0]['#label']));
    $this->assertFalse(isset($data['title'][0]['value']['#label']));
    $this->assertEquals($node->getTitle(), $data['title'][0]['value']['#text']);
    $this->assertTrue($data['title'][0]['value']['#translate']);

    // Test the body field.
    // @todo: Fields need better labels, needs to be fixed in core.
    $this->assertEquals('Body', $data['body']['#label']);
    $this->assertEquals('Delta #0', $data['body'][0]['#label']);
    $this->assertEquals('Text', (string) $data['body'][0]['value']['#label']);
    $this->assertEquals($node->body->value, $data['body'][0]['value']['#text']);
    $this->assertTrue($data['body'][0]['value']['#translate']);
    $this->assertEquals('text_plain', $data['body'][0]['value']['#format']);
    $this->assertEquals('Summary', (string) $data['body'][0]['summary']['#label']);
    $this->assertEquals($node->body->summary, $data['body'][0]['summary']['#text']);
    $this->assertTrue($data['body'][0]['summary']['#translate']);
    $this->assertEquals('Text format', (string) $data['body'][0]['format']['#label']);
    $this->assertEquals($node->body->format, $data['body'][0]['format']['#text']);
    $this->assertFalse($data['body'][0]['format']['#translate']);
    $this->assertFalse(isset($data['body'][0]['processed']));

    $this->assertEquals('Delta #1', $data['body'][1]['#label']);
    $this->assertEquals('Text', (string) $data['body'][1]['value']['#label']);
    $this->assertEquals($node->body[1]->value, $data['body'][1]['value']['#text']);
    $this->assertTrue($data['body'][1]['value']['#translate']);
    $this->assertEquals('Summary', (string) $data['body'][1]['summary']['#label']);
    $this->assertEquals($node->body[1]->summary, $data['body'][1]['summary']['#text']);
    $this->assertTrue($data['body'][1]['summary']['#translate']);
    $this->assertEquals('text_plain', $data['body'][0]['summary']['#format']);
    $this->assertEquals('Text format', (string) $data['body'][1]['format']['#label']);
    $this->assertEquals($node->body[1]->format, $data['body'][1]['format']['#text']);
    $this->assertFalse($data['body'][1]['format']['#translate']);
    $this->assertFalse(isset($data['body'][1]['processed']));

    // Test if language neutral entities can't be added to a translation job.
    $node = Node::create([
      'title' => $this->randomMachineName(),
      'uid' => $account->id(),
      'type' => $type->id(),
      'langcode' => LanguageInterface::LANGCODE_NOT_SPECIFIED,
    ]);
    $node->save();

    // Check that adding of language neutral to a translation job fails.
    $job = tmgmt_job_create(LanguageInterface::LANGCODE_NOT_SPECIFIED, 'de');
    $job->save();
    $job_item = tmgmt_job_item_create('content', 'node', $node->id(), array('tjid' => $job->id()));
    $this->expectException(EntityStorageException::class);
    $this->expectExceptionMessage('Entity <em class="placeholder">und</em> could not be translated because the language <em class="placeholder">Not specified</em> is not applicable');
    $job_item->save();
  }

  /**
   * Test node acceptTranslation.
   */
  public function testAcceptTranslation() {
    $account = $this->createUser();
    $type = $this->drupalCreateContentType();
    $this->container->get('content_translation.manager')->setEnabled('node', $type->id(), TRUE);
    /** @var Translator $translator */
    $translator = Translator::load('test_translator');
    $translator->setAutoAccept(TRUE)->save();
    $node = Node::create([
      'title' => $this->randomMachineName(),
      'uid' => $account->id(),
      'type' => $type->id(),
      'langcode' => 'en',
    ]);
    $node->save();
    $job = tmgmt_job_create('en', 'de');
    $job->translator = 'test_translator';
    $job->save();
    $job_item = tmgmt_job_item_create('content', $node->getEntityTypeId(), $node->id(), array('tjid' => $job->id()));
    $job_item->save();

    // Request translation. Here it fails.
    $job->requestTranslation();
    $items = $job->getItems();
    /** @var \Drupal\tmgmt\Entity\JobItem $item */
    $item = reset($items);
    // As was set to auto_accept, should be accepted.
    $this->assertEquals(JobItemInterface::STATE_ACCEPTED, $item->getState());

    // Test that the source language is set correctly.
    $node = Node::load($node->id());
    $manager = $this->container->get('content_translation.manager');
    $this->assertEquals('en', $manager->getTranslationMetadata($node->getTranslation('de'))->getSource(), 'Source language is correct.');
  }

  /**
    * Test if the source is able to pull content in requested language.
   */
  public function testRequestDataForSpecificLanguage() {
    // Create an english node.
    $account = $this->createUser();
    $type = $this->drupalCreateContentType();
    $field = FieldStorageConfig::loadByName('node', 'body');
    $field->setTranslatable(TRUE);
    $field->setCardinality(2);
    $field->save();

    $node = Node::create(array(
      'title' => $this->randomMachineName(),
      'uid' => $account->id(),
      'type' => $type->id(),
      'langcode' => 'cs',
    ));

    $node = $node->addTranslation('en');

    $node->get('title')->appendItem(array('value' => $this->randomMachineName()));
    $value = array(
      'value' => $this->randomMachineName(),
      'summary' => $this->randomMachineName(),
      'format' => 'text_plain'
    );
    $node->get('body')->appendItem($value);
    $node->get('body')->appendItem($value);
    $node->save();

    $job = tmgmt_job_create('en', 'de');
    $job->save();
    $job_item = tmgmt_job_item_create('content', 'node', $node->id(), array('tjid' => $job->id()));
    $job_item->save();

    $source_plugin = $this->container->get('plugin.manager.tmgmt.source')->createInstance('content');
    $data = $source_plugin->getData($job_item);
    $this->assertEquals($value['value'], $data['body'][0]['value']['#text']);
  }

  /**
   * Creates a custom content type based on default settings.
   *
   * @param $settings
   *   An array of settings to change from the defaults.
   *   Example: 'type' => 'foo'.
   * @return
   *   Created content type.
   */
  protected function drupalCreateContentType($settings = array()) {
    $name = strtolower($this->randomMachineName(8));
    $values = array(
      'type' => $name,
      'name' => $name,
      'base' => 'node_content',
      'title_label' => 'Title',
      'body_label' => 'Body',
      'has_title' => 1,
      'has_body' => 1,
    );

    $type = NodeType::create($values);
    $saved = $type->save();
    node_add_body_field($type);

    $this->assertEquals(SAVED_NEW, $saved);

    return $type;
  }

  /**
   * Test extraction and saving translation for embedded references.
   */
  public function testEmbeddedReferences() {
    $field1 = FieldStorageConfig::create(array(
      'field_name' => 'field1',
      'entity_type' => $this->entityTypeId,
      'type' => 'entity_reference',
      'cardinality' => -1,
      'settings' => array('target_type' => $this->entityTypeId),
    ));
    $field1->save();
    $field2 = FieldStorageConfig::create(array(
      'field_name' => 'field2',
      'entity_type' => $this->entityTypeId,
      'type' => 'entity_reference',
      'cardinality' => -1,
      'settings' => array('target_type' => $this->entityTypeId),
    ));
    $field2->save();

    // Create field instances on the content type.
    FieldConfig::create(array(
      'field_storage' => $field1,
      'bundle' => $this->entityTypeId,
      'label' => 'Field 1',
      'settings' => array(),
    ))->save();
    FieldConfig::create(array(
      'field_storage' => $field2,
      'bundle' => $this->entityTypeId,
      'label' => 'Field 2',
      'translatable' => FALSE,
      'settings' => array(),
    ))->save();

    $this->config('tmgmt_content.settings')
      ->set('embedded_fields.' . $this->entityTypeId . '.field1', TRUE)
      ->save();

    // Create test entities that can be referenced, the first 5 with en
    // then two with cs as source language.
    $referenced_entities = [];
    for ($i = 0; $i < 7; $i++) {
      $referenced_values = [
        'langcode' => $i < 5 ? 'en' : 'cs',
        'user_id' => 1,
        'name' => 'Referenced entity #' . $i,
      ];
      $referenced_entities[$i] = EntityTestMul::create($referenced_values);
      $referenced_entities[$i]->save();
    }

    // Add a translation for one of the cs entities.
    $referenced_entities[5]->addTranslation('en', ['name' => 'EN entity #5']);
    $referenced_entities[5]->save();

    // Create an english test entity.
    $values = array(
      'langcode' => 'en',
      'user_id' => 1,
      'name' => $this->randomString(),
      'field1' => [$referenced_entities[0], $referenced_entities[1], $referenced_entities[2], $referenced_entities[5], $referenced_entities[6]],
      'field2' => $referenced_entities[4],
    );
    $entity_test = EntityTestMul::create($values);
    $entity_test->save();

    $job = tmgmt_job_create('en', 'de');
    $job->translator = 'test_translator';
    $job->save();
    $job_item = tmgmt_job_item_create('content', $this->entityTypeId, $entity_test->id(), array('tjid' => $job->id()));
    $job_item->save();

    $source_plugin = $this->container->get('plugin.manager.tmgmt.source')->createInstance('content');
    $data = $source_plugin->getData($job_item);

    // Ensure that field 2 is not in the extracted data.
    $this->assertFalse(isset($data['field2']));

    // Ensure some labels and structure for field 1.
    $this->assertEquals('Field 1', $data['field1']['#label']);
    $this->assertEquals('Delta #0', $data['field1'][0]['#label']);
    $this->assertEquals('Name', $data['field1'][0]['entity']['name']['#label'], 'Name');
    $this->assertEquals($data['field1'][0]['entity']['name'][0]['value']['#text'], 'Referenced entity #0');
    $this->assertEquals($data['field1'][1]['entity']['name'][0]['value']['#text'], 'Referenced entity #1');
    $this->assertEquals($data['field1'][2]['entity']['name'][0]['value']['#text'], 'Referenced entity #2');
    $this->assertEquals($data['field1'][3]['entity']['name'][0]['value']['#text'], 'EN entity #5');
    $this->assertEquals($data['field1'][4]['entity']['name'][0]['value']['#text'], 'Referenced entity #6');

    // Now request a translation.
    $job->requestTranslation();

    // Mess with the source entity while the job is being translated. Remove
    // the second reference and switch positions.
    $entity_test->set('field1', [$referenced_entities[2], $referenced_entities[0], $referenced_entities[5], $referenced_entities[6]]);
    $entity_test->save();

    $items = $job->getItems();
    $item = reset($items);
    $item->acceptTranslation();

    \Drupal::entityTypeManager()->getStorage('entity_test_mul')->resetCache();

    // Check that the translations were saved correctly, making sure that the
    // translations were attached to the correct referenced entities as far
    // as possible.
    $referenced_translation = EntityTestMul::load($referenced_entities[0]->id())->getTranslation('de');
    $this->assertEquals('de(de-ch): Referenced entity #0', $referenced_translation->get('name')->value);

    $referenced_translation = EntityTestMul::load($referenced_entities[2]->id())->getTranslation('de');
    $this->assertEquals('de(de-ch): Referenced entity #2', $referenced_translation->get('name')->value);

    $referenced_translation = EntityTestMul::load($referenced_entities[5]->id())->getTranslation('de');
    $this->assertEquals('de(de-ch): EN entity #5', $referenced_translation->get('name')->value);

    $referenced_translation = EntityTestMul::load($referenced_entities[6]->id())->getTranslation('de');
    $this->assertEquals('de(de-ch): Referenced entity #6', $referenced_translation->get('name')->value);

    $this->assertFalse(EntityTestMul::load($referenced_entities[1]->id())->hasTranslation('de'));
    $this->assertFalse(EntityTestMul::load($referenced_entities[3]->id())->hasTranslation('de'));
    $this->assertFalse(EntityTestMul::load($referenced_entities[4]->id())->hasTranslation('de'));
  }

  /**
   * Test creation of continuous job items.
   */
  public function testContinuousJobItems() {
    $account = $this->createUser();
    $type = $this->drupalCreateContentType();
    $second_type = $this->drupalCreateContentType();

    // Enable entity translations for nodes.
    $content_translation_manager = \Drupal::service('content_translation.manager');
    $content_translation_manager->setEnabled('node', $type->label(), TRUE);
    $content_translation_manager->setEnabled('node', $second_type->label(), TRUE);

    // Create test translator for continuous job.
    $translator = Translator::load('test_translator');

    // Continuous settings configuration.
    $continuous_settings = [
      'content' => [
        'node' => [
          'enabled' => 1,
          'bundles' => [
            $type->id() => 1,
            $second_type->id() => 0,
          ],
        ],
      ],
    ];

    // Create continuous job with source language set to english.
    $continuous_job = tmgmt_job_create('en', 'de', $account->id(), [
      'job_type' => Job::TYPE_CONTINUOUS,
      'translator' => $translator,
      'continuous_settings' => $continuous_settings,
    ]);
    $this->assertEquals(SAVED_NEW, $continuous_job->save());

    // Create an english node.
    $prevented_node = Node::create([
      'title' => TestContinuousEventSubscriber::DISALLOWED_LABEL,
      'uid' => $account->id(),
      'type' => $type->id(),
      'langcode' => 'en',
    ]);
    $prevented_node->save();
    $this->assertEquals(0, count($continuous_job->getItems()));

    // Create an english node.
    $node = Node::create([
      'title' => $this->randomMachineName(),
      'uid' => $account->id(),
      'type' => $type->id(),
      'langcode' => 'en',
    ]);
    $node->save();

    // Test hook_entity_insert() for english node.
    $continuous_job_items = $continuous_job->getItems();
    $continuous_job_item = reset($continuous_job_items);
    $this->assertEquals($node->label(), $continuous_job_item->label(), 'Continuous job item is automatically created for an english node.');

    // Test that continuous job item is in state review.
    $this->assertEquals($continuous_job_item->getState(), JobItemInterface::STATE_REVIEW, 'Translation for an english node is in state review.');

    // Update english node.
    $node->set('title', $this->randomMachineName());
    $node->save();

    // Test that there is no new job item.
    $this->assertEquals(count($continuous_job->getItems()), 1, 'There are no new job items for an english node.');

    // Accept translation for an english node.
    $continuous_job_item->acceptTranslation();

    // Test that the translation for an english node is created and saved.
    $node = Node::load($node->id());
    $translation = $node->getTranslation('de');
    $data = $continuous_job_item->getData();
    $this->assertEquals($translation->label(), $data['title'][0]['value']['#translation']['#text'], 'Translation for an english node has been saved correctly.');
    $this->assertEquals($continuous_job_item->getState(), JobItemInterface::STATE_ACCEPTED, 'Translation for an english node has been accepted.');

    // Create a german node.
    $german_node = Node::create([
      'title' => $this->randomMachineName(),
      'uid' => $account->id(),
      'type' => $type->id(),
      'langcode' => 'de',
    ]);
    $german_node->save();

    // Test that there is no new item for german node.
    $this->assertEquals(count($continuous_job->getItems()), 1, 'Continuous job item is not created for a german node.');

    // Create new english node with different type.
    $second_node = Node::create([
      'title' => $this->randomMachineName(),
      'uid' => $account->id(),
      'type' => $second_type->id(),
      'langcode' => 'en',
    ]);
    $second_node->save();

    // Test that there is no new item for second english node.
    $this->assertEquals(count($continuous_job->getItems()), 1, 'Continuous job item is not created for a second english node.');

    // Update english node.
    $node->set('title', $this->randomMachineName());
    $node->save();

    // Test that there are no new job items for english node because it's
    // translation is not outdated.
    $this->assertEquals(count($continuous_job->getItems()), 1, 'Continuous job item is not created for an updated english node.');

    // Set the outdated flag to true.
    $translation = $node->getTranslation('de');
    $translation->content_translation_outdated->value = 1;
    $translation->save();

    // Test that there are now two items for english node.
    $this->assertCount(2, $continuous_job->getItems(), 'Continuous job item is automatically created for an updated english node.');

    $continuous_job_item_recent = $continuous_job->getMostRecentItem('content', $node->getEntityTypeId(), $node->id());

    // Set job item state to aborted.
    $continuous_job_item_recent->setState(JobItemInterface::STATE_ABORTED, NULL, array(), 'status');

    // Update english node.
    $node->set('title', $this->randomMachineName());
    $node->save();

    // Test that there are now three items for english node.
    $this->assertEquals(count($continuous_job->getItems()), 3, 'Continuous job item is automatically created for an updated english node.');
  }

  /**
   * Test submit continuous job items on cron.
   */
  public function testSubmitContinuousOnCron() {
    $account = $this->createUser();
    $type = $this->drupalCreateContentType();
    $second_type = $this->drupalCreateContentType();

    // Enable entity translations for nodes.
    $content_translation_manager = \Drupal::service('content_translation.manager');
    $content_translation_manager->setEnabled('node', $type->id(), TRUE);
    $content_translation_manager->setEnabled('node', $second_type->id(), TRUE);

    // Create test translator for continuous job.
    $translator = Translator::load('test_translator');

    // Continuous settings configuration.
    $continuous_settings = [
      'content' => [
        'node' => [
          'enabled' => 1,
          'bundles' => [
            $type->id() => 1,
            $second_type->id() => 0,
          ],
        ],
      ],
    ];

    $this->config('tmgmt.settings')
      ->set('submit_job_item_on_cron', TRUE)
      ->set('job_items_cron_limit', 3)
      ->save();

    $first_job = tmgmt_job_create('en', 'de', $account->id(), [
      'job_type' => Job::TYPE_CONTINUOUS,
      'translator' => $translator,
      'continuous_settings' => $continuous_settings,
    ]);
    $first_job->save();

    // Create an english node.
    $first_node = Node::create([
      'title' => $this->randomMachineName(),
      'uid' => $account->id(),
      'type' => $type->id(),
      'langcode' => 'en',
    ]);
    $first_node->save();

    $first_items = array_values($first_job->getItems());
    foreach ($first_items as $job_item) {
      $this->assertEquals(JobItemInterface::STATE_INACTIVE, $job_item->getState(), 'Job item is inactive before cron run');
    }

    // Test that there is one job item for an english node.
    $this->assertCount(1, $first_job->getItems(), 'There is one job item for an english node.');

    // Update english node.
    $first_node->set('title', $this->randomMachineName());
    $first_node->save();

    // Test that there is no new job item for updated english node.
    $this->assertCount(1, $first_job->getItems(), 'There are no new job items for updated english node.');

    // Test that job item's data is updated properly.
    $first_job_items = $first_job->getItems();
    $first_job_item = reset($first_job_items);
    $data = $first_job_item->getData();
    $this->assertEquals($first_node->label(), $data['title'][0]['value']['#text'], 'Data in job item has been updated properly.');

    $second_job = tmgmt_job_create('de', 'en', $account->id(), [
      'job_type' => Job::TYPE_CONTINUOUS,
      'translator' => $translator,
      'continuous_settings' => $continuous_settings,
    ]);
    $second_job->save();

    // Create a german node.
    $second_node = Node::create([
      'title' => $this->randomMachineName(),
      'uid' => $account->id(),
      'type' => $type->id(),
      'langcode' => 'de',
    ]);
    $second_node->save();
    // Create a german node.
    $third_node = Node::create([
      'title' => $this->randomMachineName(),
      'uid' => $account->id(),
      'type' => $type->id(),
      'langcode' => 'de',
    ]);
    $third_node->save();

    $second_items = array_values($second_job->getItems());
    foreach ($second_items as $job_item) {
      $this->assertEquals(JobItemInterface::STATE_INACTIVE, $job_item->getState(), 'Job item is inactive before cron run');
    }

    $third_job = tmgmt_job_create('cs', 'en', $account->id(), [
      'job_type' => Job::TYPE_CONTINUOUS,
      'translator' => $translator,
      'continuous_settings' => $continuous_settings,
    ]);
    $third_job->save();
    // Create 3 sample nodes.
    for ($i = 0; $i < 3; $i++) {
      $node = Node::create([
        'title' => $this->randomMachineName(),
        'uid' => $account->id(),
        'type' => $type->id(),
        'langcode' => 'cs',
      ]);
      $node->save();
    }

    tmgmt_cron();

    // Assert that the translator was called twice, once with the item of the
    // first job and once with the 2 items of the second job.
    $expected_groups = [
      [
        ['item_id' => $first_items[0]->id(), 'job_id' => $first_items[0]->getJobId()]
      ],
      [
        ['item_id' => $second_items[0]->id(), 'job_id' => $second_items[0]->getJobId()],
        ['item_id' => $second_items[1]->id(), 'job_id' => $second_items[1]->getJobId()]
      ]
    ];

    // Check job items is properly grouped and we have exactly 2 groups.
    $this->assertEquals($expected_groups, \Drupal::state()->get('job_item_groups'), 'Job items groups are equal');

    foreach ($first_job->getItems() as $job_item) {
      $this->assertEquals(JobItemInterface::STATE_REVIEW, $job_item->getState(), 'Job item is active after cron run');
    }

    foreach ($second_job->getItems() as $job_item) {
      $this->assertEquals(JobItemInterface::STATE_REVIEW, $job_item->getState(), 'Job item is active after cron run');
    }

    // Run cron again to process 3 remaining job items.
    tmgmt_cron();

    $third_items = array_values($third_job->getItems());
    $expected_groups[] = [
      ['item_id' => $third_items[0]->id(), 'job_id' => $third_items[0]->getJobId()],
      ['item_id' => $third_items[1]->id(), 'job_id' => $third_items[1]->getJobId()],
      ['item_id' => $third_items[2]->id(), 'job_id' => $third_items[2]->getJobId()],
    ];
    // Assert there are 3 new job items appeared from the third job.
    $this->assertEquals($expected_groups, \Drupal::state()->get('job_item_groups'), 'Job items groups are equal');
    foreach ($third_job->getItems() as $job_item) {
      $this->assertEquals(JobItemInterface::STATE_REVIEW, $job_item->getState(), 'Job item is active after cron run');
    }
  }

  /**
   * Test abortion of continuous translators.
   */
  public function testContinuousTranslatorsAbortion() {
    \Drupal::service('router.builder')->rebuild();
    // Create a continuous translator.
    $translator = Translator::load('test_translator');
    $this->assertTrue($translator->getPlugin() instanceof ContinuousTranslatorInterface);

    // Create a node type.
    $type = NodeType::create(['type' => $this->randomMachineName()]);
    $type->save();

    // Enable the node type for translation.
    $content_translation_manager = \Drupal::service('content_translation.manager');
    $content_translation_manager->setEnabled('node', $type->id(), TRUE);

    // Create a continuous job.
    $continuous_job = tmgmt_job_create('en', 'de', 0, [
      'job_type' => Job::TYPE_CONTINUOUS,
      'continuous_settings' => [
        'content' => [
          'node' => [
            'enabled' => TRUE,
            'bundles' => [
              $type->id() => TRUE,
            ],
          ],
        ],
      ],
    ]);
    $continuous_job->translator = $translator;
    $continuous_job->save();

    // Abort a continuous job.
    $continuous_job->aborted();

    // Create a node.
    $node = Node::create(array(
      'title' => $this->randomMachineName(),
      'type' => $type->id(),
      'language' => 'en',
      'body' => $this->randomMachineName(),
    ));
    $node->save();

    // Assert that node has not been captured.
    $updated_continuous_job = Job::load($continuous_job->id());
    $this->assertEquals([], $updated_continuous_job->getItems());
    $this->assertEquals(Job::STATE_ABORTED, $updated_continuous_job->getState());
  }

}
