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
	
	/* Kosovo App Config Details */
	$form['kosovo_appID'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Kosovo App ID'),
      '#default_value' => $config->get('kosovo_appID'),
    ];

    $form['kosovo_paths'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Kosovo Paths'),
      '#description' => $this->t('Enter one value per line.'),
      '#default_value' => $config->get('kosovo_paths'),
    ];

    $form['kosovo_appclips'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Kosovo App Clips'),
      '#description' => $this->t('Enter the "apps" that have appclips: *your_id*.com.domain.Clip.'),
      '#default_value' => $config->get('kosovo_appclips'),
    ];
	
	$form['kosovo_appID_Test'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Kosovo App ID - UAT'),
      '#default_value' => $config->get('kosovo_appID_Test'),
    ];

    $form['kosovo_paths_Test'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Kosovo Paths - UAT'),
      '#description' => $this->t('Enter one value per line.'),
      '#default_value' => $config->get('kosovo_paths_Test'),
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
	
    /* Kosovo Config Settings */
	$config->set('kosovo_appID_Test', $form_state->getValue('kosovo_appID_Test'));
    $kosovo_paths_Test = str_replace("\r\n", "\n", $form_state->getValue('kosovo_paths_Test'));
    $kosovo_paths_Test = str_replace("\r", "\n", $kosovo_paths_Test);
    $config->set('kosovo_paths_Test', $kosovo_paths_Test);
	
    $config->set('kosovo_appID', $form_state->getValue('kosovo_appID'));
    $kosovo_paths = str_replace("\r\n", "\n", $form_state->getValue('kosovo_paths'));
    $kosovo_paths = str_replace("\r", "\n", $kosovo_paths);
    $config->set('kosovo_paths', $kosovo_paths);
    $config->set('kosovo_appclips', $form_state->getValue('kosovo_appclips'));

    $config->save();

    return parent::submitForm($form, $form_state);
  }

}
