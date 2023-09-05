<?php

namespace Drupal\languagefield\Plugin\Field\FieldFormatter;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemInterface;
use Drupal\Core\Field\Plugin\Field\FieldFormatter\StringFormatter;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\languagefield\Entity\CustomLanguageManager;
use Drupal\languagefield\Plugin\Field\FieldType\LanguageItem;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the 'language_field' formatter.
 *
 * @FieldFormatter(
 *   id = "languagefield_default",
 *   label = @Translation("Language"),
 *   field_types = {
 *     "language_field",
 *   }
 * )
 */
class LanguageFormatter extends StringFormatter implements ContainerFactoryPluginInterface {

  /**
   * ModuleHandler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   *   ModuleHandler service.
   */
  protected $moduleHandler;

  /**
   * LanguageFormatter constructor.
   *
   * @param string $plugin_id
   *   Plugin id.
   * @param mixed $plugin_definition
   *   Plugin definition.
   * @param \Drupal\Core\Field\FieldDefinitionInterface $field_definition
   *   Field definition.
   * @param array $settings
   *   Settings.
   * @param string $label
   *   Label.
   * @param string $view_mode
   *   View mode.
   * @param array $third_party_settings
   *   Third party settings.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   EntityTypeManager service.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $moduleHandler
   *   Module handler.
   */
  public function __construct($plugin_id, $plugin_definition, FieldDefinitionInterface $field_definition, array $settings, $label, $view_mode, array $third_party_settings, EntityTypeManagerInterface $entity_type_manager, ModuleHandlerInterface $moduleHandler) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $label, $view_mode, $third_party_settings, $entity_type_manager);
    $this->moduleHandler = $moduleHandler;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static($plugin_id, $plugin_definition, $configuration['field_definition'], $configuration['settings'], $configuration['label'], $configuration['view_mode'], $configuration['third_party_settings'], $container->get('entity_type.manager'), $container->get('module_handler'));
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    $settings = parent::defaultSettings();
    $settings['format'] = ['name' => 'name'];
    return $settings;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $form = parent::settingsForm($form, $form_state);
    $form['format'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Display'),
      '#description' => $this->t('Select the elements you want to show. The elements will be concatenated when showing the field.'),
      '#default_value' => $this->getSetting('format'),
      '#options' => LanguageItem::settingsOptions('formatter'),
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $summary = parent::settingsSummary();

    $settings = $this->getSettings()['format'];
    $options = LanguageItem::settingsOptions('formatter');

    if (empty($settings)) {
      $summary[] = $this->t('** Not set **');
    }
    else {
      foreach ($settings as $value) {
        switch ($value) {
          case '0':
            // Option is not selected.
            break;

          default:
            $summary[] = isset($options[$value]) ? $options[$value] : '...';
            break;
        }
      }
    }
    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  protected function viewValue(FieldItemInterface $item) {
    $settings = $this->getSettings();

    $langcode = $item->value;

    // Do NOT use \Drupal::languageManager,
    // since it only uses installed languages.
    // Do call LanguageItem::getLanguage to have
    // the benefit of added custom languages.
    $language = CustomLanguageManager::createFromLangcode($langcode);

    $language_translated_name = $this->t($language->getName());
    // Create the markup for this value.
    $markup = [];

    if (!empty($settings['format']['iso'])) {
      $markup[] = $langcode;
    }
    if (!empty($settings['format']['name'])) {
      // @todo Use language of user of of content entity?
      $markup[] = $language_translated_name;
    }
    if (!empty($settings['format']['name_native'])) {
      // @todo Create feature request to add function to D8 core.
      $native_name = $item->getNativeName();
      $markup[] = (empty($settings['format']['name'])) ? $native_name : '(' . $native_name . ')';
    }

    $markup = (empty($markup)) ? $language_translated_name : implode(' ', $markup);

    $result = [
      '#type' => 'processed_text',
      '#context' => ['value' => $item->value],
      '#format' => $item->format,
    ];

    // Add variables for languageicons theme function.
    if (!empty($settings['format']['icon']) && $this->moduleHandler->moduleExists('languageicons')) {
      $result += [
        'language' => $language,
        'title' => $markup,
      ];
      languageicons_link_add($result, $language_translated_name);
      unset($result['language']);
      unset($result['html']);
    }
    else {
      // The text value has no text format assigned to it, so the user input
      // should equal the output, including newlines.
      $result += [
        '#text' => $markup,
      ];
    }

    return $result;
  }

}
