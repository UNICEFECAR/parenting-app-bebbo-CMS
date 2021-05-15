<?php

/**
 * @file
 * Contains json_field.views.inc.
 */

use Drupal\field\FieldStorageConfigInterface;

/**
 * Implements hook_field_views_data().
 *
 * Views integration for json fields. Adds a data field to the default field data.
 *
 * @see field_views_field_default_views_data()
 */
function json_field_field_views_data(FieldStorageConfigInterface $field_storage) {
  // Use the JSONViews service to get the views data.
  return \Drupal::service('json_field.views')->getViewsFieldData($field_storage);
}
