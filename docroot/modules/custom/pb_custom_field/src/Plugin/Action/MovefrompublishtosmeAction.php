<?php

namespace Drupal\pb_custom_field\Plugin\Action;

use Drupal\views_bulk_operations\Action\ViewsBulkOperationsActionBase;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Entity\ContentEntityInterface;

/**
 * Action description.
 *
 * @Action(
 *   id = "pb_custom_field_publish_to_sme",
 *   label = @Translation("Publish to Archive then SME Review"),
 *   type = "node",
 *   confirm = FALSE
 *
 * )
 */
class MovefrompublishtosmeAction extends ViewsBulkOperationsActionBase {

  use StringTranslationTrait;
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
    $context = $this->context;
    $total_selected = $context['sandbox']['total'];
    $this->processItem = $this->processItem + 1;
    $message = "";
    $error_message = "";
    $current_language = $entity->get('langcode')->value;
    $nid = $entity->get('nid')->getString();
    $node = node_load($nid);
    $node_lang = $node->getTranslation($current_language);
    $current_state = $node_lang->moderation_state->value;
    $uid = \Drupal::currentUser()->id();
    if ($current_state == 'published') {
      /* change status from publish to archive */
      $node_lang->set('moderation_state', 'archive');
      $node_lang->set('uid', $uid);
      $node_lang->set('content_translation_source', $current_language);
      $node_lang->set('changed', time());
      $node_lang->set('created', time());
      $node_lang->save();
      $node->setRevisionTranslationAffected(FALSE);
      $node->save();
      /* change status from archive to sme */
      $node = node_load($nid);
      $node_lang = $node->getTranslation($current_language);
      $node_lang->set('moderation_state', 'sme_review');
      $node_lang->set('uid', $uid);
      $node_lang->set('content_translation_source', $current_language);
      $node_lang->set('changed', time());
      $node_lang->set('created', time());
      $node_lang->save();
      $node->setRevisionTranslationAffected(FALSE);
      $node->save();
      $this->assigned = $this->assigned + 1;
    }
    else {
      $this->nonAssigned = $this->nonAssigned + 1;
    }
    if ($this->nonAssigned > 0) {
      $error_message = $this->t("Please Select Published Content ( @nonassigned ) <br/>", ['@nonassigned' => $this->nonAssigned]);
    }
    else {
      $message = $this->t("Content Changed into SME Review Successfully ( @assigned ) <br/>", ['@assigned' => $this->assigned]);
    }
    if ($total_selected == $this->processItem) {
      if (!empty($message)) {
        drupal_set_message($message, 'status');
      }
      if (!empty($error_message)) {
        drupal_set_message($error_message, 'error');
      }
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
