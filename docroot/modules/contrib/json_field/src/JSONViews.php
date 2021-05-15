<?php

namespace Drupal\json_field;

use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\field\FieldStorageConfigInterface;

/**
 * Class JSONViews.
 *
 * @package Drupal\json_field
 */
class JSONViews implements JSONViewsInterface {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public function getViewsFieldData(FieldStorageConfigInterface $field_storage) {
    // Make sure views.views.inc is loaded.
    module_load_include('inc', 'views', 'views.views');

    // Get the default data from the views module.
    $data = views_field_default_views_data($field_storage);

    $field_name = $field_storage->getName();
    $value_field_name = $field_name . '_value';
    $entity_entry = $field_storage->getTargetEntityTypeId() . '__' . $field_name;

    if (!empty($data[$entity_entry][$value_field_name])) {
      $data[$entity_entry][$field_name . '_json_value'] = [
        'group' => $data[$entity_entry][$value_field_name]['group'],
        'title' => $this->t('@value_title (data)', [
          '@value_title' => $data[$entity_entry][$value_field_name]['title'],
        ]),
        'title short' => $data[$entity_entry][$value_field_name]['title short'],
        'help' => $data[$entity_entry][$value_field_name]['help'],
        'field' => $data[$entity_entry][$field_name]['field'],
      ];
      $data[$entity_entry][$field_name . '_json_value']['field']['id'] = 'json_data';
    }

    return $data;
  }

}
