<?php

/**
 * @file
 * Post update functions for the Custom Article module.
 */

use Drupal\Core\Cache\Cache;

/**
 * Sets "Do not feature" to 0 on articles that have no stored value.
 */
function custom_article_post_update_set_empty_do_not_feature(): string {
  $database = \Drupal::database();

  // The field is untranslatable, so its value is stored only for the default
  // translation of each revision. Fill the current revision and any newer
  // draft, so publishing a draft later does not bring the empty value back.
  $query = $database->select('node_field_revision', 'r');
  $query->join('node', 'n', 'n.nid = r.nid AND r.vid >= n.vid');
  $query->leftJoin('node_revision__field_do_not_feature', 'f', 'f.revision_id = r.vid AND f.langcode = r.langcode AND f.deleted = 0');
  $query->fields('r', ['nid', 'vid', 'langcode']);
  $query->condition('n.type', 'article');
  $query->condition('r.default_langcode', 1);
  $query->isNull('f.revision_id');
  $missing_revisions = $query->execute()->fetchAll();

  $query = $database->select('node_field_data', 'd');
  $query->leftJoin('node__field_do_not_feature', 'f', 'f.entity_id = d.nid AND f.langcode = d.langcode AND f.deleted = 0');
  $query->fields('d', ['nid', 'vid', 'langcode']);
  $query->condition('d.type', 'article');
  $query->condition('d.default_langcode', 1);
  $query->isNull('f.entity_id');
  $missing_current = $query->execute()->fetchAll();

  if (!$missing_revisions && !$missing_current) {
    return 'No articles without a "Do not feature" value.';
  }

  $transaction = $database->startTransaction();
  foreach ($missing_revisions as $row) {
    $database->insert('node_revision__field_do_not_feature')
      ->fields([
        'bundle' => 'article',
        'deleted' => 0,
        'entity_id' => $row->nid,
        'revision_id' => $row->vid,
        'langcode' => $row->langcode,
        'delta' => 0,
        'field_do_not_feature_value' => 0,
      ])
      ->execute();
  }
  foreach ($missing_current as $row) {
    $database->insert('node__field_do_not_feature')
      ->fields([
        'bundle' => 'article',
        'deleted' => 0,
        'entity_id' => $row->nid,
        'revision_id' => $row->vid,
        'langcode' => $row->langcode,
        'delta' => 0,
        'field_do_not_feature_value' => 0,
      ])
      ->execute();
  }
  unset($transaction);

  $nids = array_unique(array_merge(
    array_column($missing_revisions, 'nid'),
    array_column($missing_current, 'nid'),
  ));
  \Drupal::entityTypeManager()->getStorage('node')->resetCache($nids);

  // Expire the affected nodes and the article API listings in every language
  // they are translated into.
  $langcodes = $database->select('node_field_data', 'd')
    ->fields('d', ['langcode'])
    ->condition('d.nid', $nids, 'IN')
    ->distinct()
    ->execute()
    ->fetchCol();
  $tags = array_map(static fn($nid): string => 'node:' . $nid, $nids);
  foreach ($langcodes as $langcode) {
    $tags[] = 'bebbo_api_list:article:' . $langcode;
  }
  Cache::invalidateTags($tags);

  return sprintf('Set "Do not feature" to 0 on %d article(s): %s.', count($nids), implode(', ', $nids));
}
