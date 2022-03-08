<?php

namespace Drupal\pb_custom_field\Plugin\Action;

use Drupal\views_bulk_operations\Action\ViewsBulkOperationsActionBase;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\node\Entity\Node;
use Drupal\user\Entity\User;

/**
 * Action description.
 *
 * @Action(
 *   id = "pb_custom_field_change_to_publish",
 *   label = @Translation("Change To Published"),
 *   type = "node",
 *   confirm = FALSE
 * )
 */
class ChangedToPublishedAction extends ViewsBulkOperationsActionBase {

  use StringTranslationTrait;
  /**
   * Get the total translated count.
   *
   * @var int
   */
  public $initial_count = 1;
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
   * Get the total non translated count.
   *
   * @var int
   */
  public $countryRestrict = 0;
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
    $current_language = $entity->get('langcode')->value;
    $node_id = $entity->get('nid')->getString();
    $selected_node = node_load($node_id);
    $selected_node_lang = $selected_node->getTranslation($current_language);
      ///// Change node status
      $selected_node_lang->set('moderation_state', 'draft');
      $selected_node_lang->set('uid', $uid);
      $selected_node_lang->set('content_translation_source', $current_language);
      $selected_node_lang->set('changed', time());
      $selected_node_lang->set('created', time());
      ///// create revision
      $selected_node_lang->setNewRevision(TRUE);
      $selected_node_lang->revision_log = 'Content changed into Archive!!!';
      $selected_node_lang->setRevisionCreationTime(REQUEST_TIME);
      $selected_node_lang->setRevisionUserId($uid);
      $selected_node_lang->setRevisionTranslationAffected(NULL);
      $selected_node_lang->save();
      $selected_node->save();
    
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