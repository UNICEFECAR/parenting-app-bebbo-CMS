<?php

namespace Drupal\json_field\Plugin\Field\FieldType;

use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\TypedData\DataDefinition;

/**
 * Plugin implementation of the 'jsonb' field type.
 *
 * @FieldType(
 *   id = "json_native_binary",
 *   label = @Translation("JSON (native binary storage)"),
 *   description = @Translation("This field stores a JSON object or an array of JSON objects. JSONB datatype is only supported on Postgres. It falls back to JSON datatype on MySQL."),
 *   category = @Translation("Data"),
 *   default_widget = "json_textarea",
 *   default_formatter = "json",
 *   constraints = {"valid_json" = {}}*
 * )
 */
class NativeBinaryJSONItem extends NativeJSONItem {

  /**
   * {@inheritdoc}
   */
  public static function schema(FieldStorageDefinitionInterface $field_definition) {
    return array(
      'columns' => array(
        'value' => array(
          'type' => 'text',
          'pgsql_type' => 'jsonb',
          'mysql_type' => 'json',
          'not null' => FALSE,
        ),
      ),
    );
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
