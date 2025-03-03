<?php

namespace Drupal\gtm\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Implements the form controller.
 */
class SettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'gtm_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['gtm.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('gtm.settings');
    $form['general'] = [
      '#type' => 'details',
      '#title' => $this->t('General settings'),
      '#open' => TRUE,
    ];
    $form['general']['enable'] = [
      '#title' => $this->t('Enable Google Tag Manager'),
      '#description' => $this->t('Add GTM code on website pages'),
      '#type' => 'checkbox',
      '#maxlength' => 20,
      '#required' => FALSE,
      '#size' => 15,
      '#default_value' => $config->get('enable'),
    ];
    $form['general']['admin-pages'] = [
      '#title' => $this->t('Display Google Tag Manager on admin pages (if GTM enabled)'),
      '#description' => $this->t('Add GTM code on admin pages'),
      '#type' => 'checkbox',
      '#maxlength' => 20,
      '#required' => FALSE,
      '#size' => 15,
      '#default_value' => $config->get('admin-pages'),
    ];
    $form['general']['admin-disable'] = [
      '#title' => $this->t('Disable for admin'),
      '#description' => $this->t('Do not use GTM for User with uid=1'),
      '#type' => 'checkbox',
      '#maxlength' => 20,
      '#required' => FALSE,
      '#size' => 15,
      '#default_value' => $config->get('admin-disable'),
    ];
    $form['general']['google-tag'] = [
      '#title' => $this->t('GTM-ID'),
      '#default_value' => $config->get('google-tag'),
      '#maxlength' => 20,
      '#size' => 15,
      '#type' => 'textfield',
      '#description' => $this->t(
        "You can add site to
        <a target='_blank' href='https://tagmanager.google.com/?hl=@lang'>Google Tag Manager</a>",
        ['@lang' => \Drupal::languageManager()->getCurrentLanguage()->getId()]
      ),
    ];
    return parent::buildForm($form, $form_state);
  }

  /**
   * Implements a form submit handler.
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->config('gtm.settings');
    $config
      ->set('enable', $form_state->getValue('enable'))
      ->set('admin-pages', $form_state->getValue('admin-pages'))
      ->set('admin-disable', $form_state->getValue('admin-disable'))
      ->set('google-tag', $form_state->getValue('google-tag'))
      ->save();

    parent::submitForm($form, $form_state);
  }

}
