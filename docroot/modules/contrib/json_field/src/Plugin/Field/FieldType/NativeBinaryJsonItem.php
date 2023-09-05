<?php

namespace Drupal\json_field\Plugin\Field\FieldType;

use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\TypedData\DataDefinition;

/**
 * Plugin implementation of the 'jsonb' field type.
 *
 * @FieldType(
 *   id = "json_native_binary",
 *   label = @Translation("JSONB/JSON (raw)"),
 *   description = @Translation("Allows JSON data to be stored in the database. On PostgreSQL the data is stored in a JSONB column, on MySQL it uses a regular JSON column."),
 *   category = @Translation("Data"),
 *   default_widget = "json_textarea",
 *   default_formatter = "json",
 *   constraints = {"valid_json" = {}}*
 * )
 */
class NativeBinaryJsonItem extends NativeJsonItem {

  /**
   * {@inheritdoc}
   */
  public static function schema(FieldStorageDefinitionInterface $field_definition) {
    return [
      'columns' => [
        'value' => [
          'type' => 'text',
          'pgsql_type' => 'jsonb',
          'mysql_type' => 'json',
          'not null' => FALSE,
        ],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public static function propertyDefinitions(FieldStorageDefinitionInterface $field_definition) {
    $properties['value'] = DataDefinition::create('string')
      ->setLabel(t('JSONB value'));

    return $properties;
  }

}
