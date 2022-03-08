<?php

namespace Drupal\feeds\Form;

use Drupal\Component\Utility\Html;
use Drupal\Core\Config\Entity\ConfigEntityStorageInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\PluginFormInterface;
use Drupal\feeds\Exception\MissingTargetException;
use Drupal\feeds\FeedTypeInterface;
use Drupal\feeds\MissingTargetDefinition;
use Drupal\feeds\Plugin\Type\MappingPluginFormInterface;
use Drupal\feeds\Plugin\Type\Target\ConfigurableTargetInterface;
use Drupal\feeds\Plugin\Type\Target\TargetInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a form for mapping settings.
 */
class MappingForm extends FormBase {

  /**
   * The feed type.
   *
   * @var \Drupal\feeds\FeedTypeInterface
   */
  protected $feedType;

  /**
   * The feed type storage.
   *
   * @var \Drupal\Core\Config\Entity\ConfigEntityStorageInterface
   */
  protected $feedTypeStorage;

  /**
   * The mappings for this feed type.
   *
   * @var array
   */
  protected $mappings;

  /**
   * Constructs a new MappingForm object.
   *
   * @param \Drupal\Core\Config\Entity\ConfigEntityStorageInterface $feed_type_storage
   *   The feed type storage.
   */
  public function __construct(ConfigEntityStorageInterface $feed_type_storage) {
    $this->feedTypeStorage = $feed_type_storage;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager')->getStorage('feeds_feed_type')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'feeds_mapping_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, FeedTypeInterface $feeds_feed_type = NULL) {
    $feed_type = $this->feedType = $feeds_feed_type;
    $this->targets = $targets = $feed_type->getMappingTargets();

    // Denormalize targets.
    $this->sourceOptions = [];
    foreach ($feed_type->getMappingSources() as $key => $info) {
      $this->sourceOptions[$key] = $info['label'];
    }
    $this->sourceOptions = $this->sortOptions($this->sourceOptions);
    $this->sourceOptions = [
      '__new' => $this->t('New source...'),
      '----' => '----',
    ] + $this->sourceOptions;

    $target_options = [];
    foreach ($targets as $key => $target) {
      $target_options[$key] = $target->getLabel() . ' (' . $key . ')';
    }
    $target_options = $this->sortOptions($target_options);

    if ($form_state->getValues()) {
      $this->processFormState($form, $form_state);

      $triggering_element = $form_state->getTriggeringElement() + ['#op' => ''];

      switch ($triggering_element['#op']) {
        case 'cancel':
        case 'configure':
          // These don't need a configuration message.
          break;

        default:
          $this->messenger()->addWarning($this->t('Your changes will not be saved until you click the <em>Save</em> button at the bottom of the page.'));
          break;
      }
    }

    $form['#tree'] = TRUE;
    $form['#prefix'] = '<div id="feeds-mapping-form-ajax-wrapper">';
    $form['#suffix'] = '</div>';
    $form['#attached']['library'][] = 'feeds/feeds';

    $table = [
      '#type' => 'table',
      '#header' => [
        $this->t('Source'),
        $this->t('Target'),
        $this->t('Summary'),
        $this->t('Configure'),
        $this->t('Unique'),
        $this->t('Remove'),
      ],
      '#sticky' => TRUE,
    ];

    foreach ($feed_type->getMappings() as $delta => $mapping) {
      $table[$delta] = $this->buildRow($form, $form_state, $mapping, $delta);
    }

    $table['add']['source']['#markup'] = '';

    $table['add']['target'] = [
      '#type' => 'select',
      '#title' => $this->t('Add a target'),
      '#title_display' => 'invisible',
      '#options' => $target_options,
      '#empty_option' => $this->t('- Select a target -'),
      '#parents' => ['add_target'],
      '#default_value' => NULL,
      '#ajax' => [
        'callback' => '::ajaxCallback',
        'wrapper' => 'feeds-mapping-form-ajax-wrapper',
        'effect' => 'none',
        'progress' => 'none',
      ],
    ];

    $table['add']['summary']['#markup'] = '';
    $table['add']['configure']['#markup'] = '';
    $table['add']['unique']['#markup'] = '';
    $table['add']['remove']['#markup'] = '';

    $form['mappings'] = $table;

    // Legend explaining source and target elements.
    $form['legendset'] = $this->buildLegend($form, $form_state);

    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save'),
      '#button_type' => 'primary',
    ];

    // Allow plugins to hook into the mapping form.
    foreach ($feed_type->getPlugins() as $plugin) {
      if ($plugin instanceof MappingPluginFormInterface) {
        $plugin->mappingFormAlter($form, $form_state);
      }
    }

    return $form;
  }

  /**
   * Builds a single mapping row.
   *
   * @param array $form
   *   The complete mapping form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the complete form.
   * @param array $mapping
   *   A single configured mapper, which is expected to consist of the
   *   following:
   *   - map
   *     An array of target subfield => source field.
   *   - target
   *     The name of the target plugin.
   *   - unique
   *     (optional) An array of subfield => enabled as unique.
   *   - settings
   *     (optional) An array of settings for the target.
   * @param int $delta
   *   The index number of the mapping.
   *
   * @return array
   *   The form structure for a single mapping row.
   */
  protected function buildRow(array $form, FormStateInterface $form_state, array $mapping, $delta) {
    try {
      /** @var \Drupal\feeds\Plugin\Type\TargetInterface $plugin */
      $plugin = $this->feedType->getTargetPlugin($delta);
    }
    catch (MissingTargetException $e) {
      // The target plugin is missing!
      $this->messenger()->addWarning($e->getMessage());
      watchdog_exception('feeds', $e);
      $plugin = NULL;
    }

    // Check if the target exists.
    if (!empty($this->targets[$mapping['target']])) {
      /** @var \Drupal\feeds\TargetDefinitionInterface $target_definition */
      $target_definition = $this->targets[$mapping['target']];
    }
    else {
      // The target is missing! Create a placeholder target definition, so that
      // the mapping row is still being displayed.
      $target_definition = MissingTargetDefinition::create();
    }

    $ajax_delta = -1;
    $triggering_element = (array) $form_state->getTriggeringElement() + ['#op' => ''];
    if ($triggering_element['#op'] === 'configure') {
      $ajax_delta = $form_state->getTriggeringElement()['#delta'];
    }

    $row = ['#attributes' => ['class' => ['draggable', 'tabledrag-leaf']]];
    $row['map'] = ['#type' => 'container'];
    $row['targets'] = [
      '#theme' => 'item_list',
      '#items' => [],
      '#attributes' => ['class' => ['target']],
    ];

    if ($target_definition instanceof MissingTargetDefinition) {
      $row['#attributes']['class'][] = 'missing-target';
      $row['#attributes']['class'][] = 'color-error';
    }

    foreach ($mapping['map'] as $column => $source) {
      if (!$target_definition->hasProperty($column)) {
        unset($mapping['map'][$column]);
        continue;
      }
      $row['map'][$column] = [
        'select' => [
          '#type' => 'select',
          '#options' => $this->sourceOptions,
          '#default_value' => $source,
          '#empty_option' => $this->t('- Select a source -'),
          '#attributes' => ['class' => ['feeds-table-select-list']],
        ],
        '__new' => [
          '#type' => 'container',
          '#states' => [
            'visible' => [
              ':input[name="mappings[' . $delta . '][map][' . $column . '][select]"]' => ['value' => '__new'],
            ],
          ],
          'value' => [
            '#type' => 'textfield',
            '#states' => [
              'visible' => [
                ':input[name="mappings[' . $delta . '][map][' . $column . '][select]"]' => ['value' => '__new'],
              ],
            ],
          ],
          'machine_name' => [
            '#type' => 'machine_name',
            '#machine_name' => [
              'exists' => [$this, 'customSourceExists'],
              'source' => ['mappings', $delta, 'map', $column, '__new', 'value'],
              'standalone' => TRUE,
              'label' => '',
            ],
            '#default_value' => '',
            '#required' => FALSE,
            '#disabled' => '',
          ],
        ],
      ];

      $label = Html::escape($target_definition->getLabel() . ' (' . $mapping['target'] . ')');

      if (count($mapping['map']) > 1) {
        $desc = $target_definition->getPropertyLabel($column);
      }
      else {
        $desc = $target_definition->getDescription();
      }
      if ($desc) {
        $label .= ': ' . $desc;
      }
      $row['targets']['#items'][] = $label;
    }

    $default_button = [
      '#ajax' => [
        'callback' => '::ajaxCallback',
        'wrapper' => 'feeds-mapping-form-ajax-wrapper',
        'effect' => 'fade',
        'progress' => 'none',
      ],
      '#delta' => $delta,
    ];

    $row['settings']['#markup'] = '';
    $row['configure']['#markup'] = '';
    if ($plugin && $this->pluginHasSettingsForm($plugin, $form_state)) {
      if ($delta == $ajax_delta) {
        $row['settings'] = $plugin->buildConfigurationForm([], $form_state);
        $row['settings']['actions'] = [
          '#type' => 'actions',
          'save_settings' => $default_button + [
            '#type' => 'submit',
            '#button_type' => 'primary',
            '#value' => $this->t('Update'),
            '#op' => 'update',
            '#name' => 'target-save-' . $delta,
          ],
          'cancel_settings' => $default_button + [
            '#type' => 'submit',
            '#value' => $this->t('Cancel'),
            '#op' => 'cancel',
            '#name' => 'target-cancel-' . $delta,
            '#limit_validation_errors' => [[]],
          ],
        ];
        $row['#attributes']['class'][] = 'feeds-mapping-settings-editing';
      }
      else {
        $row['settings'] = [
          '#parents' => ['config_summary', $delta],
        ] + $this->buildSummary($plugin);
        $row['configure'] = $default_button + [
          '#type' => 'image_button',
          '#op' => 'configure',
          '#name' => 'target-settings-' . $delta,
          '#src' => 'core/misc/icons/787878/cog.svg',
        ];
      }
    }
    elseif ($plugin instanceof ConfigurableTargetInterface) {
      $summary = $this->buildSummary($plugin);
      if (!empty($summary)) {
        $row['settings'] = [
          '#parents' => ['config_summary', $delta],
        ] + $this->buildSummary($plugin);
      }
    }

    $mappings = $this->feedType->getMappings();

    foreach ($mapping['map'] as $column => $source) {
      if ($target_definition->isUnique($column)) {
        $row['unique'][$column] = [
          '#title' => $this->t('Unique'),
          '#type' => 'checkbox',
          '#default_value' => !empty($mappings[$delta]['unique'][$column]),
          '#title_display' => 'invisible',
        ];
      }
      else {
        $row['unique']['#markup'] = '';
      }
    }

    if ($delta != $ajax_delta) {
      $row['remove'] = $default_button + [
        '#title' => $this->t('Remove'),
        '#type' => 'checkbox',
        '#default_value' => FALSE,
        '#title_display' => 'invisible',
        '#parents' => ['remove_mappings', $delta],
        '#remove' => TRUE,
      ];
    }
    else {
      $row['remove']['#markup'] = '';
    }

    return $row;
  }

  /**
   * Checks if the given plugin has a settings form.
   *
   * @param \Drupal\feeds\Plugin\Type\Target\TargetInterface $plugin
   *   The target plugin.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the complete form.
   *
   * @return bool
   *   TRUE if it has a settings form. False otherwise.
   */
  protected function pluginHasSettingsForm(TargetInterface $plugin, FormStateInterface $form_state) {
    if (!$plugin instanceof ConfigurableTargetInterface) {
      // Target is not configurable.
      return FALSE;
    }

    if (!$plugin instanceof PluginFormInterface) {
      // Target plugin does not provide a settings form.
      return FALSE;
    }

    $settings_form = $plugin->buildConfigurationForm([], $form_state);
    return !empty($settings_form);
  }

  /**
   * Builds the summary for a configurable target.
   *
   * @param \Drupal\feeds\Plugin\Type\Target\ConfigurableTargetInterface $plugin
   *   A configurable target plugin.
   *
   * @return array
   *   A renderable array.
   */
  protected function buildSummary(ConfigurableTargetInterface $plugin) {
    // Display a summary of the current plugin settings.
    $summary = $plugin->getSummary();
    if (!empty($summary)) {
      if (!is_array($summary)) {
        $summary = [$summary];
      }

      return [
        '#type' => 'inline_template',
        '#template' => '<div class="plugin-summary">{{ summary|safe_join("<br />") }}</div>',
        '#context' => ['summary' => $summary],
        '#cell_attributes' => ['class' => ['plugin-summary-cell']],
      ];
    }

    return [];
  }

  /**
   * Builds legend which explains source and target elements.
   *
   * @param array $form
   *   The complete mapping form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the complete form.
   *
   * @return array
   *   The legend form element.
   */
  protected function buildLegend(array $form, FormStateInterface $form_state) {
    $element = [
      '#type' => 'details',
      '#title' => $this->t('Legend'),
      'sources' => [
        '#caption' => $this->t('Sources'),
        '#type' => 'table',
        '#header' => [
          $this->t('Name'),
          $this->t('Machine name'),
          $this->t('Description'),
        ],
        '#rows' => [],
      ],
      'targets' => [
        '#caption' => $this->t('Targets'),
        '#type' => 'table',
        '#header' => [
          $this->t('Name'),
          $this->t('Machine name'),
          $this->t('Description'),
        ],
        '#rows' => [],
      ],
    ];

    foreach ($this->feedType->getMappingSources() as $key => $info) {
      $element['sources']['#rows'][$key] = [
        'label' => $info['label'],
        'name' => $key,
        'description' => isset($info['description']) ? $info['description'] : NULL,
      ];
    }
    asort($element['sources']['#rows']);

    /** @var \Drupal\feeds\TargetDefinitionInterface $definition */
    foreach ($this->targets as $key => $definition) {
      $element['targets']['#rows'][$key] = [
        'label' => $definition->getLabel(),
        'name' => $key,
        'description' => $definition->getDescription(),
      ];
    }

    return $element;
  }

  /**
   * Checks if a particular source already exists on the saved feed type.
   *
   * @param string $name
   *   The name to check.
   * @param array $element
   *   The form element using the machine name.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the complete form.
   *
   * @return bool
   *   True if the source already exists, false otherwise.
   */
  public function customSourceExists($name, array $element, FormStateInterface $form_state) {
    // Get unchanged feed type.
    $unchanged_feed_type = $this->feedTypeStorage->loadUnchanged($this->feedType->getOriginalId());
    // Check if the custom source already exists on the last saved feed type.
    if ($unchanged_feed_type && $unchanged_feed_type->customSourceExists($name)) {
      return TRUE;
    }

    // Get the delta and the column of the passed form element. The delta is the
    // position of the mapping row on the form, the column refers to a property
    // of the target plugin.
    $element_delta = $element['#array_parents'][1];
    $element_column = $element['#array_parents'][3];

    // Check other mappings.
    foreach ($form_state->getValue('mappings') as $delta => $mapping) {
      foreach ($mapping['map'] as $column => $value) {
        // Check if this value belongs to our own element.
        if ($delta == $element_delta && $element_column == $column) {
          // Don't compare name to our own element.
          continue;
        }

        // Check if for this mapping row a new source is selected.
        if ($value['select'] == '__new') {
          // Compare the new source's name with the name to check.
          $map_name = $mappings[$delta]['map'][$column] = $value['__new']['machine_name'];
          if ($name == $map_name) {
            // Name is already used by an other mapper.
            return TRUE;
          }
        }
      }
    }

    // Name does not exist yet for custom source.
    return FALSE;
  }

  /**
   * Processes the form state, populating the mappings on the feed type.
   *
   * @param array $form
   *   The complete mapping form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the complete form.
   */
  protected function processFormState(array $form, FormStateInterface $form_state) {
    // Process any plugin configuration.
    $triggering_element = $form_state->getTriggeringElement() + ['#op' => ''];
    if ($triggering_element['#op'] === 'update') {
      $this->feedType->getTargetPlugin($triggering_element['#delta'])->submitConfigurationForm($form, $form_state);
    }

    $mappings = $this->feedType->getMappings();
    foreach (array_filter((array) $form_state->getValue('mappings', [])) as $delta => $mapping) {
      foreach ($mapping['map'] as $column => $value) {
        if ($value['select'] == '__new') {
          // Add a new source.
          $this->feedType->addCustomSource($value['__new']['machine_name'], [
            'label' => $value['__new']['value'],
          ] + $value['__new']);
          $mappings[$delta]['map'][$column] = $value['__new']['machine_name'];
        }
        else {
          $mappings[$delta]['map'][$column] = $value['select'];
        }
      }
      if (isset($mapping['unique'])) {
        $mappings[$delta]['unique'] = array_filter($mapping['unique']);
      }
    }
    $this->feedType->setMappings($mappings);

    // Remove any mappings.
    foreach (array_keys(array_filter($form_state->getValue('remove_mappings', []))) as $delta) {
      $this->feedType->removeMapping($delta);
    }

    // Add any targets.
    if ($new_target = $form_state->getValue('add_target')) {
      $map = array_fill_keys($this->targets[$new_target]->getProperties(), '');
      $this->feedType->addMapping([
        'target' => $new_target,
        'map' => $map,
      ]);
    }

    // Allow the #default_value of 'add_target' to be reset.
    $input =& $form_state->getUserInput();
    unset($input['add_target']);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    if (isset($form_state->getTriggeringElement()['#delta'])) {
      $delta = $form_state->getTriggeringElement()['#delta'];
      $this->feedType->getTargetPlugin($delta)->validateConfigurationForm($form, $form_state);
      $form_state->setRebuild();
    }
    else {
      // Allow plugins to validate the mapping form.
      foreach ($this->feedType->getPlugins() as $plugin) {
        if ($plugin instanceof MappingPluginFormInterface) {
          $plugin->mappingFormValidate($form, $form_state);
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->processFormState($form, $form_state);

    // Allow plugins to hook into the mapping form.
    foreach ($this->feedType->getPlugins() as $plugin) {
      if ($plugin instanceof MappingPluginFormInterface) {
        $plugin->mappingFormSubmit($form, $form_state);
      }
    }

    $this->feedType->save();
  }

  /**
   * Builds an options list from mapping sources or targets.
   *
   * @param array $options
   *   The options to sort.
   *
   * @return array
   *   The sorted options.
   */
  protected function sortOptions(array $options) {
    $result = [];
    foreach ($options as $k => $v) {
      if (is_array($v) && !empty($v['label'])) {
        $result[$k] = $v['label'];
      }
      elseif (is_array($v)) {
        $result[$k] = $k;
      }
      else {
        $result[$k] = $v;
      }
    }
    asort($result);

    return $result;
  }

  /**
   * Callback for ajax requests.
   *
   * @return array
   *   The form element to return.
   */
  public static function ajaxCallback(array $form, FormStateInterface $form_state) {
    return $form;
  }

  /**
   * Page title callback.
   *
   * @return string
   *   The title of the mapping page.
   */
  public function mappingTitle(FeedTypeInterface $feeds_feed_type) {
    return $this->t('Mappings @label', ['@label' => $feeds_feed_type->label()]);
  }

}
