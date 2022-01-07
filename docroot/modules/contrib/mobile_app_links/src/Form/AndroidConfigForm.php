<?php

namespace Drupal\mobile_app_links\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Class AndroidConfigForm.
 */
class AndroidConfigForm extends ConfigFormBase {

  const CONFIG_NAME = 'mobile_app_links.android';

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'mobile_app_links_android_config_form';
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

    $form['package_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Package Name'),
      '#default_value' => $config->get('package_name'),
    ];

    $form['sha256_cert_fingerprints'] = [
      '#type' => 'textarea',
      '#title' => $this->t('SHA256 Certificate Fingerprints'),
      '#description' => $this->t('Enter one value per line.'),
      '#default_value' => $config->get('sha256_cert_fingerprints'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->config(self::CONFIG_NAME);
    $config->set('package_name', $form_state->getValue('package_name'));

    $certificates = str_replace("\r\n", "\n", $form_state->getValue('sha256_cert_fingerprints'));
    $certificates = str_replace("\r", "\n", $certificates);
    $config->set('sha256_cert_fingerprints', $certificates);
    $config->save();

    return parent::submitForm($form, $form_state);
  }

}
