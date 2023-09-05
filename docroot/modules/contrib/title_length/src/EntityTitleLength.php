<?php

namespace Drupal\title_length;

use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Database;
use Drupal\Core\Database\Schema;
use Drupal\Core\Entity\EntityDefinitionUpdateManager;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Site\Settings;

/**
 * Class to update the length of the entity title.
 */
abstract class EntityTitleLength implements EntityTitleLengthInterface {

  /**
   * Entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  private EntityTypeManagerInterface $entityTypeManager;

  /**
   * Schema.
   *
   * @var \Drupal\Core\Database\Schema
   */
  private Schema $schema;

  /**
   * Update manager.
   *
   * @var \Drupal\Core\Entity\EntityDefinitionUpdateManager
   */
  private EntityDefinitionUpdateManager $updateManager;

  /**
   * Constructor of EntityTitleLength class.
   *
   * @param \Drupal\Core\Database\Connection $connection
   *   Database connection.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   Entity type manager.
   * @param \Drupal\Core\Entity\EntityDefinitionUpdateManager $update_manager
   *   Update Manager.
   */
  public function __construct(Connection $connection, EntityTypeManagerInterface $entity_type_manager, EntityDefinitionUpdateManager $update_manager) {
    $this->schema = $connection->schema();
    $this->entityTypeManager = $entity_type_manager;
    $this->updateManager = $update_manager;
  }

  /**
   * Get length of title field.
   *
   * @return int
   *   Length of title field.
   */
  public static function getLength(): int {
    return Settings::get(static::getEntityType() . '_title_length_chars') ?: EntityTitleLengthInterface::DEFAULT_LENGTH;
  }

  /**
   * {@inheritDoc}
   */
  public function changeLength(int $length): void {
    $this->schema->changeField(static::getEntityType() . '_field_data', static::getNameOfTitleField(), static::getNameOfTitleField(), [
      'length' => $length,
      'not null' => TRUE,
      'type' => 'varchar',
    ]);
    $this->schema->changeField(static::getEntityType() . '_field_revision', static::getNameOfTitleField(), static::getNameOfTitleField(), [
      'default' => NULL,
      'length' => $length,
      'type' => 'varchar',
    ]);
    // Update storage definition.
    $entity_type_definition = $this->entityTypeManager->getDefinition(static::getEntityType());
    /* @phpstan-ignore-next-line */
    $fields = $this->getBaseFieldDefinitions($entity_type_definition);
    $fields[static::getNameOfTitleField()]->setSetting('max_length', $length);
    $this->updateManager->installFieldStorageDefinition(static::getNameOfTitleField(), static::getEntityType(), static::getEntityType(), $fields[static::getNameOfTitleField()]);
  }

  /**
   * Check if exists entities with long titles.
   *
   * @param int|null $length
   *   Length.
   *
   * @return bool
   *   Exists or not.
   */
  public function checkIfExistEntitiesWithLongTitles(?int $length = self::ORIGINAL_LENGTH): bool {
    $length_function = 'char_length';
    $connection = Database::getConnection();
    switch ($connection->databaseType()) {
      case 'sqlite':
        $length_function = 'length';
        break;

      case 'sqlsrv':
        $length_function = 'len';
        break;
    }

    $query = $connection->select(static::getEntityType() . '_field_data', 't');
    $query->addField('t', static::getNameOfTitleField());
    $query->where("$length_function(" . static::getNameOfTitleField() . ") > $length");
    $query = $query->countQuery();
    $query = $query->execute();
    $long_title_count = $query ? (int) $query->fetchField() : 0;

    $query = $connection->select(static::getEntityType() . '_field_revision', 't');
    $query->addField('t', static::getNameOfTitleField());
    $query->where("$length_function(" . static::getNameOfTitleField() . ") > $length");
    $query = $query->countQuery();
    $query = $query->execute();
    $long_revision_title_count = $query ? (int) $query->fetchField() : 0;

    return $long_title_count + $long_revision_title_count > 0;
  }

}
