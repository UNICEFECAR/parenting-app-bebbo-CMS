<?php

namespace Drupal\node_title_length;

use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\node\Entity\Node;
use Drupal\title_length\EntityTitleLength;

/**
 * Class to update the length of the node title.
 */
class NodeTitleLength extends EntityTitleLength {

  /**
   * {@inheritDoc}
   */
  public static function getEntityType(): string {
    return 'node';
  }

  /**
   * {@inheritDoc}
   */
  public static function getNameOfTitleField(): string {
    return 'title';
  }

  /**
   * {@inheritDoc}
   */
  public function getBaseFieldDefinitions(EntityTypeInterface $entity_type_definition): array {
    return Node::baseFieldDefinitions($entity_type_definition);
  }

}
