<?php

namespace Drupal\pb_custom_form\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Database\Database;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\group\Entity\Group;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/*
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Database\Database;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\group\Entity\Group;
 */
/**
 * Action description.
 *
 * @Action(
 *   id = "pb_custom_form_action",
 *   label = @Translation("Force Update API Check"),
 *   type = "node",
 *   confirm = FALSE
 * )
 */
class CustomForm extends FormBase {

  use StringTranslationTrait;

  /**
   * Get force check update api.
   */
  public function getFormId() {
    return 'forcefull_check_update_api';
  }

  /**
   * Force update check build form.
   *
   * @param array $form
   *   The custom form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The custom form state.
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $groups = Group::loadMultiple();
    foreach ($groups as $group) {
      $id = $group->get('id')->getString();
      $label = $group->get('label')->getString();
      $coutry_group[$id] = $label;
    }

    /* Dropdown Select. */
    $form['country_select'] = [
      '#type' => 'select',
      '#title' => $this->t('Country'),
      '#options' => $coutry_group,
    ];

    /* CheckBoxes. */
    $form['checkbox'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Force Update Check'),
      '#return_value' => 1,
      '#default_value' => FALSE,
    ];

    $form['actions'] = [
      '#type' => 'actions',
    ];

    /* Add a submit button. */
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save'),
      '#button_type' => 'primary',
    ];

    return $form;
  }

  /**
   * Force update check build form validation.
   *
   * @param array $form
   *   The custom form validation.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The custom form state validation.
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
  }

  /**
   * Force update check build submit form.
   *
   * @param array $form
   *   The custom form submit.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The custom form state.
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $date = new DrupalDateTime();
    $conn = Database::getConnection();
    $conn->insert('forcefull_check_update_api')->fields(
      [
        'flag' => $form_state->getValue('checkbox'),
        'country_id' => $form_state->getValue('country_select'),
        'updated_at' => $date->getTimestamp(),
      ]
    )->execute();

    $checkbox = $form_state->getValue('checkbox');
    $text_msg = $this->t("Force Check Update API Disabled");
    if ($checkbox == 1) {
      $text_msg = $this->t("Force Check Update API Enbled");
    }
    drupal_set_message($text_msg, 'status');
  }

}
