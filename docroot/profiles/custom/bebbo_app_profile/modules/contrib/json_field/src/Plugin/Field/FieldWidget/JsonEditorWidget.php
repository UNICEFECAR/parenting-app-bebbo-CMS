<?php

namespace Drupal\json_field\Plugin\Field\FieldWidget;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;
use Swaggest\JsonSchema\Schema;

/**
 * Plugin implementation of the 'json_editor' widget.
 *
 * @FieldWidget(
 *   id = "json_editor",
 *   label = @Translation("WYSIWYG editor ()"),
 *   field_types = {
 *     "json",
 *     "json_native",
 *     "json_native_binary"
 *   }
 * )
 */
class JsonEditorWidget extends WidgetBase {

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return [
      'mode' => 'code',
      'modes' => [
        'tree',
        'code',
      ],
      'schema' => '',
      'schema_validate' => FALSE,
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $elements = [];

    $modes = [
      'text' => t('Plain text'),
      'code' => t('Code Editor (ACE)'),
      'tree' => t('Tree'),
      'form' => t('Form (read-only structure)'),
      'view' => t('View (read-only)'),
    ];

    $elements['mode'] = [
      '#type' => 'select',
      '#options' => $modes,
      '#title' => t('Editor mode'),
      '#default_value' => $this->getSetting('mode'),
    ];

    $elements['modes'] = [
      '#type' => 'checkboxes',
      '#options' => $modes,
      '#title' => t('Available modes'),
      '#default_value' => $this->getEditorModes(),
    ];

    $elements['schema'] = [
      '#type' => 'textarea',
      '#title' => t('JSON schema to validate the field'),
      '#default_value' => $this->getSetting('schema'),
      '#attributes' => [
        'data-json-editor' => 'admin',
      ],
      '#attached' => [
        'library' => ['json_field/json_editor.widget'],
        'drupalSettings' => [
          'json_field' => [
            'admin' => [
              'mode' => 'code',
              'modes' => ['tree', 'code', 'text'],
              'schema' => file_get_contents(__DIR__ . '/../../../../assets/schema.json'),
            ],
          ],
        ],
      ],
      '#element_validate' => [[get_class($this), 'validateJsonSchema']],
    ];

    $elements['schema_validate'] = [
      '#type' => 'checkbox',
      '#title' => t('Validate against the schema'),
      '#description' => t('Uses theJSON schema provided above to validate the data entered, prevents saving the entity if the JSON is not valid against the schema.'),
      '#default_value' => $this->getSetting('schema_validate'),
    ];

    return $elements;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $summary = [];

    $summary[] = t('Mode: @mode', ['@mode' => $this->getSetting('mode')]);
    $summary[] = t('Available modes: @modes', ['@modes' => implode(', ', $this->getEditorModes())]);
    $has_schema = !empty($this->getSetting('schema'));
    $summary[] = t('JSON schema: @exists', ['@exists' => $has_schema ? t('Yes') : t('No')]);
    if ($has_schema) {
      $summary[] = t('JSON schema validation: @validate', ['@validate' => $this->getSetting('schema_validate') ? t('Yes') : t('No')]);
    }

    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $editor_config = [
      'mode' => $this->getSetting('mode'),
      'modes' => $this->getEditorModes(),
    ];
    if (!empty($this->getSetting('schema'))) {
      $editor_config['schema'] = $this->getSetting('schema');
    }
    $hash = hash('sha256', serialize($editor_config));

    $element['value'] = [
      '#title' => t('JSON'),
      '#type' => 'textarea',
      '#default_value' => isset($items[$delta]->value) ? $items[$delta]->value : NULL,
      '#attributes' => [
        'data-json-editor' => $hash,
      ],
      '#attached' => [
        'library' => ['json_field/json_editor.widget'],
        'drupalSettings' => [
          'json_field' => [$hash => $editor_config],
        ],
      ],
    ];

    if (!empty($this->getSetting('schema')) && $this->getSetting('schema_validate')) {
      $element['value']['#element_validate'][] = [get_class($this), 'validateJsonData'];
    }

    return $element;
  }

  /**
   *
   */
  private function getEditorModes() {
    $mode = $this->getSetting('mode');
    // Enforce selected mode in modes options.
    $modes = array_filter($this->getSetting('modes'));
    array_unshift($modes, $mode);
    return array_values(array_unique($modes));
  }

  /**
   * Check the submitted JSON against the configured JSON Schema.
   *
   * @param $element
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   */
  public static function validateJsonData($element, FormStateInterface $form_state) {
    $hash = $element['#attributes']['data-json-editor'];
    $settings = $element['#attached']['drupalSettings']['json_field'][$hash];
    $json_schema = $settings['schema'];

    // Do not use Json::decode since it forces a return as Array.
    $data = json_decode($element['#value']);

    try {
      $schema = Schema::import(json_decode($json_schema));
      $schema->in($data);
    }
    catch (\Exception $e) {
      $form_state->setError($element, t('JSON Schema validation failed.'));
    }
  }

  /**
   * Ensure the JSON schema is itself valid and supported by the PHP library.
   *
   * @param $element
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   */
  public static function validateJsonSchema($element, FormStateInterface $form_state) {
    try {
      // Do not use Json::decode since it forces a return as Array.
      $value = json_decode($element['#value']);
      // If the schema is empty do not try to validate as it will always fail
      // and it will not be possible to save the form.
      if (!empty($value)) {
        $schema = Schema::import($value);
      }
    }
    catch (\Exception $e) {
      $form_state->setError($element, t('JSON Schema is not valid.'));
    }

  }

}
