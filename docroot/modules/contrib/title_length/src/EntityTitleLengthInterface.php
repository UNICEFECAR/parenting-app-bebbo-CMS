<?php

namespace Drupal\title_length;

use Drupal\Core\Entity\EntityTypeInterface;

/**
 * Class to update the length of the entity title.
 */
interface EntityTitleLengthInterface {

  /**
   * Default length of entity title field.
   */
  public const ORIGINAL_LENGTH = 255;

  /**
   * Default length of entity title field.
   */
  public const DEFAULT_LENGTH = 500;

  /**
   * Return entity type.
   *
   * @return string
   *   Entity type.
   */
  public static function getEntityType(): string;

  /**
   * Return name of entity title field.
   *
   * @return string
   *   Name of entity title field.
   */
  public static function getNameOfTitleField(): string;

  /**
   * Return entity base field definitions.
   *
   * @return array
   *   Entity base field definition.
   */
  public function getBaseFieldDefinitions(EntityTypeInterface $entity_type_definition): array;

  /**
   * Change length of entity title field.
   *
   * @param int $length
   *   Length.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function changeLength(int $length): void;

}
