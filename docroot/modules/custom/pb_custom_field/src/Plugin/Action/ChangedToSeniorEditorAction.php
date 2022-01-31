<?php

namespace Drupal\pb_custom_field\Plugin\Action;

use Drupal\views_bulk_operations\Action\ViewsBulkOperationsActionBase;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Entity\ContentEntityInterface;

/* use Drupal\node\Entity\Node;
use Drupal\user\Entity\User;
 */
/**
 * Action description.
 *
 * @Action(
 *   id = "pb_custom_field_change_to_senior_editor",
 *   label = @Translation("Change to Senior Editor"),
 *   type = "node",
 *   confirm = FALSE
 * )
 */
class ChangedToSeniorEditorAction extends ViewsBulkOperationsActionBase {

  use StringTranslationTrait;
  /**
   * Get the total translated count.
   *
   * @var int
   */
  public $initialCount = 1;
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
    $initial_count = $this->initialCount++;
    $list = $this->context['list'];
    $page = $this->context['sandbox']['page'];
    foreach ($list as $value) {
      $nids = $value[0];
      /* $langs = $value[1]; */
      $n_language[$nids][] = $value[1];
    }
    if ($initial_count == 1 && $page == 0) {
      $batch = [
        'title' => t('change status'),
        'operations' => [
          [
            '\Drupal\pb_custom_field\ChangeintoSeniorEditorActionStatus::offLoadCountryProcessd',
            [$n_language],
          ],
        ],
        'finished' => '\Drupal\pb_custom_field\ChangeintoSeniorEditorActionStatus::offLoadsCountryProcessFinishedCallback',
      ];
      batch_set($batch);
    }
    return $this->t("Total Content Selected");
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
