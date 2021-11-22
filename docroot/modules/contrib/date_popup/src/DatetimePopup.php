<?php

namespace Drupal\date_popup;

use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Form\FormStateInterface;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItemInterface;
use Drupal\datetime\Plugin\views\filter\Date;

/**
 * The datetime popup views filter plugin.
 */
class DatetimePopup extends Date {

  use DatePopupTrait;

  /**
   * {@inheritdoc}
   */
  public function buildExposedForm(&$form, FormStateInterface $form_state) {
    parent::buildExposedForm($form, $form_state);
    $this->applyDatePopupToForm($form);
  }
  /**
@@ -20,4 +22,15 @@ class DatetimePopup extends Date {
     $this->applyDatePopupToForm($form);
   }
 
  /**
   * {@inheritdoc}
   */
  protected function opBetween($field) {
    // Add 1 day to the end so the query will include the selected date.
    $end = new DrupalDateTime($this->value['max']);
    $end->add(new \DateInterval ('P1D'));
    $this->value['max'] = $end->format(DateTimeItemInterface::DATE_STORAGE_FORMAT);
    parent::opBetween($field);
  }


}
