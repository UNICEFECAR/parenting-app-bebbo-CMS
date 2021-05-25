<?php

/**
 * @file
 * Provide Views data for workflow.module.
 *
 * @ingroup views_module_handlers
 */

use Drupal\field\FieldStorageConfigInterface;

/**
 * Implements hook_field_views_data().
 */
function languagefield_field_views_data(FieldStorageConfigInterface $field_storage) {
  $data = views_field_default_views_data($field_storage);
  foreach ($data as $table_name => $table_data) {
    $data[$table_name][$field_storage->getName() . '_value']['filter']['id'] = 'languagefield';
  }

  return $data;
}
