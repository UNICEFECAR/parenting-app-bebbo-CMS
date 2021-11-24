<?php

namespace Drupal\pb_custom_form\Form;

/**
 * @file
 * Php version 7.2.10.
 */
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Database\Database;
use Drupal\Core\Datetime\DrupalDateTime;

/* use Drupal\user\Entity\User; */
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
   * Create new form.
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    global $base_url;
    $request = $this->getRequest();
    $country_name = $request->query->get('country_name');

    $form['markup_text'] = [
      '#type' => 'markup',
      '#markup' => '<b> Are you sure you want to proceed with a force update for ' . $country_name . ' Country</b>',
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
      '#template' => '<a href = "' . $base_url . '/admin/config/parent-buddy/forcefull-update-check"><button type="button" class="button">No</button></a>',
    ];
    return $form;
  }

  /**
   * Save force check update api.
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    global $base_url;
    $request = $this->getRequest();
    $country_id = $request->query->get('country_id');
    $flag = $request->query->get('flag');
    /* $uid = \Drupal::currentUser()->id();
    $user = User::load($uid); */
    $uid = $this->currentUser()->id();
    $user = $this->entityTypeManager()->getStorage('user')->load($uid);
    $uuid = $user->uuid();
    $date = new DrupalDateTime();
    $conn = Database::getConnection();
    if ($flag != '' && $country_id != '') {
      $conn->insert('forcefull_check_update_api')->fields([
        'flag_status' => $flag,
        'countries_id' => $country_id,
        'uuid' => $uuid,
        'created_at' => $date->getTimestamp(),
      ]
      )->execute();
      drupal_flush_all_caches();
      $path = $base_url . '/admin/config/parent-buddy/forcefull-update-check';
      my_goto($path);
      $message = $this->t("Data inserted successfully");
      drupal_set_message($message, 'status', TRUE);
    }
    else {
      $path = $base_url . '/admin/config/parent-buddy/forcefull-update-check';
      my_goto($path);
      $warn_message = $this->t("Please Select Country And Flag");
      drupal_set_message($warn_message, 'warning', TRUE);
    }
  }

}
