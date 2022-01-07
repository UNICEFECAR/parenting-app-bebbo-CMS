<?php

namespace Drupal\mobile_app_links\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Class IosConfigForm.
 */
class IosConfigForm extends ConfigFormBase {

  const CONFIG_NAME = 'mobile_app_links.ios';

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'mobile_app_links_ios_config_form';
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

    $form['appID'] = [
      '#type' => 'textfield',
      '#title' => $this->t('App ID'),
      '#default_value' => $config->get('appID'),
    ];

    $form['paths'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Paths'),
      '#description' => $this->t('Enter one value per line.'),
      '#default_value' => $config->get('paths'),
    ];
	
    $form['appclips'] = [
      '#type' => 'textfield',
      '#title' => $this->t('App Clips'),
      '#description' => $this->t('Enter the "apps" that have appclips: *your_id*.com.domain.Clip.'),
      '#default_value' => $config->get('appclips'),
    ];
	
	$form['appID_Test'] = [
      '#type' => 'textfield',
      '#title' => $this->t('App ID - UAT'),
      '#default_value' => $config->get('appID_Test'),
    ];

    $form['paths_Test'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Paths - UAT'),
      '#description' => $this->t('Enter one value per line.'),
      '#default_value' => $config->get('paths_Test'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->config(self::CONFIG_NAME);
    $config->set('appID', $form_state->getValue('appID'));

    $paths = str_replace("\r\n", "\n", $form_state->getValue('paths'));
    $paths = str_replace("\r", "\n", $paths);
    $config->set('paths', $paths);
    $config->set('appclips', $form_state->getValue('appclips'));
	
	/* UAT Config Settings */
	$config->set('appID_Test', $form_state->getValue('appID_Test'));
	$paths_uat = str_replace("\r\n", "\n", $form_state->getValue('paths_Test'));
    $paths_uat = str_replace("\r", "\n", $paths_uat);
    $config->set('paths_Test', $paths_uat);

    $config->save();

    return parent::submitForm($form, $form_state);
  }

}
