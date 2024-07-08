<?php

namespace Drupal\custom_article\Commands;

use Drush\Commands\DrushCommands;
use Drupal\node\Entity\Node;
/**
 * A Drush commandfile.
 */
class CustomArticleUpdate extends DrushCommands {

  /**
   * @command custom-article:custom-article-update
   * @aliases custom-article-update
   */
  public function updateFields() {
    // Define the source and target fields.
    $source_field = 'field_suggest_as_daily_reads';
    $target_field = 'field_do_not_feature';

    // Fetch all article nodes.
    $nids = \Drupal::entityQuery('node')
      ->condition('type', 'article')
      ->condition('nid', '6221')
      ->accessCheck(FALSE)
      ->execute();

    $nodes = Node::loadMultiple($nids);

    foreach ($nodes as $node) {
      // Log or print the NID before processing.
      $this->output()->writeln('Processing node ID: ' . $node->id());

      if ($node->hasField($source_field) && $node->hasField($target_field)) {
        $source_value = $node->get($source_field)->value;
        $target_value = !$source_value;
        $node->set($target_field, $target_value);
        $node->save();
      }
    }

    $this->logger()->success(dt('Updated @count articles.', ['@count' => count($nodes)]));
  }

}
