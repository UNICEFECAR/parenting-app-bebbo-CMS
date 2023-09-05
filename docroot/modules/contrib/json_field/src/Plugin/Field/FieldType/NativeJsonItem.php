<?php

namespace Drupal\json_field\Plugin\Field\FieldType;

use Drupal\Core\Field\FieldItemBase;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\TypedData\DataDefinition;

/**
 * Plugin implementation of the 'json' field type.
 *
 * @FieldType(
 *   id = "json_native",
 *   label = @Translation("JSON (raw)"),
 *   description = @Translation("Allows JSON data to be stored in the database. Stores the data in a JSON column in the database."),
 *   category = @Translation("Data"),
 *   default_widget = "json_textarea",
 *   default_formatter = "json",
 *   constraints = {"valid_json" = {}}
 * )
 */
class NativeJsonItem extends FieldItemBase {

  /**
   * {@inheritdoc}
   */
  public static function schema(FieldStorageDefinitionInterface $field_definition) {
    return [
      'columns' => [
        'value' => [
          'type' => 'json',
          'pgsql_type' => 'json',
          'mysql_type' => 'json',
          'sqlite_type' => 'text',
          'not null' => FALSE,
        ],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function isEmpty() {
    $value = $this->get('value')->getValue();
    return $value === NULL || $value === '';
  }

  /**
   * {@inheritdoc}
   */
  public static function propertyDefinitions(FieldStorageDefinitionInterface $field_definition) {
    $properties['value'] = DataDefinition::create('string')
      ->setLabel(t('JSON value'));

    return $properties;
  }

}
