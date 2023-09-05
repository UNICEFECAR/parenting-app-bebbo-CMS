<?php

namespace Drupal\languagefield\Plugin\Field\FieldType;

use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Field\Plugin\Field\FieldType\LanguageItem as LanguageItemBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\TypedData\OptionsProviderInterface;
use Drupal\Core\Url;
use Drupal\languagefield\Entity\CustomLanguageManager;

/**
 * Plugin implementation of the 'language' field type.
 *
 * @FieldType(
 *   id = "language_field",
 *   label = @Translation("Language"),
 *   description = @Translation("This field stores a language as a Field."),
 *   default_widget = "languagefield_select",
 *   default_formatter = "languagefield_default",
 *   no_ui = FALSE,
 *   constraints = {
 *     "ComplexData" = {
 *       "value" = {
 *         "Length" = {"max" = 12},
 *       }
 *     }
 *   }
 * )
 */
class LanguageItem extends LanguageItemBase implements OptionsProviderInterface {

  /**
   * {@inheritdoc}
   */
  public static function schema(FieldStorageDefinitionInterface $field_definition) {
    return [
      'columns' => [
        'value' => [
          'type' => 'varchar_ascii',
          'length' => $field_definition->getSetting('maxlength'),
          'not null' => FALSE,
        ],
      ],
      'indexes' => [
        'value' => ['value'],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultStorageSettings() {
    $defaultStorageSettings = [
      'maxlength' => CustomLanguageManager::LANGUAGEFIELD_LANGCODE_MAXLENGTH,
      'language_range' => [CustomLanguageManager::LANGUAGEFIELD_LANGUAGES_PREDEFINED => CustomLanguageManager::LANGUAGEFIELD_LANGUAGES_PREDEFINED],
      'included_languages' => [],
      'excluded_languages' => [],
      'groups' => '',
      // @see callback_allowed_values_function()
      'allowed_values_function' => 'languagefield_allowed_values',
    ] + parent::defaultStorageSettings();

    return $defaultStorageSettings;
  }

  /**
   * Gets the unified keys for Formatter and Widget display settings.
   *
   * @param string $usage
   *   Usage.
   *
   * @return array
   *   Array of options.
   */
  public static function settingsOptions($usage = 'formatter') {
    $options = [];

    if (\Drupal::moduleHandler()->moduleExists('languageicons')) {
      if ($usage != 'widget') {
        $options += [
          'icon' => t('Language icons'),
        ];
      }
    }
    $options += [
      'iso' => t('ISO 639-code'),
      'name' => t('Name'),
      'name_native' => t('Display in native language'),
    ];
    return $options;
  }

  /**
   * Get language configuration value.
   *
   * @param string $code
   *   Code.
   *
   * @return string
   *   Value.
   */
  public static function getLanguageConfigurationValues($code) {

    switch ($code) {
      case LanguageInterface::LANGCODE_SITE_DEFAULT:
        $language = \Drupal::languageManager()->getDefaultLanguage();
        $value = $language->getId();
        break;

      case LanguageInterface::LANGCODE_NOT_SPECIFIED:
        $value = LanguageInterface::LANGCODE_NOT_SPECIFIED;
        break;

      case 'current_interface':
        $language = \Drupal::languageManager()->getCurrentLanguage();
        $value = $language->getId();
        break;

      case 'authors_default':
        $user = \Drupal::currentUser();
        $language_code = $user->getPreferredLangcode();
        $language = !empty($language_code)
          ? \Drupal::languageManager()->getLanguage($language_code)
          : \Drupal::languageManager()->getCurrentLanguage();
        $value = $language->getId();
        break;

      default:
        $value = $code;
    }
    return $value;
  }

  /**
   * {@inheritdoc}
   */
  public function applyDefaultValue($notify = TRUE) {
    // Default to no default value.
    $this->setValue(NULL, $notify);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function storageSettingsForm(array &$form, FormStateInterface $form_state, $has_data) {
    $element = parent::storageSettingsForm($form, $form_state, $has_data);

    $settings = $this->getFieldDefinition()
      ->getFieldStorageDefinition()
      ->getSettings();

    $languages = $this->getPossibleOptions();

    $url_1 = (\Drupal::moduleHandler()->moduleExists('language'))
      ? Url::fromRoute('entity.configurable_language.collection', [], [])->toString()
      : '';
    $url_2 = Url::fromRoute('languagefield.custom_language.collection', [], [])->toString();
    $element['language_range'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Enabled languages'),
      '#description' => $this->t("Installed languages can be maintained on the
        <a href=':url_1'>Languages</a> page, when Language module is installed. Custom languages can
        be maintained on the <a href=':url_2'>Custom languages</a> page. (Options marked with '*' are
        typically used as default value in a hidden widget.)", [
          ':url_1' => $url_1,
          ':url_2' => $url_2,
        ]),
      '#required' => TRUE,
      '#default_value' => $settings['language_range'],
      '#options' => [
        // The following are from Languagefield.
        CustomLanguageManager::LANGUAGEFIELD_LANGUAGES_PREDEFINED => $this->t('All predefined languages'),
        // self::LANGUAGEFIELD_LANGUAGES_ENABLED =>
        // $this->t('Enabled installed languages (not functioning yet)'),
        // The following are from Drupal\Core\Language\LanguageInterface.
        LanguageInterface::STATE_CONFIGURABLE => $this->t("All installed (enabled) languages (from <a href=':url_1'>Languages</a> page)",
          [
            ':url_1' => $url_1,
            ':url_2' => $url_2,
          ]),
        // const STATE_CONFIGURABLE = 1; -> 'en', 'de'
        CustomLanguageManager::LANGUAGEFIELD_LANGUAGES_CUSTOM => $this->t("All custom languages (from <a href=':url_2'>Custom languages</a> page)",
          [
            ':url_1' => $url_1,
            ':url_2' => $url_2,
          ]),
        LanguageInterface::STATE_LOCKED => $this->t('All locked languages'),
        // const STATE_LOCKED = 2; -> 'und', 'zxx'
        // const STATE_ALL = 3; -> 'en', 'de', 'und', 'zxx'
        // LanguageInterface::STATE_ALL => $this->t('All installed languages'),
        // const STATE_SITE_DEFAULT = 4; -> 'en'
        // LanguageInterface::STATE_SITE_DEFAULT =>
        // $this->t("The site's default language"),
        // The following are copied from
        // LanguageConfiguration::getDefaultOptions()
        LanguageInterface::LANGCODE_SITE_DEFAULT => $this->t("Site's default language (@language)", [
          '@language' => $this->t(\Drupal::languageManager()
            ->getDefaultLanguage()
            ->getName()
          ),
        ]),
        LanguageInterface::LANGCODE_NOT_SPECIFIED => $this->t('Language neutral'),
        'current_interface' => $this->t('Current interface language') . '*',
        'authors_default' => $this->t("Author's preferred language") . '*',
      ],
    ];

    $element['included_languages'] = [
      '#type' => 'select',
      '#title' => $this->t('Restrict by language'),
      '#default_value' => $settings['included_languages'],
      '#options' => ['' => $this->t('- None -')] + $languages,
      '#description' => $this->t('If no languages are selected, this filter will not be used.'),
      '#multiple' => TRUE,
      '#size' => 10,
    ];

    $element['excluded_languages'] = [
      '#type' => 'select',
      '#title' => $this->t('Excluded languages'),
      '#default_value' => $settings['excluded_languages'],
      '#options' => ['' => $this->t('- None -')] + $languages,
      '#description' => $this->t('This removes individual languages from the list.'),
      '#multiple' => TRUE,
      '#size' => 10,
    ];

    $element['groups'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Language groups'),
      '#default_value' => $settings['groups'],
      '#description' => $this->t("Provides a simple way to group common languages. If no groups are provided, no groupings will be used. Enter in the following format:<br/><code>cn,en,ep,ru<br/>African languages|bs,br<br/>Asian languages|cn,km,fil,ja</code>"),
      '#multiple' => TRUE,
      '#size' => 10,
    ];

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function isEmpty() {
    $value = $this->get('value')->getValue();
    return $value === NULL || $value === '';
  }

  /**
   * {@inheritdoc}
   */
  public function getConstraints() {
    $constraints = [];
    // @Usage When adding parent::getConstraints(), only English is allowed.
    $max_length = $this->getSetting('max_length');
    if ($max_length) {
      $constraint_manager = \Drupal::typedDataManager()
        ->getValidationConstraintManager();
      $constraints[] = $constraint_manager->create('ComplexData', [
        'value' => [
          'Length' => [
            'max' => $max_length,
            'maxMessage' => $this->t('%name: may not be longer than @max characters.', [
              '%name' => $this->getFieldDefinition()->getLabel(),
              '@max' => $max_length,
            ]),
          ],
        ],
      ]);
    }

    return $constraints;
  }

  /**
   * {@inheritdoc}
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   (optional) The user account for which to filter the possible options.
   *   If omitted, all possible options are returned.
   * @param string $format
   *   Extra parameter for formatting options.
   */
  public function getPossibleOptions(AccountInterface $account = NULL, $format = 'en') {
    // Caching as per https://www.drupal.org/node/2661204
    static $possible_options = [];

    $field_name = $this->getFieldDefinition()->getName();
    if (isset($possible_options[$field_name])) {
      return $possible_options[$field_name];
    }

    // No need to cache this data. It is a hardcoded list.
    $languages = \Drupal::languageManager()->getStandardLanguageList();
    // Add the custom languages to the list.
    $languages += CustomLanguageManager::getCustomLanguageList();

    // Format the array to Options format.
    foreach ($languages as $langcode => $language_names) {
      $language_name = '';
      switch ($format) {
        case 'en':
          $language_name .= $this->t($language_names[0]);
          break;

        case 'loc':
          $language_name .= $language_names[1];
          break;

        case 'both':
          $language_name .= $this->t($language_names[0]);
          if (mb_strlen($language_names[1])) {
            $language_name .= ' (' . $language_names[1] . ')';
          }
          $language_name .= ' [' . $langcode . ']';
          break;
      }

      $possible_options[$field_name][$langcode] = $language_name;
    }

    asort($possible_options[$field_name]);

    return $possible_options[$field_name];
  }

  /**
   * {@inheritdoc}
   */
  public function getPossibleValues(AccountInterface $account = NULL) {
    $options = $this->getPossibleOptions($account);
    return array_keys($options);
  }

  /**
   * {@inheritdoc}
   */
  public function getSettableValues(AccountInterface $account = NULL) {
    $options = $this->getSettableOptions($account);
    return array_keys($options);
  }

  /**
   * {@inheritdoc}
   */
  public function getSettableOptions(AccountInterface $account = NULL) {
    // Caching as per https://www.drupal.org/node/2661204
    static $settable_options;

    $field_name = $this->getFieldDefinition()->getName();
    if (!isset($settable_options[$field_name])) {
      $settings = $this->getFieldDefinition()->getSettings();
      $settable_options[$field_name] = CustomLanguageManager::allowedValues($settings);
    }
    return $settable_options[$field_name];
  }

  /* ************************************
   *  End of use Drupal\Core\TypedData\OptionsProviderInterface.
   */

  /* ************************************
   *  Start of contrib functions.
   */

  /**
   * Gets the Native name. (Should be added to \Drupal\Core\Language\Language.)
   */
  public function getNativeName() {
    $value = $this->value;
    switch ($value) {
      case 'und':
        $name = '';
        break;

      default:
        $standard_languages = \Drupal::languageManager()->getStandardLanguageList();
        $standard_languages += CustomLanguageManager::getCustomLanguageList();
        $name = $standard_languages[$value][1];
        break;
    }

    return $name;
  }

}
