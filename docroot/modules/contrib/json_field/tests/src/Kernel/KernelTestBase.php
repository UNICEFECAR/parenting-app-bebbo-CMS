<?php

namespace Drupal\Tests\json_field\Kernel;

use Drupal\Component\FileCache\FileCacheFactory;
use Drupal\Core\Site\Settings;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\KernelTestBase as DrupalKernelTestBase;

/**
 * The Class KernelTestBase.
 *
 * @package Drupal\Tests\json_field\Kernel
 */
abstract class KernelTestBase extends DrupalKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'json_field',
    'field',
    'user',
    'entity_test',
    'serialization',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    FileCacheFactory::setPrefix(Settings::getApcuPrefix('file_cache', $this->root));
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installEntitySchema('entity_test');
  }

  /**
   * Creates a field to use in tests.
   *
   * @param array $field_storage_properties
   *   Field storage properties array.
   * @param array $field_properties
   *   Field properties array.
   * @param string $type
   *   Type.
   */
  protected function createTestField(array $field_storage_properties = [], array $field_properties = [], $type = 'json') {
    $field_storage = FieldStorageConfig::create([
      'field_name' => 'test_json_field',
      'entity_type' => 'entity_test',
      'type' => $type,
    ] + $field_storage_properties);
    $field_storage->save();

    $field = FieldConfig::create([
      'field_name' => 'test_json_field',
      'entity_type' => 'entity_test',
      'type' => $type,
      'bundle' => 'entity_test',
    ] + $field_properties);
    $field->save();
  }

}
