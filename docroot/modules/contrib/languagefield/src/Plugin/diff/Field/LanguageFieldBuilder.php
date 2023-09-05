<?php

namespace Drupal\languagefield\Plugin\diff\Field;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\diff\Plugin\diff\Field\CoreFieldBuilder;

/**
 * Plugin to compare schedules.
 *
 * @FieldDiffBuilder(
 *   id = "languagefield_diff_builder",
 *   label = @Translation("Language Field Diff"),
 *   field_types = {
 *     "language_field",
 *   },
 * )
 */
class LanguageFieldBuilder extends CoreFieldBuilder {

  /**
   * {@inheritdoc}
   */
  public function build(FieldItemListInterface $field_items) {
    $result = [];

    // Every item from $field_items is of type FieldItemInterface.
    foreach ($field_items as $field_key => $field_item) {
      if (!$field_item->isEmpty()) {
        $values = $field_item->getValue();
        if (isset($values['value'])) {
          $value = $field_item->view(['label' => 'hidden']);
          $result[$field_key][] = $this->renderer->renderPlain($value);
        }
      }
    }

    return $result;
  }

}
