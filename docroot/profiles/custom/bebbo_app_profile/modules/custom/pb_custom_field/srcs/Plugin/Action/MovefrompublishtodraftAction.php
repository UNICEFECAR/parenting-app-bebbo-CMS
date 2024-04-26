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
 *   id = "pb_custom_field_publish_to_draft",
 *   label = @Translation("Change To Draft"),
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
    $user = User::load($uid);
    $aaa = $this->initial_count++;
    $aaab[] = $aaa;
    $initial_countss = $this->initial_count + 1;
    $list = $this->context['list'];
    $total = $this->context['sandbox']['total'];
    $batch_size = $this->context['sandbox']['batch_size'];
    $current_batch = $this->context['sandbox']['current_batch'];
    $page = $this->context['sandbox']['page'];
    $list_count = count($list);
    $rounds = $total/$batch_size;
    $message = "";
    $error_message = "";
    $current_language[] = $entity->get('langcode')->value;   
    foreach ($list as  $value) {
     
      $nids = $value[0];
      $langs = $value[1];
      $n_language[$nids][] = $value[1];
    }
    
    if ($aaa == 1 && $page == 0) {

      $batch = [
      'title' => t('change status'),
      'operations' => [
      [
        '\Drupal\pb_custom_field\ChangeActionStatus::offLoadCountryProcessfordraft',
        [$n_language,$all_nids]
      ],
      ],
      'finished' => '\Drupal\pb_custom_field\ChangeActionStatus::offLoadsCountryProcessfordraftFinishedCallback',
    ];
    batch_set($batch);
  }

    // if ($this->nonAssigned > 0) {
    //   $error_message = $this->t("Please Select Published Content ( @nonassigned ) <br/>", ['@nonassigned' => $this->nonAssigned]);
    // }
    // else {
    //   $message = $this->t("Content Changed Into Draft Successfully ( @assigned ) <br/>", ['@assigned' => $this->assigned]);
    // }

    // if ($this->countryRestrict > 0) {
    //   $error_message = $this->t("This content belongs to Master content and cannot be edited. It has to be assigned to your country to allow for further editing and contextualization. ( @countryRestrict ) <br/>", ['@countryRestrict' => $this->countryRestrict]);
    // }

    /* $message.="Please visit Country content page to view.";*/
    // if ($list_count == $this->processItem) {
    //   if (!empty($message)) {
    //     drupal_set_message($message, 'status');
    //   }
    //   if (!empty($error_message)) {
    //     drupal_set_message($error_message, 'error');
    //   }
    // }

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
