<?php

namespace Drupal\mobile_app_links\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Class AppleDevMerchantIdAssocConfigForm.
 */
class AppleDevIdAssocConfigForm extends ConfigFormBase {

  const CONFIG_NAME = 'mobile_app_links.apple_dev_id_assoc';

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'mobile_app_links_apple_dev_id_assoc_config_form';
  }

  /**
   * {@inheritdoc}
   */
  public function getEditableConfigNames() {
    return [self::CONFIG_NAME];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildForm($form, $form_state);

    $config = $this->config(self::CONFIG_NAME);

    $form['apple_dev_id_assoc'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Apple Developer Id Domain Association'),
      '#description' => $this->t('Enter the id.'),
      '#default_value' => $config->get('apple_dev_id_assoc'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->config(self::CONFIG_NAME);
    $id = $form_state->getValue('apple_dev_id_assoc');
    $config->set('apple_dev_id_assoc', $id);
    $config->save();

    return parent::submitForm($form, $form_state);
  }

}
