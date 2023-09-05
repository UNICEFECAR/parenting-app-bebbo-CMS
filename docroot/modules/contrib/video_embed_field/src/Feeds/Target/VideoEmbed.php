<?php

namespace Drupal\video_embed_field\Feeds\Target;

use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\feeds\FieldTargetDefinition;
use Drupal\feeds\Feeds\Target\StringTarget;

/**
 * Defines a Video Embed Field mapper.
 *
 * @FeedsTarget(
 *   id = "video_embed_field",
 *   field_types = {"video_embed_field"}
 * )
 */
class VideoEmbed extends StringTarget {

  /**
   * {@inheritdoc}
   */
  protected static function prepareTarget(FieldDefinitionInterface $field_definition) {
    return FieldTargetDefinition::createFromFieldDefinition($field_definition)
      ->addProperty('value')
      ->markPropertyUnique('value');
  }

}
