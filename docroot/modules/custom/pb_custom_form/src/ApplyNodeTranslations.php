<?php

namespace Drupal\pb_custom_form;

use Drupal\node\Entity\Node;

/**
 * Parses and verifies the doc comments for files.
 */
class ApplyNodeTranslations {

  /**
   * Initializes batch processing.
   */
  public static function initiateBatchProcessing($type) {
    $items = self::getNodeIdsForBatch($type);

    if (empty($items)) {
      return;
    }
    // Start a batch process.
    $operation_callback = [
          ['\Drupal\pb_custom_form\ApplyNodeTranslations::operationCallback', [$items]],
    ];
    $batch = [
      'title' => t('Applying related articles and video articles in English content to all translations'),
      'operations' => $operation_callback,
      'finished' => '\Drupal\pb_custom_form\ApplyNodeTranslations::FinishedCallback',
    ];

    batch_set($batch);
  }

  /**
   * Process callback for the batch set in the TriggerBatchForm form.
   *
   * @param array $items
   *   The items to process.
   * @param array $context
   *   Reference to the batch context array.
   */
  public static function operationCallback(array $items, &$context) {
    // Context sandbox is empty on initial load. Here we take care of things
    // that need to be done once only. This context is then subsequently
    // available for every subsequent batch run.
    if (empty($context['sandbox'])) {
      $context['sandbox']['progress'] = 0;
      $context['sandbox']['errors'] = [];
      $context['sandbox']['max'] = count($items);
    }

    // If we have nothing to process, mark the batch as 100% complete (0 = not
    // started, eg 0.5 = 50% completed, 1 = 100% completed).
    if (!$context['sandbox']['max']) {
      $context['finished'] = 1;
      return;
    }

    // If we haven't yet processed all.
    if ($context['sandbox']['progress'] < $context['sandbox']['max']) {

      // This is a counter that is passed from batch run to batch run.
      if (isset($items[$context['sandbox']['progress']])) {
        $node = Node::load($items[$context['sandbox']['progress']]);
        if (!empty($node->get('field_related_articles')->target_id)) {
          $target_ids = $node->get('field_related_articles')->getvalue();
          foreach ($target_ids as $target_id) {
            $entity = Node::load($target_id['target_id']);
            $translation_nodes = $node->getTranslationLanguages();
            foreach ($translation_nodes as $translation_node) {
              $lang = $translation_node->getId();
              if ($entity->hasTranslation($lang)) {
                $trans_node_target_ids = $node->getTranslation($lang)->field_related_articles->getvalue();
                $trans_target_ids = [];
                foreach ($trans_node_target_ids as $trans_node_target_id) {
                  $trans_target_ids[] = $trans_node_target_id['target_id'];
                }
                if (!in_array($target_id['target_id'], $trans_target_ids)) {
                  $node->getTranslation($lang)->field_related_articles->appendItem(['target_id' => $target_id['target_id']]);
                  $node->save();
                }
                // unset($trans_target_ids);
              }
            }

          }
        }
        if (!empty($node->get('field_related_video_articles')->target_id)) {
          $video_target_ids = $node->get('field_related_video_articles')->getvalue();
          foreach ($video_target_ids as $video_target_id) {
            $video_entity = Node::load($video_target_id['target_id']);
            $video_translation_nodes = $node->getTranslationLanguages();
            foreach ($video_translation_nodes as $video_translation_node) {
              $video_lang = $video_translation_node->getId();
              if ($video_entity->hasTranslation($video_lang)) {
                $video_trans_node_target_ids = $node->getTranslation($video_lang)->field_related_video_articles->getvalue();
                $video_trans_target_ids = [];
                foreach ($video_trans_node_target_ids as $video_trans_node_target_id) {
                  $video_trans_target_ids[] = $video_trans_node_target_id['target_id'];
                }
                if (!in_array($video_target_id['target_id'], $video_trans_target_ids)) {
                  $node->getTranslation($video_lang)->field_related_video_articles->appendItem(['target_id' => $video_target_id['target_id']]);
                  $node->save();
                }
                // unset($video_trans_target_ids);
              }
            }

          }
        }
        $context['message'] = t('[@percentage] Updating "@item" (@id)', [
          '@percentage' => 'Completed ' . ($context['sandbox']['max'] - $context['sandbox']['progress']) . ' of ' . $context['sandbox']['max'],
          '@item' => $node->label(),
          '@id' => 'ID - ' . $node->id(),
        ]);

      }

      $context['sandbox']['progress']++;
      $context['results']['items'][] = $items[$context['sandbox']['progress']];

      // Results are passed to the finished callback.
      // $context['results']['items'][] =
      // $items[$context['sandbox']['progress']];.
    }

    // When progress equals max, finished is '1' which means completed. Any
    // decimal between '0' and '1' is used to determine the percentage of
    // the progress bar.
    $context['finished'] = $context['sandbox']['progress'] / $context['sandbox']['max'];
  }

  /**
   * Batch finished callback.
   *
   * @param bool $success
   *   Batch encountered errors or not.
   * @param array $results
   *   The processed chapters.
   * @param array $operations
   *   The different batches that were run.
   */
  public static function finishedCallback($success, array $results, array $operations) {
    if ($success && !empty($results)) {

      // The 'success' parameter means no fatal PHP errors were detected.
      $message = t('@count publications were updated.', [
        '@count' => count($results['items']),
      ]);
      \Drupal::messenger()->addStatus($message);
    }
    else {

      // A fatal error occurred.
      $message = t('No pending update');
      \Drupal::messenger()->addWarning($message);
    }
  }

  /**
   * Get all nodes from specific content type.
   */
  public static function getNodeIdsForBatch($type) {
    $nids = \Drupal::database()->select('node', 'n')
      ->fields('n', ['nid'])
      ->condition('type', $type, '=')
      ->execute()
      ->fetchCol();
    return $nids;
  }

}
