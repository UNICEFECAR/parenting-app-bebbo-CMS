<?php

namespace Drupal\languagefield\Plugin\Field\FieldWidget;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\languagefield\Plugin\Field\FieldType\LanguageItem;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the 'languagefield_autocomplete' widget.
 *
 * @FieldWidget(
 *   id = "languagefield_autocomplete",
 *   label = @Translation("Language autocomplete"),
 *   field_types = {
 *     "language_field",
 *   }
 * )
 */
class LanguageAutocompleteWidget extends WidgetBase implements ContainerFactoryPluginInterface {

  /**
   * CacheData.
   *
   * @var \Drupal\Core\Cache\CacheBackendInterface
   *   CacheData.
   */
  protected $cacheData;

  /**
   * LanguageAutocompleteWidget constructor.
   *
   * @param string $plugin_id
   *   Plugin id.
   * @param mixed $plugin_definition
   *   Plugin definition.
   * @param \Drupal\Core\Field\FieldDefinitionInterface $field_definition
   *   Field definition.
   * @param array $settings
   *   Settings.
   * @param array $third_party_settings
   *   Third party settings.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cacheData
   *   Cache data.
   */
  public function __construct($plugin_id, $plugin_definition, FieldDefinitionInterface $field_definition, array $settings, array $third_party_settings, CacheBackendInterface $cacheData) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $third_party_settings);
    $this->cacheData = $cacheData;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static($plugin_id, $plugin_definition, $configuration['field_definition'], $configuration['settings'], $configuration['third_party_settings'], $container->get('cache.data'));
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return [
      'size' => '60',
      'autocomplete_route_name' => 'languagefield.autocomplete',
      'placeholder' => '',
    ] + parent::defaultSettings();
  }

  /**
   * Form element validate handler for language autocomplete element.
   *
   * @param mixed $element
   *   Element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Form state interface.
   */
  public static function validateElement($element, FormStateInterface $form_state) {
    if (!$input = $element['#value']) {
      return;
    }

    $languages = $element['#languagefield_options'];
    $langcode = array_search($input, $languages);
    if (!empty($langcode)) {
      $form_state->setValueForElement($element, $langcode);
    }
    else {
      $form_state->setError($element, t('An unexpected language is entered.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    /** @var \Drupal\languagefield\Plugin\Field\FieldType\LanguageItem $item */
    $item = $items[$delta];
    $value = isset($item->value) ? $item->value : NULL;

    $item_definition = (is_object($item)) ? $item : new LanguageItem($items->getItemDefinition());
    $settable_languages = $item_definition->getSettableOptions();
    $possible_languages = $item_definition->getPossibleOptions();

    $element['value'] = $element + [
      '#type' => 'textfield',
      '#default_value' => isset($possible_languages[$value]) ? $possible_languages[$value] : '',
      '#languagefield_options' => $settable_languages,
      '#autocomplete_route_name' => $this->getSetting('autocomplete_route_name'),
      '#autocomplete_route_parameters' => [
        'entity_type' => $this->fieldDefinition->get('entity_type'),
        'bundle' => $this->fieldDefinition->get('bundle'),
        'field_name' => $this->fieldDefinition->get('field_name'),
      ],
      '#size' => $this->getSetting('size'),
      '#placeholder' => $this->getSetting('placeholder'),
      '#maxlength' => 255,
      '#element_validate' => [[get_class($this), 'validateElement']],
    ];

    return $element;
  }

}
