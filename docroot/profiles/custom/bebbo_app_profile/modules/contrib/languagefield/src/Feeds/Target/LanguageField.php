<?php

namespace Drupal\languagefield\Feeds\Target;

use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\feeds\FieldTargetDefinition;
use Drupal\feeds\Plugin\Type\Target\ConfigurableTargetInterface;
use Drupal\feeds\Plugin\Type\Target\FieldTargetBase;

/**
 * Defines a language field mapper.
 *
 * @FeedsTarget(
 *   id = "language_field",
 *   field_types = {
 *     "language_field"
 *   }
 * )
 */
class LanguageField extends FieldTargetBase implements ConfigurableTargetInterface {

  /**
   * {@inheritdoc}
   */
  protected static function prepareTarget(FieldDefinitionInterface $field_definition) {
    return FieldTargetDefinition::createFromFieldDefinition($field_definition)
      ->addProperty('value');
  }

  /**
   * {@inheritdoc}
   */
  protected function prepareValue($delta, array &$values) {
    $field_definition = $this->targetDefinition->getFieldDefinition()->getFieldStorageDefinition();
    $allowed_values = languagefield_allowed_values($field_definition);
    $value = trim($values['value']);
    $lang_index = array_search($value, $allowed_values);
    if ($lang_index) {
      $value = $lang_index;
    }
    $values['value'] = (string) $value;
  }

}
