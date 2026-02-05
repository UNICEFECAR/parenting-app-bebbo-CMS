<?php

namespace Drupal\file_sanitizer\Commands;

use Drush\Commands\DrushCommands;
use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * Drush commands for re-saving nodes.
 */
class NodeTouchCommands extends DrushCommands {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * Constructs a NodeTouchCommands object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    parent::__construct();
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * Re-save nodes to update changed date for all translations.
   *
   * @command node:touch
   * @option nid Specific node ID to process.
   * @option limit Number of nodes to process (for testing).
   * @option type Content type machine name.
   * @usage node:touch --type=article --limit=10
   * @usage node:touch --nid=1123
   * @usage node:touch --nid=1123 --type=article
   */
  public function touchNodes(
    array $options = [
      'nid' => NULL,
      'limit' => NULL,
      'type' => NULL,
    ],
  ) {
    $nid = $options['nid'] ? (int) $options['nid'] : NULL;
    $limit = $options['limit'] ? (int) $options['limit'] : NULL;
    $type = $options['type'];

    $this->output()->writeln('<info>Starting node touch process...</info>');

    // Set flag to suppress email notifications during bulk operation.
    // phpcs:ignore DrupalPractice.Objects.GlobalDrupal.GlobalDrupal
    \Drupal::state()->set('node_touch_bulk_operation', TRUE);

    // Build entity query.
    $query = $this->entityTypeManager
      ->getStorage('node')
      ->getQuery()
      ->accessCheck(FALSE)
      ->condition('status', 1)
      ->sort('nid', 'ASC');

    if ($nid) {
      $query->condition('nid', $nid);
    }

    if ($type) {
      $query->condition('type', $type);
    }

    if ($limit && !$nid) {
      $query->range(0, $limit);
    }

    $nids = $query->execute();

    if (empty($nids)) {
      $this->output()->writeln('<comment>No nodes found.</comment>');
      // phpcs:ignore DrupalPractice.Objects.GlobalDrupal.GlobalDrupal
      \Drupal::state()->delete('node_touch_bulk_operation');
      return;
    }

    $message = sprintf(
      '<info>Processing %d node%s%s</info>',
      count($nids),
      count($nids) === 1 ? '' : 's',
      $type ? " of type {$type}" : ''
    );
    if ($nid) {
      $message = "<info>Processing node {$nid}</info>";
    }
    $this->output()->writeln($message);

    // Chunk size: safe for memory.
    $chunkSize = 50;
    $chunks = array_chunk($nids, $chunkSize);

    $count = 0;

    foreach ($chunks as $chunk) {
      /** @var \Drupal\node\NodeInterface[] $nodes */
      $nodes = $this->entityTypeManager
        ->getStorage('node')
        ->loadMultiple($chunk);

      foreach ($nodes as $node) {
        $processed_translations = [];
        foreach ($node->getTranslationLanguages() as $langcode => $language) {
          $translation = $node->getTranslation($langcode);

          // Only process published translations.
          if ($translation->isPublished()) {
            // Force update of changed time.
            // phpcs:ignore DrupalPractice.Objects.GlobalDrupal.GlobalDrupal
            $translation->setChangedTime(\Drupal::time()->getRequestTime());
            $translation->save();
            $processed_translations[] = $langcode;
          }
        }

        if (!empty($processed_translations)) {
          $count++;
          $this->output()->writeln(sprintf(
            'Processed node %d (languages: %s)',
            $node->id(),
            implode(', ', $processed_translations)
          ));
        }
      }

      // Progress output.
      $this->output()->writeln("Total processed: {$count} nodes...");
    }

    // Clear the flag after bulk operation is complete.
    // phpcs:ignore DrupalPractice.Objects.GlobalDrupal.GlobalDrupal
    \Drupal::state()->delete('node_touch_bulk_operation');

    $this->output()->writeln('<success>Node touch process completed.</success>');
  }

}
