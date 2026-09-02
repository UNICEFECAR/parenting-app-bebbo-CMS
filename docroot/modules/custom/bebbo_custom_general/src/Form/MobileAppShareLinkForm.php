<?php

namespace Drupal\bebbo_custom_general\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Admin form to manage the inline JS injected into mobile share pages.
 */
class MobileAppShareLinkForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {

    return [
      'bebbo_custom_general.mobile_app_share_link_form',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {

    return 'admin_mobile_app_share_link_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('bebbo_custom_general.mobile_app_share_link_form');
    $form['mobile_app_share_link'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Manage Mobile APP Javascript'),
      '#description' => $this->t('Provide only the content that needs to be embedded with in the script tag here. Donot include script tag.'),
      '#default_value' => $config->get('mobile_app_share_link'),
    ];
    /* Kosovo share page - Javascript */

    $form['kosovo_mobile_app_share_link'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Kosovo - Mobile APP Javascript'),
      '#description' => $this->t('Provide only the content that needs to be embedded with in the script tag here. Donot include script tag.'),
      '#default_value' => $config->get('kosovo_mobile_app_share_link'),
    ];
    return parent::buildForm($form, $form_state);

  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);
    $this->config('bebbo_custom_general.mobile_app_share_link_form')
      ->set('mobile_app_share_link', $form_state->getValue('mobile_app_share_link'))
      ->set('kosovo_mobile_app_share_link', $form_state->getValue('kosovo_mobile_app_share_link'))
      ->save();
  }

}
