<?php

namespace Drupal\layout_custom_section_classes\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Global settings form.
 */
class GlobalSettingsForm extends ConfigFormBase {

  /**
   * Config settings.
   *
   * @var string
   */
  const SETTINGS = 'layout_custom_section_classes.settings';

  /**
   * Configuration categories.
   *
   * @var array
   */
  protected $categories = [
    'allowed_section_attributes',
    'allowed_section_region_attributes',
  ];

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'layout_builder_section_attributes_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      static::SETTINGS,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config(static::SETTINGS)->get();

    // Convert the true/false values back into a format FAPI expects.
    // attribute => attribute (for true).
    // attribute => 0 (for false).
    foreach ($config as $category => $cat_config) {
      if (in_array($category, $this->categories)) {
        foreach ($cat_config as $attribute => $value) {
          $config[$category][$attribute] = ($value) ? $attribute : 0;
        }
      }
    }

    $options = [
      'id' => $this->t('ID'),
      'class_list' => $this->t('Class list'),
      'class' => $this->t('Class(es)'),
      'style' => $this->t('Inline CSS styles'),
      'data' => $this->t('Custom data-* attributes'),
    ];

    $form['intro'] = [
      '#markup' => $this->t('<p>Control which attributes are made available to content editors below:</p>'),
    ];

    $form['allowed_section_attributes'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Allowed section attributes'),
      '#options' => $options,
      '#default_value' => $config['allowed_section_attributes'] ?? [],
    ];

    $form['allowed_section_region_attributes'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Allowed section regions attributes'),
      '#options' => $options,
      '#default_value' => $config['allowed_section_region_attributes'] ?? [],
    ];

    $form['class_list'] = [
      '#type' => 'textarea',
      '#title' => $this->t('CSS classes to choose from'),
      '#default_value' => implode("\n", $config['class_list'] ?? []),
      '#description' => $this->t('Configure CSS classes which you can add to sections on the "manage display" screens. Add multiple CSS classes line by line. Each class name must be a valid class.<br />If you want to have a friendly name, separate class and friendly name by |, but this is not required. eg:<br /><em>class-name-1<br />class-name-2|Friendly name<br />class-name-3</em>'),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $classList = $form_state->getValue('class_list');

    if (!empty($classList)) {
      $classItems = preg_split('/\R/', $classList);
      foreach ($classItems as $classItem) {
        $data_attribute = explode('|', $classItem);
        if (!_layout_custom_section_classes_validate_css_class($data_attribute[0]) || trim($data_attribute[0]) === '') {
          $form_state->setErrorByName('class_list', $this->t('Classes must be valid CSS classes'));
        }

      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->configFactory->getEditable(static::SETTINGS);

    // Loop through configuration categories.
    foreach ($this->categories as $category) {
      $cat_config = $form_state->getValue($category);

      // Convert the FAPI values into booleans for config storage.
      foreach ($cat_config as $attribute => $value) {
        $cat_config[$attribute] = ($value) ? TRUE : FALSE;
      }
      $config->set($category, $cat_config);
    }

    // Prepare region classes.
    $classList = [];
    $classListValue = $form_state->getValue('class_list');
    if (!empty($classListValue)) {
      $classList = explode("\n", str_replace("\r", '', $classListValue));
    }
    $config->set('class_list', $classList);

    $config->save();
    parent::submitForm($form, $form_state);
  }

}
