<?php

namespace Drupal\layout_section_classes;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Layout\LayoutDefault;
use Drupal\Core\Plugin\PluginFormInterface;

/**
 * A layout plugin class for layouts with selectable classes.
 */
class ClassyLayout extends LayoutDefault implements PluginFormInterface {

  /**
   * {@inheritdoc}
   */
  public function build(array $regions) {
    $build = parent::build($regions);
    $classes = $this->configuration['additional']['classes'] ?? [];
    $build['#attributes']['class'] = $build['#attributes']['class'] ?? [];
    $definitions = $this->getPluginDefinition()->get('classes');
    foreach ($classes as $key => $class_set) {
      $definition = $definitions[$key];
      if (is_string($class_set) && $class_set) {
        $build['#attributes']['class'][] = $class_set;
      }
      if (is_array($class_set)) {
        $build['#attributes']['class'] = array_merge($build['#attributes']['class'], array_filter($class_set));
      }
      if ($definition['region_classes'] ?? FALSE) {
        $class_set = (array) $class_set;
        foreach ($class_set as $class) {
          if ($region_classes = ($definition['region_classes'][$class] ?? FALSE)) {
            foreach ($region_classes as $region => $region_class) {
              $build[$region]['#attributes']['class'][] = $region_class;
            }
          }
        }
      }
      if ($definition['attributes'] ?? FALSE) {
        $class_set = (array) $class_set;
        foreach ($class_set as $class) {
          if ($attributes = ($definition['attributes'][$class] ?? FALSE)) {
            foreach ($attributes as $attribute => $attribute_value) {
              $build['#attributes'][$attribute] = $attribute_value;
            }
          }
        }
      }
    }
    return $build;
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);

    $form['classes'] = [
      '#type' => 'container',
      '#tree' => TRUE,
    ];
    $plugin_configuration = $this->configuration['additional']['classes'] ?? [];
    foreach ($this->getPluginDefinition()->get('classes') as $key => $class_definition) {
      if (empty($class_definition['options']) || !is_array($class_definition['options'])) {
        throw new \Exception('The "options" key is required for layout class definitions.');
      }
      $definition_default = $class_definition['default'] ?? NULL;
      $form['classes'][$key] = [
        '#title' => $class_definition['label'] ?? $this->t('Classes'),
        '#type' => 'select',
        '#multiple' => $class_definition['multiple'] ?? FALSE,
        '#options' => $class_definition['options'],
        '#required' => $class_definition['required'] ?? FALSE,
        '#default_value' => $plugin_configuration[$key] ?? $definition_default,
        '#description' => $class_definition['description'] ?? '',
      ];
      // Add an empty option if the selection is option or it's required with no
      // default.
      if (!$form['classes'][$key]['#required'] || ($form['classes'][$key]['#required'] && $form['classes'][$key]['#default_value'] === NULL)) {
        $form['classes'][$key]['#empty_option'] = $this->t('- Select -');
      }
    }
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    parent::submitConfigurationForm($form, $form_state);
    $this->configuration['additional']['classes'] = $form_state->getValue('classes');
  }

}
