<?php

namespace Drupal\json_field;

use Drupal\field\FieldStorageConfigInterface;

/**
 * Class JSONViews.
 *
 * @package Drupal\json_field
 */
interface JsonViewsInterface {

  /**
   * Gets the views data for a field instance.
   *
   * @param \Drupal\field\FieldStorageConfigInterface $field_storage
   *   The field storage config entity.
   *
   * @return array
   *   The JSON field views data.
   */
  public function getViewsFieldData(FieldStorageConfigInterface $field_storage);

}
