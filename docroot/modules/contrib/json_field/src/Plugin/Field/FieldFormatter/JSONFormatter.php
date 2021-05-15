<?php

namespace Drupal\json_field\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Field\FieldItemListInterface;

/**
 * Plugin implementation of the 'json' formatter.
 *
 * @FieldFormatter(
 *   id = "json",
 *   label = @Translation("JSON"),
 *   field_types = {
 *     "json",
 *     "json_native",
 *     "json_native_binary",
 *   },
 *   quickedit = {
 *     "editor" = "plain_text"
 *   }
 * )
 */
class JSONFormatter extends FormatterBase {

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $elements = !empty($items) ? ['#attached' => ['library' => ['json_field/json_field.formatter']]] : [];

    foreach ($items as $delta => $item) {
      $elements[$delta] = [
        '#type' => 'json_text',
        '#text' => $item->value,
        '#langcode' => $langcode,
      ];
    }

    return $elements;
  }

}
