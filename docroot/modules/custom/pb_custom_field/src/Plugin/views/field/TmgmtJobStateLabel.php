<?php

namespace Drupal\pb_custom_field\Plugin\views\field;

use Drupal\tmgmt\Entity\Job;
use Drupal\tmgmt\JobInterface;
use Drupal\views\Attribute\ViewsField;
use Drupal\views\Plugin\views\field\NumericField;
use Drupal\views\ResultRow;

/**
 * Shows the TMGMT job state as a text label.
 *
 * TMGMT replaces the job state entity field in the views data with an
 * icon-only handler, so a readable state column cannot be configured in the
 * view alone. For active jobs the label follows the same logic TMGMT uses
 * for the icon: the most relevant job item state wins, so a job whose items
 * are all translated reads "Needs review" instead of a generic "Active".
 */
#[ViewsField("pb_tmgmt_job_state_label")]
class TmgmtJobStateLabel extends NumericField {

  /**
   * {@inheritdoc}
   */
  public function render(ResultRow $values) {
    $job = $this->getEntity($values);
    if (!$job instanceof JobInterface) {
      return '';
    }

    $state = (int) $job->getState();
    if ($state === JobInterface::STATE_ACTIVE) {
      // Mirror \Drupal\tmgmt\Plugin\views\field\JobState: the highest-weight
      // job item state describes the job best ("In progress", "Needs
      // review", ...).
      $highest_weight_icon = NULL;
      foreach ($job->getItems() as $item) {
        $job_item_icon = $item->getStateIcon();
        if ($job_item_icon && (!$highest_weight_icon || $highest_weight_icon['#weight'] < $job_item_icon['#weight'])) {
          $highest_weight_icon = $job_item_icon;
        }
      }
      return [
        '#plain_text' => $highest_weight_icon ? $highest_weight_icon['#title'] : $this->t('In progress'),
      ];
    }

    $states = Job::getStates();
    return isset($states[$state]) ? ['#plain_text' => $states[$state]] : '';
  }

}
