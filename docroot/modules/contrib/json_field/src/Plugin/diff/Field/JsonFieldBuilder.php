<?php

declare(strict_types=1);

namespace Drupal\json_field\Plugin\diff\Field;

use Drupal\diff\FieldDiffBuilderBase;
use Drupal\Core\Field\FieldItemListInterface;

/**
 * Plugin to compare JSON fields.
 *
 * @FieldDiffBuilder(
 *   id = "json_field_diff_builder",
 *   label = @Translation("JSON Field Diff"),
 *   field_types = {
 *     "json",
 *     "json_native_binary",
 *     "json_native"
 *   },
 * )
 */
class JsonFieldBuilder extends FieldDiffBuilderBase {

  /**
   * {@inheritdoc}
   */
  public function build(FieldItemListInterface $field_items) {
    $result = [];

    /** @var \Drupal\Core\Field\FieldItemInterface $field_item */
    foreach ($field_items as $delta => $field_item) {
      if ($field_item->isEmpty()) {
        continue;
      }

      $result[$delta][] = $this->prettyPrintJson($field_item->value);
    }

    return $result;
  }

  /**
   * Add line breaks to JSON to make to it easier visualize the diff.
   *
   * @param string $json
   *   The json to make "pretty".
   *
   * @return string
   *   The "pretty" json.
   */
  protected function prettyPrintJson(string $json): string {
    try {
      return json_encode(
        json_decode(
          $json,
          FALSE,
          512,
          JSON_THROW_ON_ERROR
        ),
        JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT
      );
    }
    catch (\JsonException $e) {
      return $json;
    }
  }

}
