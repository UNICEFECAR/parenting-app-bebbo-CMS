<?php

namespace Drupal\json_field\Normalizer;

use Drupal\json_field\Plugin\Field\FieldType\NativeJsonItem;
use Drupal\serialization\Normalizer\NormalizerBase;

/**
 * Converts values for JsonItemNormalizer to and from common formats for hal.
 *
 * Overrides FieldItemNormalizer to prevent treating JSON strings as strings
 * that need to be escaped.
 */
class JsonItemNormalizer extends NormalizerBase {

  /**
   * {@inheritdoc}
   */
  protected $supportedInterfaceOrClass = NativeJsonItem::class;

  /**
   * {@inheritdoc}
   */
  public function normalize($object, $format = NULL, array $context = []) {
    $field = $object->getParent();
    return [
      $field->getName() => [$object->getValue()],
    ];
  }

}
