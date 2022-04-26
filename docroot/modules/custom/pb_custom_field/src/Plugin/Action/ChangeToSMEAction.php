<?php

namespace Drupal\pb_custom_field\Plugin\Action;

use Drupal\views_bulk_operations\Action\ViewsBulkOperationsActionBase;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\node\Entity\Node;
use Drupal\user\Entity\User;

/* use Drupal\node\Entity\Node;
use Drupal\user\Entity\User;
 */
/**
 * Action description.
 *
 * @Action(
 *   id = "pb_custom_field_change_to_sme",
 *   label = @Translation("Change to SME"),
 *   type = "node",
 *   confirm = FALSE
 * )
 */
class ChangeToSMEAction extends ViewsBulkOperationsActionBase {

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
    $user = User::load($uid);
    // $groups = array();
    $grp_membership_service = \Drupal::service('group.membership_loader');
    $grps = $grp_membership_service->loadByUser($user);
    if (!empty($grps)) {
      foreach ($grps as $grp) {
        $groups = $grp->getGroup();
      }
      $grp_country_language = $groups->get('field_language')->getValue();
      $grp_country_new_array = array_column($grp_country_language, 'value');
    }

    // $this->initial = $this->initial + 1;
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
    if ($current_state !== 'sme_review' && empty($grps)) {
      /* Change status from publish to archive. */
      $uid = \Drupal::currentUser()->id();
      $node_lang_archive->set('moderation_state', 'sme_review');
      $node_lang_archive->set('uid', $uid);
      $node_lang_archive->set('content_translation_source', $current_language);
      $node_lang_archive->set('changed', time());

      $node_lang_archive->setNewRevision(TRUE);
      $node_lang_archive->revision_log = 'Content changed  into SME Review State';
      $node_lang_archive->setRevisionCreationTime(REQUEST_TIME);
      $node_lang_archive->setRevisionUserId($uid);
      $node_lang_archive->setRevisionTranslationAffected(NULL);
      $node_lang_archive->save();
      $archive_node->save();
      $this->assigned = $this->assigned + 1;
    }
    elseif ($current_state !== 'sme_review' && !empty($grps)) {
      if (in_array($current_language, $grp_country_new_array)) {
        /* Change status into “Published” state. */
        $uid = \Drupal::currentUser()->id();
        $node_lang_archive->set('moderation_state', 'sme_review');
        $node_lang_archive->set('uid', $uid);
        $node_lang_archive->set('content_translation_source', $current_language);
        $node_lang_archive->set('changed', time());

        $node_lang_archive->setNewRevision(TRUE);
        $node_lang_archive->revision_log = 'Content changed  into SME Review State';
        $node_lang_archive->setRevisionCreationTime(REQUEST_TIME);
        $node_lang_archive->setRevisionUserId($uid);
        $node_lang_archive->setRevisionTranslationAffected(NULL);
        $node_lang_archive->save();
        $archive_node->save();
        $this->assigned = $this->assigned + 1;
      }
      else {
        $this->countryRestrict = $this->countryRestrict + 1;

      }
      
    }
    else {
      $this->nonAssigned = $this->nonAssigned + 1;

    }

    if ($this->nonAssigned > 0) {
      $error_message = $this->t("Selected content is already in SME Review state ( @nonassigned ) <br/>", ['@nonassigned' => $this->nonAssigned]);
    }
    if ($this->assigned > 0) {
      $message = $this->t("Content changed into SME Review successfully ( @assigned ) <br/>", ['@assigned' => $this->assigned]);
    }
    if ($this->countryRestrict > 0) {
      $error_message = $this->t("This content belongs to Master content and cannot be edited. It has to be assigned to your country to allow for further editing and contextualization. ( @countryRestrict ) <br/>", ['@countryRestrict' => $this->countryRestrict]);
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
    // if ($this->initial == 1) {
    //   /* Please add the entity */
    //   $message = 'Content Bulk updated into published' . $uid . " content id - " . $all_ids;
    //   \Drupal::logger('Content Bulk updated')->info($message);
    // }

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
