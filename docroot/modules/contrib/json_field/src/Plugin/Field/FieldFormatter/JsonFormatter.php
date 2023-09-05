<?php

namespace Drupal\json_field\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;

/**
 * Plugin implementation of the 'json' formatter.
 *
 * @FieldFormatter(
 *   id = "json",
 *   label = @Translation("Plain text"),
 *   field_types = {
 *     "json",
 *     "json_native",
 *     "json_native_binary",
 *   },
 *   quickedit = {
 *     "editor" = "plain_text"
 *   }
 * )
 */
class JsonFormatter extends FormatterBase {

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return [
      'attach_library' => TRUE,
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $form = parent::settingsForm($form, $form_state);

    $form['attach_library'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Attach library'),
      '#description' => $this->t('By default the JSONView JS library will be loaded to provide a resonable experience viewing the JSON data. Disabling this option will prevent the JSONView JS library from being loaded.'),
      '#default_value' => $this->getSetting('attach_library'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $summary = [];

    $attached = $this->getSetting('attach_library') ?? TRUE;
    $summary['attach_library'] = $this->t('Attach library: %attach_library', [
      '%attach_library' => $attached ? $this->t('Yes') : $this->t('No'),
    ]);

    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $elements = [];

    if ($this->getSetting('attach_library') ?? TRUE) {
      $elements['#attached']['library'][] = 'json_field/json_field.formatter';
    }

    foreach ($items as $delta => $item) {
      $elements[$delta] = [
        '#type' => 'json_text',
        '#text' => $item->value,
        '#langcode' => $langcode,
      ];
    }

    return $elements;
  }

}
