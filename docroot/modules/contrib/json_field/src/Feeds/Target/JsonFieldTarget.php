<?php

namespace Drupal\json_field\Feeds\Target;

use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\feeds\FieldTargetDefinition;
use Drupal\feeds\Plugin\Type\Target\FieldTargetBase;

/**
 * Defines a json_field field mapper.
 *
 * @FeedsTarget(
 *   id = "json_field",
 *   field_types = {
 *     "json",
 *     "json_native",
 *     "json_native_binary"
 *   }
 * )
 */
class JsonFieldTarget extends FieldTargetBase {

  /**
   * {@inheritdoc}
   */
  protected static function prepareTarget(FieldDefinitionInterface $field_definition) {
    $definition = FieldTargetDefinition::createFromFieldDefinition($field_definition)
      ->addProperty('value');

    if (($field_definition->getType() === 'json_native') || ($field_definition->getType() === 'json_native_binary')) {
      $definition->markPropertyUnique('value');
    }

    return $definition;
  }

}
