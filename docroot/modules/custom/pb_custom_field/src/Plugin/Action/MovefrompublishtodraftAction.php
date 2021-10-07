<?php

namespace Drupal\pb_custom_field\Plugin\Action;

use Drupal\views_bulk_operations\Action\ViewsBulkOperationsActionBase;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\node\Entity\Node;

/**
 * Action description.
 *
 * @Action(
 *   id = "pb_custom_field_publish_to_draft",
 *   label = @Translation("Publish to Archive then Draft"),
 *   type = "node",
 *   confirm = FALSE
 * )
 */
class MovefrompublishtodraftAction extends ViewsBulkOperationsActionBase {

  use StringTranslationTrait;
  /**
   * Get the total translated count.
   *
   * @var int
   */
  public $initial = 0;
  /**
   * Get the total translated count.
   *
   * @var int
   */
  public $assigned = 0;
  /**
   * Get the total non translated count.
   *
   * @var int
   */
  public $nonAssigned = 0;
  /**
   * Get the total items processed.
   *
   * @var int
   */
  public $processItem = 0;

  /**
   * {@inheritdoc}
   */
  public function execute(ContentEntityInterface $entity = NULL) {
    $uid = \Drupal::currentUser()->id();
    $this->initial = $this->initial + 1;
    $this->processItem = $this->processItem + 1;
    $list = $this->context['list'];
    $list_count = count($list);
    $message = "";
    $error_message = "";
    $current_language = $entity->get('langcode')->value;
    $nid = $entity->get('nid')->getString();
    $archive_node = node_load($nid);
    $ids = array_column($list, '0');
    $all_ids = implode(',', $ids);
    $node_lang_archive = $archive_node->getTranslation($current_language);
    $current_state = $node_lang_archive->moderation_state->value;
    if ($current_state == 'published') {
      /* Change status from publish to archive. */
      $uid = \Drupal::currentUser()->id();
      $node_lang_archive->setNewRevision(TRUE);
      $node_lang_archive->revision_log = 'content change to draft' . $nid . "--" . time();
      $node_lang_archive->setRevisionCreationTime(REQUEST_TIME);
      $node_lang_archive->setRevisionUserId($uid);
      $storage = \Drupal::entityTypeManager()->getStorage($archive_node->getEntityTypeId());
      $vid = $storage->getLatestTranslationAffectedRevisionId($archive_node->id(), $current_language);
      /* Update Database node field revision table. */
      $table = 'node_field_revision';
      \Drupal::database()->update($table)
        ->fields(['revision_translation_affected' => 1])
        ->condition('langcode', $current_language)
        ->condition('vid', $vid)
        ->condition('nid', $nid)
        ->execute();
      $node_lang_archive->set('moderation_state', 'archive');
      $node_lang_archive->set('uid', $uid);
      $node_lang_archive->set('content_translation_source', $current_language);
      $node_lang_archive->set('changed', time());
      $node_lang_archive->set('created', time());
      $node_lang_archive->save();
      $archive_node->save();
      /* Change status from publish to draft. */

      $draft_node = Node::load($nid);
      $node_lang_draft = $draft_node->getTranslation($current_language);
      $node_lang_draft->set('moderation_state', 'draft');
      $node_lang_draft->set('uid', $uid);
      $node_lang_draft->set('content_translation_source', $current_language);
      $node_lang_draft->set('changed', time());
      $node_lang_draft->set('created', time());
      $node_lang_draft->save();
      $draft_node->save();
      $this->assigned = $this->assigned + 1;
    }
    else {
      $this->nonAssigned = $this->nonAssigned + 1;
    }

    if ($this->nonAssigned > 0) {
      $error_message = $this->t("Please Select Published Content ( @nonassigned ) <br/>", ['@nonassigned' => $this->nonAssigned]);
    }
    else {
      $message = $this->t("Content Changed Into Draft Successfully ( @assigned ) <br/>", ['@assigned' => $this->assigned]);
    }

    /* $message.="Please visit Country content page to view.";*/
    if ($list_count == $this->processItem) {
      if (!empty($message)) {
        drupal_set_message($message, 'status');
      }
      if (!empty($error_message)) {
        drupal_set_message($error_message, 'error');
      }
    }

    if ($this->initial == 1) {
      /* Please add the entity */
      $message = 'Content Bulk updated from archieve to draft by' . $uid . " content id - " . $all_ids;
      \Drupal::logger('Content Bulk updated')->info($message);
    }
    return $this->t("Total content selected");
  }

  /**
   * {@inheritdoc}
   */
  public function access($object, AccountInterface $account = NULL, $return_as_object = FALSE) {
    if ($object->getEntityType() === 'node') {
      $access = $object->access('update', $account, TRUE)
        ->andIf($object->status->access('edit', $account, TRUE));
      return $return_as_object ? $access : $access->isAllowed();
    }
    return TRUE;
  }

}
