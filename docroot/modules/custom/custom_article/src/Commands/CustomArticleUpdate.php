<?php

namespace Drupal\custom_article\Commands;

use Drush\Commands\DrushCommands;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * A Drush commandfile for updating article nodes.
 */
class CustomArticleUpdate extends DrushCommands {

  /**
   * The entity type manager service.
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
   * Constructs a new CustomArticleUpdate command.
   */
  public function __construct() {
    parent::__construct();
  }

  /**
   * Get the entity type manager service.
   */
  protected function getEntityTypeManager(): EntityTypeManagerInterface {
    if (!isset($this->entityTypeManager)) {
      $this->entityTypeManager = \Drupal::entityTypeManager();
    }
    return $this->entityTypeManager;
  }

  /**
   * Get the database connection service.
   */
  protected function getDatabase(): Connection {
    if (!isset($this->database)) {
      $this->database = \Drupal::database();
    }
    return $this->database;
  }

  /**
   * Updates article nodes by copying a source field into a target field.
   *
   * @command custom-article:custom-article-update
   * @aliases custom-article-update
   */
  public function updateFields(): void {
    $source_field = 'field_suggest_as_daily_reads';
    $target_field = 'field_do_not_feature';

    // Fetch all article node IDs.
    $query = $this->getDatabase()->select('node_field_data', 'nfd')
      ->fields('nfd', ['nid'])
      ->condition('nfd.type', 'article')
      ->condition('nfd.default_langcode', 1)
      ->condition('nfd.langcode', 'en', '<>')
      ->condition('nfd.status', 1)
      ->orderBy('nfd.nid', 'ASC');

    $nids = $query->execute()->fetchCol();

    if (empty($nids)) {
      $this->logger()->warning('No articles found.');
      return;
    }

    $node_storage = $this->getEntityTypeManager()->getStorage('node');
    /** @var \Drupal\node\NodeInterface[] $nodes */
    $nodes = $node_storage->loadMultiple($nids);

    foreach ($nodes as $node) {
      $this->output()->writeln('Processing node ID: ' . $node->id());

      if ($node->hasField($source_field) && $node->hasField($target_field)) {
        $source_value = $node->get($source_field)->value;
        $node->set($target_field, !$source_value);
        $node->save();
      }
    }

    $this->logger()->success('Updated ' . count($nodes) . ' articles.');
  }

}
