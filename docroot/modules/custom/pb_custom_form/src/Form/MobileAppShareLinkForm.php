<?php

namespace Drupal\pb_custom_form\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\ConfigFormBase;

/**
 * {@inheritdoc}
 */
class MobileAppShareLinkForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {

    return [
      'pb_custom_form.mobile_app_share_link_form',
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
    $config = $this->config('pb_custom_form.mobile_app_share_link_form');
    $form['mobile_app_share_link'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Manage Mobile APP Javascript'),
      '#description' => $this->t('Provide only the content that needs to be embedded with in the script tag here. Donot include script tag.'),
      '#default_value' => $config->get('mobile_app_share_link'),
    ];
    return parent::buildForm($form, $form_state);

  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);
    $this->config('pb_custom_form.mobile_app_share_link_form')
      ->set('mobile_app_share_link', $form_state->getValue('mobile_app_share_link'))
      ->save();
  }

}
