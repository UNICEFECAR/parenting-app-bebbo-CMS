<?php

namespace Drupal\symfony_mailer;

use Drupal\Core\Entity\EntityListBuilderInterface;

/**
 * Defines an interface to build Mailer Policy entity listings.
 */
interface MailerPolicyListBuilderInterface extends EntityListBuilderInterface {

  /**
   * Overrides the entities to display.
   *
   * @param string[] $entity_ids
   *   An array entity IDs.
   *
   * @return $this
   */
  public function overrideEntities(array $entity_ids);

  /**
   * Filters the entities to a specific type.
   *
   * @param string $type
   *   The email type show show.
   *
   * @return $this
   */
  public function filterType(string $type);

  /**
   * Hides columns in the output.
   *
   * @param string[] $columns
   *   The columns to hide.
   *
   * @return $this
   */
  public function hideColumns(array $columns);

}
