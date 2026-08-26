<?php

declare(strict_types=1);

namespace Drupal\bebbo_serializer\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\Attribute\FieldFormatter;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Emits entity reference target IDs without loading the target entities.
 *
 * Core's entity_reference_entity_id formatter loads every referenced entity
 * and resolves its translation for the requested language before printing
 * the ID. On the API listings that means hundreds of node and term loads plus
 * a language-fallback pass per target, for output that is just the integer.
 */
#[FieldFormatter(
  id: 'bebbo_raw_target_id',
  label: new TranslatableMarkup('Raw target ID (no entity load)'),
  field_types: ['entity_reference'],
)]
final class RawTargetIdFormatter extends FormatterBase {

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode): array {
    $elements = [];
    foreach ($items as $delta => $item) {
      $elements[$delta] = ['#plain_text' => (string) $item->target_id];
    }
    return $elements;
  }

}
