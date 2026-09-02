<?php

namespace Drupal\bebbo_custom_general\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Admin form for the per-site master-language setting.
 */
class SettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {

    return [
      'bebbo_custom_general.adminsettings',
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
    $config = $this->config('bebbo_custom_general.adminsettings');
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
    $this->config('bebbo_custom_general.adminsettings')
      ->set('master_language', $form_state->getValue('master_language'))
      ->save();
  }

}
