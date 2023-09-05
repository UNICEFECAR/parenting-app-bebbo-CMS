<?php

namespace Drupal\taxonomy_term_title_length;

use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\taxonomy\Entity\Term;
use Drupal\title_length\EntityTitleLength;

/**
 * Class to update the length of the taxonomy term name.
 */
class TaxonomyTermTitleLength extends EntityTitleLength {

  /**
   * {@inheritDoc}
   */
  public static function getEntityType(): string {
    return 'taxonomy_term';
  }

  /**
   * {@inheritDoc}
   */
  public static function getNameOfTitleField(): string {
    return 'name';
  }

  /**
   * {@inheritDoc}
   */
  public function getBaseFieldDefinitions(EntityTypeInterface $entity_type_definition): array {
    return Term::baseFieldDefinitions($entity_type_definition);
  }

}
