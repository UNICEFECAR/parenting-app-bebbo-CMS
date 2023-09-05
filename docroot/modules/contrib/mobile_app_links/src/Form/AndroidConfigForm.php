<?php

namespace Drupal\mobile_app_links\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Provides the Android app configuration form.
 */
class AndroidConfigForm extends ConfigFormBase {

  const CONFIG_NAME = 'mobile_app_links.android_packages';

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

    $android_packages = (array) $config->get('android_packages');

    $form['#tree'] = TRUE;
    $form['android_packages'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Android App Configurations'),
      '#prefix' => '<div id="configurations-fieldset-wrapper">',
      '#suffix' => '</div>',
    ];

    $temp_count = (count($android_packages) == 0) ? ($form_state->get('temp_count') + 1) : $form_state->get('temp_count');

    if (!empty($android_packages)) {
      foreach ($android_packages as $key => $android_package) {
        $form['android_packages'][$key] = [
          '#type' => 'fieldset',
          '#Collapsible' => TRUE,
        ];

        $form['android_packages'][$key]['package_name'] = [
          '#type' => 'textfield',
          '#title' => $this->t('Package Name'),
          '#default_value' => $android_package['package_name'] ?? '',
        ];

        $form['android_packages'][$key]['sha256_cert_fingerprints'] = [
          '#type' => 'textarea',
          '#title' => $this->t('Enter one value per line.'),
          '#description' => $this->t('Enter one value per line.'),
          '#default_value' => $android_package['sha256_cert_fingerprints'] ?? '',
        ];
        	
        /* Kosovo Country package details */
        $form['kosovo_package_name'] = [
          '#type' => 'textfield',
          '#title' => $this->t('Kosovo Package Name'),
          '#default_value' => $config->get('kosovo_package_name'),
        ];

        $form['kosovo_sha256_cert_fingerprints'] = [
          '#type' => 'textarea',
          '#title' => $this->t('Kosovo SHA256 Certificate Fingerprints'),
          '#description' => $this->t('Enter one value per line.'),
          '#default_value' => $config->get('kosovo_sha256_cert_fingerprints'),
        ];

        $form['android_packages'][$key]['android_package_delete'] = [
          '#type' => 'submit',
          '#value' => $this->t('Delete'),
          '#submit' => ['::deleteThis'],
          '#name' => 'package-remove-button' . $key,
          '#ajax' => [
            'callback' => '::addRemoveCallback',
            'wrapper' => 'configurations-fieldset-wrapper',
          ],
        ];
      }
    }

    if ($temp_count > 0) {
      for ($i = 0; $i < $temp_count; $i++) {
        $form['android_packages'][$i] = [
          '#type' => 'fieldset',
          '#Collapsible' => TRUE,
        ];
        $form['android_packages'][$i]['package_name'] = [
          '#type' => 'textfield',
          '#title' => $this->t('Package Name'),
        ];

        $form['android_packages'][$i]['sha256_cert_fingerprints'] = [
          '#type' => 'textarea',
          '#title' => $this->t('Enter one value per line.'),
          '#description' => $this->t('Enter one value per line.'),
        ];

      }
    }

    $form['actions'] = [
      '#type' => 'actions',
    ];
    $form['android_packages']['actions']['android_package_add'] = [
      '#type' => 'submit',
      '#value' => $this->t('Add More'),
      '#submit' => ['::addOne'],
      '#ajax' => [
        'callback' => '::addRemoveCallback',
        'wrapper' => 'configurations-fieldset-wrapper',
      ],
    ];

    $form_state->setCached(FALSE);
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Submit'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function addOne(array &$form, FormStateInterface $form_state) {
    $android_packages_field = $form_state->get('temp_count') ?? 0;
    $form_state->set('temp_count', ($android_packages_field + 1));

    $form_state->setRebuild();
  }

  /**
   * {@inheritdoc}
   */
  public function deleteThis(array &$form, FormStateInterface $form_state) {
    $config = $this->config(self::CONFIG_NAME);
    $triggering_element = $form_state->getTriggeringElement();
    $key = $triggering_element['#parents'][1];

    // Delete config.
    $config->clear('android_packages.' . $key);
    $config->save();

    $form_state->setRebuild();
  }

  /**
   * {@inheritdoc}
   */
  public function addRemoveCallback(array &$form, FormStateInterface $form_state) {
    return $form['android_packages'];
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->config(self::CONFIG_NAME);

    $android_packages = [];
    $values = $form_state->getValue('android_packages');
    $index = 0;

    foreach ($values as $value) {
      if (!empty($value['package_name'])) {
        $config_key = str_replace('.', '-', $value['package_name']) . '_' . $index;

        $android_packages[$config_key]['package_name'] = $value['package_name'] ?? '';

        $certificates = str_replace("\r\n", "\n", $value['sha256_cert_fingerprints']);
        $certificates = str_replace("\r", "\n", $certificates);

        $android_packages[$config_key]['sha256_cert_fingerprints'] = $certificates;
      }
      $index++;
    }
    
    /* Kosovo Package details */
    $config->set('kosovo_package_name', $form_state->getValue('kosovo_package_name'));
    $certificates = str_replace("\r\n", "\n", $form_state->getValue('kosovo_sha256_cert_fingerprints'));
    $certificates = str_replace("\r", "\n", $certificates);
    $config->set('kosovo_sha256_cert_fingerprints', $certificates);
    $config->set('android_packages', $android_packages);
    $config->save();

    return parent::submitForm($form, $form_state);
  }

}
