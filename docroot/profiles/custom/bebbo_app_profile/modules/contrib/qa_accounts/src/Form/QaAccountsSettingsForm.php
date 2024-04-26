<?php

namespace Drupal\qa_accounts\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Configuration form for QA Accounts.
 */
class QaAccountsSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'qa_accounts_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['qa_accounts.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('qa_accounts.settings');

    $form['auto_create_user_per_new_role'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Automatically create QA user when role is created'),
      '#default_value' => $config->get('auto_create_user_per_new_role'),
      '#description' => $this->t('Checking this box will automatically create a QA user for newly created role.'),
    ];

    $form['auto_delete_user_per_deleted_role'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Automatically delete QA user when role is deleted'),
      '#default_value' => $config->get('auto_delete_user_per_deleted_role'),
      '#description' => $this->t('Checking this box will automatically delete a QA user when corresponding role deleted.'),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->config('qa_accounts.settings');
    $config->set('auto_create_user_per_new_role', $form_state->getValue('auto_create_user_per_new_role'))
      ->set('auto_delete_user_per_deleted_role', $form_state->getValue('auto_delete_user_per_deleted_role'))
      ->save();
    parent::submitForm($form, $form_state);
  }

}
