<?php

namespace Drupal\languagefield\Entity;

use Drupal\Core\Language\LanguageInterface;
use Drupal\language\Entity\ConfigurableLanguage;

/**
 * Defines the CustomLanguage entity.
 *
 * The CustomLanguage entity stores information about custom languages added to
 * be used by the language field.
 */
class CustomLanguageManager {

  const LANGUAGEFIELD_LANGCODE_MAXLENGTH = 12;

  // Define own variants. Keep away from the LanguageInterface constants.
  // LanguageInterface::STATE_CONFIGURABLE = 1; -> 'en', 'de'
  // LanguageInterface::STATE_LOCKED = 2; -> 'und', 'zxx'
  // LanguageInterface::STATE_ALL = 3; -> 'en', 'de', 'und', 'zxx'
  // LanguageInterface::STATE_SITE_DEFAULT = 4; -> 'en'
  // All predefined + custom languages.
  const LANGUAGEFIELD_LANGUAGES_PREDEFINED = 11;

  // All custom languages from languagefield.
  const LANGUAGEFIELD_LANGUAGES_CUSTOM = 12;

  /**
   * The list of Custom languages.
   *
   * @var array
   */
  protected static $customLanguages;

  /**
   * The unique manager.
   *
   * @var CustomLanguageManager
   */
  protected static $customLanguageManager;

  /**
   * Gets the unique manager.
   *
   * @return \Drupal\languagefield\Entity\CustomLanguageManager
   *   Language manager.
   */
  public static function getCustomLanguageManager() {
    if (static::$customLanguageManager == NULL) {
      static::$customLanguageManager = new CustomLanguageManager();
    }
    return static::$customLanguageManager;
  }

  /**
   * Creates a configurable language object from a langcode.
   *
   * Copy from $language = \Drupal::languageManager()->getLanguage($langcode);
   * Do NOT use languageManager, since it only uses installed, not custom
   * languages.
   *
   * @param string $langcode
   *   The language code to use to create the object.
   *
   * @return \Drupal\Core\Entity\EntityInterface|\Drupal\language\Entity\ConfigurableLanguage|\Drupal\languagefield\Entity\CustomLanguage
   *   Custom language.
   *
   * @see \Drupal\Core\Language\LanguageManager::getStandardLanguageList()
   */
  public static function createFromLangcode($langcode) {
    $custom_languages = CustomLanguageManager::getCustomLanguageList();

    if (isset($custom_languages[$langcode])) {
      // A known predefined language, details will be filled in properly.
      $custom_language = CustomLanguage::create([
        'id' => $langcode,
        'label' => $custom_languages[$langcode][0],
        'direction' => isset($custom_languages[$langcode][2]) ? $custom_languages[$langcode][2] : LanguageInterface::DIRECTION_LTR,
      ]);
      return $custom_language;
    }

    // $langcode refers to a standard language.
    return ConfigurableLanguage::createFromLangcode($langcode);
  }

  /**
   * Gets the list of Custom languages as an array.
   *
   * Resembling getStandardLanguageList.
   *
   * @return array
   *   Array of languages.
   */
  public static function getCustomLanguageList() {
    $result = [];

    $languages = CustomLanguageManager::getCustomLanguages();
    foreach ($languages as $language) {
      $result[$language->id()] = [
        $language->label(),
        $language->getNativeName(),
      ];
    }
    return $result;
  }

  /**
   * Gets the list of Custom languages as an array.
   *
   * Resembling getStandardLanguageList.
   *
   * @return \Drupal\languagefield\Entity\CustomLanguageInterface[]
   *   Custom language interface.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public static function getCustomLanguages() {
    if (static::$customLanguages == NULL) {
      $storage = \Drupal::entityTypeManager()->getStorage('custom_language');
      static::$customLanguages = $storage->loadMultiple();
    }
    return static::$customLanguages;
  }

  /**
   * Gets a list of allowed values.
   *
   * @param array $settings
   *   Settings.
   *
   * @return array|\Drupal\Core\Language\LanguageInterface[]
   *   Array or language interface.
   */
  public static function allowedValues(array $settings) {
    $languages = [];
    $subsets = $settings['language_range'];

    foreach ($subsets as $subset => $active) {
      $subsettable_languages = [];
      if (!$active) {
        continue;
      }

      switch ($subset) {
        case LanguageInterface::STATE_CONFIGURABLE:
        case LanguageInterface::STATE_ALL:
          $subsettable_languages = \Drupal::languageManager()
            ->getLanguages($subset);
          // Convert to $langcode => $name array.
          foreach ($subsettable_languages as $langcode => $language) {
            if (!$language->isLocked()) {
              $subsettable_languages[$langcode] = $language->getName();
            }
          }
          break;

        case LanguageInterface::STATE_LOCKED:
          $subsettable_languages = \Drupal::languageManager()
            ->getLanguages($subset);
          foreach ($subsettable_languages as $langcode => $language) {
            // @todo Fix/test LanguageInterface::STATE_LOCKED for D8-10,
            // for both standard and custom languages.
            if ($language->isLocked()) {
              $subsettable_languages[$langcode] = t('- @name -', ['@name' => $language->getName()]);
            }
          }
          break;

        case LanguageInterface::LANGCODE_NOT_SPECIFIED:
        case CustomLanguageManager::LANGUAGEFIELD_LANGUAGES_CUSTOM:
        case LanguageInterface::LANGCODE_SITE_DEFAULT:
        case 'current_interface':
        case 'authors_default':
          $subsettable_languages = self::getLanguageConfigurationOptions($subset);
          break;

        case CustomLanguageManager::LANGUAGEFIELD_LANGUAGES_PREDEFINED:
          // 'All predefined languages'.
          $standard_languages = \Drupal::languageManager()
            ->getStandardLanguageList();
          foreach ($standard_languages as $langcode => $language_names) {
            $subsettable_languages[$langcode] = t($language_names[0]);
          }
          asort($subsettable_languages);

          break;
      }
      $languages += $subsettable_languages;
    }

    $included_languages = array_filter($settings['included_languages']);
    if (!empty($included_languages)) {
      $languages = array_intersect_key($languages, $included_languages);
    }
    if (!empty($settings['excluded_languages'])) {
      $languages = array_diff_key($languages, $settings['excluded_languages']);
    }

    if (!empty($settings['groups'])) {
      $grouped_languages = [];
      $found_languages = [];
      $languages += ['other' => t('Other languages')];
      foreach (explode("\n", $settings['groups']) as $line) {
        if (strpos($line, '|') !== FALSE) {
          list($group, $langs) = explode('|', $line, 2);
          $langs = array_filter(array_map('trim', explode(',', $langs)));
          $langs = array_intersect_key($languages, array_combine($langs, $langs));
          $found_languages += $langs;
          $grouped_languages[$group] = $langs;
        }
        else {
          $langs = array_filter(array_map('trim', explode(',', $line)));
          if (!empty($langs)) {
            $langs = array_intersect_key($languages, array_combine($langs, $langs));
            $found_languages += (array) $langs;
            $grouped_languages += (array) $langs;
          }
        }
      }
      $missing_languages = array_diff_key($languages, $found_languages);
      foreach ($grouped_languages as $index => $options) {
        if (is_array($options)) {
          if (isset($options['other'])) {
            unset($options['other']);
            if ($missing_languages) {
              $grouped_languages[$index] = array_merge($grouped_languages[$index], $missing_languages);
              $missing_languages = FALSE;
            }
          }
        }
      }
      if (isset($grouped_languages['other'])) {
        unset($grouped_languages['other']);
        if ($missing_languages) {
          $grouped_languages = array_merge($grouped_languages, $missing_languages);
        }
      }
      return $grouped_languages;
    }

    return $languages;
  }

  /**
   * Helper function to get special languages.
   *
   * @param string $subset
   *   Formatting hint.
   *
   * @return array
   *   Array of key-value pairs.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  private static function getLanguageConfigurationOptions($subset) {
    $values = [];

    switch ($subset) {
      case LanguageInterface::LANGCODE_NOT_SPECIFIED:
        $values = [LanguageInterface::LANGCODE_NOT_SPECIFIED => t('Language neutral')];
        break;

      case LanguageInterface::LANGCODE_SITE_DEFAULT:
        // Copied from function language_get_default_langcode(),
        // and from function LanguageConfiguration::getDefaultOptions().
        $values = [
          LanguageInterface::LANGCODE_SITE_DEFAULT => t("Site's default language (@language)", [
            '@language' => t(\Drupal::languageManager()
              ->getDefaultLanguage()
              ->getName()
            ),
          ]),
        ];
        break;

      case 'current_interface':
        // Copied from function language_get_default_langcode(),
        // and from function LanguageConfiguration::getDefaultOptions().
        $values = ['current_interface' => t('Current interface language')];
        break;

      case 'authors_default':
        // Copied from function language_get_default_langcode(),
        // and from function LanguageConfiguration::getDefaultOptions().
        $values = ['authors_default' => t("Author's preferred language")];
        break;

      case CustomLanguageManager::LANGUAGEFIELD_LANGUAGES_CUSTOM:
        foreach (CustomLanguageManager::getCustomLanguages() as $key => $labels) {
          $values[$key] = t($labels->label());
        }
        break;
    }
    return $values;
  }

}
