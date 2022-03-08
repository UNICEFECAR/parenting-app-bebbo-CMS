<?php

/**
 * @file
 * Post update functions for Feeds.
 */

use Drupal\Core\Config\Entity\ConfigEntityUpdater;
use Drupal\feeds\FeedTypeInterface;

/**
 * Replace deprecated action ID's for 'update_non_existent' setting.
 */
function feeds_post_update_actions_update_non_existent(&$sandbox = NULL) {
  $action_id_map = [
    'comment_delete_action' => 'entity:delete_action:comment',
    'comment_publish_action' => 'entity:publish_action:comment',
    'comment_unpublish_action' => 'entity:unpublish_action:comment',
    'comment_save_action' => 'entity:save_action:comment',
    'node_delete_action' => 'entity:delete_action:node',
    'node_publish_action' => 'entity:publish_action:node',
    'node_unpublish_action' => 'entity:unpublish_action:node',
    'node_save_action' => 'entity:save_action:node',
  ];
  \Drupal::classResolver(ConfigEntityUpdater::class)
    ->update($sandbox, 'feeds_feed_type', function (FeedTypeInterface $feed_type) use ($action_id_map) {
      $config = $feed_type->getProcessor()
        ->getConfiguration();
      if (isset($action_id_map[$config['update_non_existent']])) {
        $config['update_non_existent'] = $action_id_map[$config['update_non_existent']];
        $feed_type->getProcessor()
          ->setConfiguration($config);
        return TRUE;
      };
      return FALSE;
    });
}
