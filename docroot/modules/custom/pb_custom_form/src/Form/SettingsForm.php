<?php

namespace Drupal\pb_custom_form\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * {@inheritdoc}
 */
class SettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {

    return [
      'pb_custom_form.adminsettings',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {

    return 'admin_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('pb_custom_form.adminsettings');
    $form['master_language'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Master language'),
      '#description' => $this->t('Master language for the countries'),
      '#default_value' => $config->get('master_language'),
    ];
    return parent::buildForm($form, $form_state);

  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);
    $this->config('pb_custom_form.adminsettings')
      ->set('master_language', $form_state->getValue('master_language'))
      ->save();
  }

}
