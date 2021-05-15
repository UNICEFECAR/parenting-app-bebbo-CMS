<?php

namespace Drupal\json_field;

use Drupal\field\FieldStorageConfigInterface;

/**
 * Class JSONViews.
 *
 * @package Drupal\json_field
 */
interface JSONViewsInterface {

  /**
   * Gets the views data for a field instance.
   *
   * @param \Drupal\field\FieldStorageConfigInterface $field_storage
   *
   * @return array
   *   The json field views data.
   */
  public function getViewsFieldData(FieldStorageConfigInterface $field_storage);

}
