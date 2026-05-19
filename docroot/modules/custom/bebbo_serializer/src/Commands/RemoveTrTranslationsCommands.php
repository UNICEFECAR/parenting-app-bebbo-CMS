<?php

namespace Drupal\bebbo_serializer\Commands;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\TranslatableInterface;
use Drush\Commands\DrushCommands;

/**
 * Drush command to remove Turkish (tr) translations from Bebbo default site.
 */
class RemoveTrTranslationsCommands extends DrushCommands {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected Connection $database;

  /**
   * Constructs a RemoveTrTranslationsCommands object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, Connection $database) {
    parent::__construct();
    $this->entityTypeManager = $entity_type_manager;
    $this->database = $database;
  }

  /**
   * Remove all Turkish (tr) translations from content entities.
   *
   * @param array $options
   *   Command options.
   *
   * @command bebbo:remove-tr-translations
   * @aliases rm-tr
   * @option execute Actually perform deletions. Without this flag, runs dry-run.
   * @option force-delete-default Delete entities where TR is the default language.
   * @option entity-type Limit to specific entity type (node, taxonomy_term, media).
   * @option batch-size Number of entities per batch.
   * @usage bebbo:remove-tr-translations
   *   Dry-run: shows what would be removed.
   * @usage bebbo:remove-tr-translations --execute
   *   Remove all TR translations (skip default-language entities).
   * @usage bebbo:remove-tr-translations --execute --force-delete-default
   *   Remove all TR translations and delete entities where TR is default.
   */
  public function removeTrTranslations(
    array $options = [
      'execute' => FALSE,
      'force-delete-default' => FALSE,
      'entity-type' => '',
      'batch-size' => 50,
    ],
  ) {
    $dryRun = !$options['execute'];
    $forceDeleteDefault = $options['force-delete-default'];
    $batchSize = (int) $options['batch-size'];
    $limitEntityType = $options['entity-type'];

    $mode = $dryRun ? 'DRY-RUN' : 'EXECUTE';
    $this->logger()->notice("=== Remove TR Translations [{$mode}] ===");

    $entityTypes = [
      'node' => 'Content',
      'taxonomy_term' => 'Taxonomy terms',
      'media' => 'Media',
    ];

    if ($limitEntityType && isset($entityTypes[$limitEntityType])) {
      $entityTypes = [$limitEntityType => $entityTypes[$limitEntityType]];
    }
    elseif ($limitEntityType) {
      $this->logger()->error("Unknown entity type: {$limitEntityType}. Use: node, taxonomy_term, media.");
      return;
    }

    $totals = [
      'translations_removed' => 0,
      'entities_deleted' => 0,
      'skipped_default' => 0,
      'errors' => 0,
    ];

    foreach ($entityTypes as $entityTypeId => $label) {
      $this->logger()->notice("--- Processing {$label} ({$entityTypeId}) ---");
      $result = $this->processEntityType($entityTypeId, $dryRun, $forceDeleteDefault, $batchSize);
      $totals['translations_removed'] += $result['translations_removed'];
      $totals['entities_deleted'] += $result['entities_deleted'];
      $totals['skipped_default'] += $result['skipped_default'];
      $totals['errors'] += $result['errors'];
    }

    // Handle path aliases.
    $this->logger()->notice("--- Processing Path aliases ---");
    $aliasResult = $this->processPathAliases($dryRun);
    $totals['translations_removed'] += $aliasResult['removed'];

    // Summary.
    $this->logger()->notice("=== Summary [{$mode}] ===");
    $this->logger()->notice("Translations removed: {$totals['translations_removed']}");
    $this->logger()->notice("Entities deleted (TR default): {$totals['entities_deleted']}");
    $this->logger()->notice("Skipped (TR default, no --force-delete-default): {$totals['skipped_default']}");
    $this->logger()->notice("Errors: {$totals['errors']}");
  }

  /**
   * Process a single entity type: remove TR translations or delete.
   */
  protected function processEntityType(string $entityTypeId, bool $dryRun, bool $forceDeleteDefault, int $batchSize): array {
    $result = [
      'translations_removed' => 0,
      'entities_deleted' => 0,
      'skipped_default' => 0,
      'errors' => 0,
    ];

    $storage = $this->entityTypeManager->getStorage($entityTypeId);
    $entityType = $this->entityTypeManager->getDefinition($entityTypeId);
    $dataTable = $entityType->getDataTable();

    if (!$dataTable) {
      $this->logger()->warning("No data table for {$entityTypeId}, skipping.");
      return $result;
    }

    $idKey = $entityType->getKey('id');
    $connection = $this->database;

    $ids = $connection->select($dataTable, 'd')
      ->fields('d', [$idKey])
      ->condition('d.langcode', 'tr')
      ->execute()
      ->fetchCol();

    $ids = array_unique($ids);
    $total = count($ids);

    if ($total === 0) {
      $this->logger()->notice("No TR translations found.");
      return $result;
    }

    $this->logger()->notice("Found {$total} entities with TR translations.");

    // Group by bundle for reporting.
    $bundleKey = $entityType->getKey('bundle');
    if ($bundleKey) {
      $bundleCounts = $connection->select($dataTable, 'd')
        ->fields('d', [$bundleKey])
        ->condition('d.langcode', 'tr')
        ->groupBy("d.{$bundleKey}")
        ->execute()
        ->fetchAllKeyed(0, 0);

      // Recount properly.
      $bundleCounts = [];
      $bundleQuery = $connection->select($dataTable, 'd');
      $bundleQuery->fields('d', [$bundleKey]);
      $bundleQuery->addExpression("COUNT(*)", 'cnt');
      $bundleQuery->condition('d.langcode', 'tr');
      $bundleQuery->groupBy("d.{$bundleKey}");
      $bundleRows = $bundleQuery->execute()->fetchAll();
      foreach ($bundleRows as $row) {
        $bundleCounts[$row->{$bundleKey}] = $row->cnt;
      }
      foreach ($bundleCounts as $bundle => $count) {
        $this->logger()->notice("  {$bundle}: {$count}");
      }
    }

    $batches = array_chunk($ids, $batchSize);

    foreach ($batches as $batchIndex => $batchIds) {
      $entities = $storage->loadMultiple($batchIds);

      foreach ($entities as $entity) {
        if (!$entity instanceof TranslatableInterface) {
          continue;
        }

        $entityId = $entity->id();
        $entityLabel = $entity->label() ?? '(no label)';

        try {
          if (!$entity->hasTranslation('tr')) {
            continue;
          }

          $isDefault = $entity->getTranslation('tr')->isDefaultTranslation();

          if ($isDefault) {
            if ($forceDeleteDefault) {
              if ($dryRun) {
                $this->logger()->notice("[DRY-RUN] Would DELETE {$entityTypeId} {$entityId}: \"{$entityLabel}\" (TR is default language)");
              }
              else {
                $entity->delete();
                $this->logger()->notice("DELETED {$entityTypeId} {$entityId}: \"{$entityLabel}\" (TR was default language)");
              }
              $result['entities_deleted']++;
            }
            else {
              $this->logger()->warning("SKIPPED {$entityTypeId} {$entityId}: \"{$entityLabel}\" — TR is default language. Use --force-delete-default to remove.");
              $result['skipped_default']++;
            }
            continue;
          }

          if ($dryRun) {
            $this->logger()->notice("[DRY-RUN] Would remove TR translation from {$entityTypeId} {$entityId}: \"{$entityLabel}\"");
          }
          else {
            $entity->removeTranslation('tr');
            $entity->save();
            $this->logger()->notice("Removed TR translation from {$entityTypeId} {$entityId}");
          }
          $result['translations_removed']++;
        }
        catch (\Exception $e) {
          $this->logger()->error("Error processing {$entityTypeId} {$entityId}: " . $e->getMessage());
          $result['errors']++;
        }
      }

      $processed = min(($batchIndex + 1) * $batchSize, $total);
      $this->logger()->notice("Progress: {$processed}/{$total}");
    }

    return $result;
  }

  /**
   * Remove TR path aliases.
   */
  protected function processPathAliases(bool $dryRun): array {
    $result = ['removed' => 0];
    $connection = $this->database;

    $aliases = $connection->select('path_alias', 'pa')
      ->fields('pa', ['id', 'path', 'alias', 'langcode'])
      ->condition('pa.langcode', 'tr')
      ->execute()
      ->fetchAll();

    $count = count($aliases);

    if ($count === 0) {
      $this->logger()->notice("No TR path aliases found.");
      return $result;
    }

    $this->logger()->notice("Found {$count} TR path aliases.");

    $storage = $this->entityTypeManager->getStorage('path_alias');

    foreach ($aliases as $alias) {
      if ($dryRun) {
        $this->logger()->notice("[DRY-RUN] Would delete path alias {$alias->id}: {$alias->alias} -> {$alias->path}");
      }
      else {
        $pathAlias = $storage->load($alias->id);
        if ($pathAlias) {
          $pathAlias->delete();
          $this->logger()->notice("Deleted path alias {$alias->id}: {$alias->alias}");
        }
      }
      $result['removed']++;
    }

    return $result;
  }

}
