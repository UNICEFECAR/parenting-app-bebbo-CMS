<?php

namespace Drupal\json_field\Plugin\Field\FieldType;

use Drupal\Component\Utility\Random;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemBase;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\TypedData\DataDefinition;

/**
 * Plugin implementation of the 'JSON' field type.
 *
 * @FieldType(
 *   id = "json",
 *   label = @Translation("JSON"),
 *   description = @Translation("This field stores JSON text."),
 *   category = @Translation("Data"),
 *   default_widget = "json_textarea",
 *   default_formatter = "json",
 *   constraints = {"valid_json" = {}}
 * )
 */
class JSONItem extends FieldItemBase {

  /**
   * Schema API 255 varchar.
   */
  const SIZE_SMALL = 255;

  /**
   * Schema API normal text 16KB (16*2^10).
   */
  const SIZE_NORMAL = 16384;

  /**
   * Schema API medium text 16MB (16*2^20).
   */
  const SIZE_MEDIUM = 16777216;

  /**
   * Schema API big text 4GB (4*2^30).
   */
  const SIZE_BIG = 4294967296;

  /**
   * {@inheritdoc}
   */
  public static function defaultStorageSettings() {
    return array(
      'size' => static::SIZE_BIG,
    ) + parent::defaultStorageSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function storageSettingsForm(array &$form, FormStateInterface $form_state, $has_data) {
    $elements = parent::storageSettingsForm($form, $form_state, $has_data);

    $elements['size'] = [
      '#type' => 'select',
      '#title' => $this->t('Maximum size'),
      '#default_value' => $this->getSetting('size'),
      '#options' => [
        static::SIZE_SMALL => t('255 Characters'),
        static::SIZE_NORMAL => t('64 KB'),
        static::SIZE_MEDIUM => t('16 MB'),
        static::SIZE_BIG => t('4 GB'),
      ],
    ];

    return $elements;
  }

  /**
   * {@inheritdoc}
   */
  public static function propertyDefinitions(FieldStorageDefinitionInterface $field_definition) {
    $properties['value'] = DataDefinition::create('string')
      ->setLabel(t('JSON Value'))
      ->setRequired(TRUE);
    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  public static function schema(FieldStorageDefinitionInterface $field_definition) {
    $schema['columns']['value'] = [];

    $size = $field_definition->getSetting('size');
    switch ($size) {
      case static::SIZE_SMALL:
        $schema['columns']['value']['type'] = 'varchar';
        $schema['columns']['value']['length'] = static::SIZE_SMALL;
        break;

      // We use utf8mb4 so the maximum length is size / 4, so we cannot use type
      // 'varchar' with size of 65535.
      case static::SIZE_NORMAL:
        $schema['columns']['value']['type'] = 'text';
        $schema['columns']['value']['size'] = 'normal';
        break;

      case static::SIZE_MEDIUM:
        $schema['columns']['value']['type'] = 'text';
        $schema['columns']['value']['size'] = 'medium';
        break;

      case static::SIZE_BIG:
        $schema['columns']['value']['type'] = 'text';
        $schema['columns']['value']['size'] = 'big';
        break;
    }

    return $schema;
  }

  /**
   * Calculates max character length for a field value.
   */
  public function getMaxLength() {
    $size = $this->getSetting('size');
    switch ($size) {

      // Varchar columns.
      case static::SIZE_SMALL:
        return static::SIZE_SMALL;

      // Text columns -- we use utf8mb4 so the maximum length is size / 4.
      default:
        return floor($size / 4);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getConstraints() {
    $constraint_manager = \Drupal::typedDataManager()->getValidationConstraintManager();
    $constraints = parent::getConstraints();

    $max_length = $this->getMaxLength();
    $constraints[] = $constraint_manager->create('ComplexData', array(
      'value' => [
        'Length' => [
          'max' => $max_length,
          'maxMessage' => t('%name: the text may not be longer than @max characters.', array('%name' => $this->getFieldDefinition()->getLabel(), '@max' => $max_length)),
        ],
      ],
    ));

    return $constraints;
  }

  /**
   * {@inheritdoc}
   */
  public static function generateSampleValue(FieldDefinitionInterface $field_definition) {
    $random = new Random();
    $values['value'] = '{"foo": "' . $random->word(mt_rand(1, 2000)) . '""}';
    return $values;
  }

  /**
   * {@inheritdoc}
   */
  public function isEmpty() {
    $value = $this->get('value')->getValue();
    return $value === NULL || $value === '';
  }

}
