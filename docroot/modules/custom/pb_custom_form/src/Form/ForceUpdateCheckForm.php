<?php

namespace Drupal\pb_custom_form\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Database\Database;
use Drupal\Core\Datetime\DrupalDateTime;

/**
 * Action description.
 *
 * @Action(
 *   id = "force_update_check",
 *   label = @Translation("Force Update Check"),
 *   type = "node",
 *   confirm = FALSE
 * )
 */
class ForceUpdateCheckForm extends FormBase {

  /**
   * Get form id.
   */
  public function getFormId() {
    return 'forcefull_check_update';
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
    global $base_url;
    $country_name = \Drupal::request()->query->get('country_name');

    $form['markup_text'] = [
      '#type' => 'markup',
      '#markup' => '<b> Are You Sure To Save The ' . $country_name . ' Country</b>',

    ];

    $form['actions'] = [
      '#type' => 'actions',
    ];

    /* Add a submit button. */
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Yes'),
      '#button_type' => 'primary',
    ];

    $form['actions']['submits'] = [
      '#type' => 'inline_template',
      '#template' => '<a href = "' . $base_url . '"><button type="button" class="button">No</button></a>',
    ];
    return $form;
  }

  /**
   * Save force check update api.
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    global $base_url;

    $country_id = \Drupal::request()->query->get('country_id');
    $flag = \Drupal::request()->query->get('flag');
    $user = \Drupal\user\Entity\User::load(\Drupal::currentUser()->id());
    $uuid = $user->uuid();
    $date = new DrupalDateTime();
    $conn = Database::getConnection();
    if ($flag != '' && $country_id != '') {
      $conn->insert('forcefull_check_update_api')->fields(
      [
        'flag_status' => $flag,
        'countries_id' => $country_id,
        'uuid' => $uuid,
        'created_at' => $date->getTimestamp(),
      ]
      )->execute();
      drupal_flush_all_caches();
      $path = $base_url . '/admin/config/parent-buddy/forcefull-update-check';
      my_goto($path);
      drupal_set_message(t('data inserted successfully'), 'status', TRUE);
    }
    else {
      $path = $base_url . '/admin/config/parent-buddy/forcefull-update-check';
      my_goto($path);
      drupal_set_message(t('Please Select Country And Flag'), 'warning', TRUE);
    }
  }

}
