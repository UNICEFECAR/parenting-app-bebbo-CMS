<?php

namespace Drupal\languagefield\Plugin\Tamper;

use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\languagefield\Entity\CustomLanguageManager;
use Drupal\tamper\Exception\TamperException;
use Drupal\tamper\TamperableItemInterface;
use Drupal\tamper\TamperBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation for converting language name to language code.
 *
 * @Tamper(
 *   id = "languagefield_language_to_code",
 *   label = @Translation("Language name to language code"),
 *   description = @Translation("Converts this field from a language name string to the language code."),
 *   category = "Text"
 * )
 */
class LanguageToCode extends TamperBase implements ContainerFactoryPluginInterface {

  /**
   * Holds the LanguageManager object so we can grab the language list.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = new static($configuration, $plugin_id, $plugin_definition, $configuration['source_definition']);
    $instance->setLanguageManager($container->get('language_manager'));
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function tamper($data, TamperableItemInterface $item = NULL) {
    if (!is_string($data)) {
      throw new TamperException('Input should be a string.');
    }

    /**
     * Holds the list of languages in an array.
     * @static
     */
    static $languages = [];

    if (empty($languages)) {
      $languages = $this->languageManager->getStandardLanguageList()
        + CustomLanguageManager::getCustomLanguageList();

      foreach ($languages as $language_code => $language_name) {
        $languages[$language_code] = mb_strtolower((string) $language_name[0]);
      }
      $languages = array_flip($languages);
    }

    // If it's already a valid language code, leave it alone.
    if (in_array($data, $languages)) {
      return $data;
    }

    // Trim whitespace, set to lowercase.
    $language = mb_strtolower(trim($data));
    if (isset($languages[$language])) {
      return $languages[$language];
    }
    else {
      throw new TamperException('Could not find language name ' . $language . ' in list of languages.');
    }
  }

  /**
   * Setter function for the LanguageManagerInterface.
   *
   * @param \Drupal\Core\Language\LanguageManagerInterface $language_manager
   *   The language manager used to grab language list.
   */
  public function setLanguageManager(LanguageManagerInterface $language_manager) {
    $this->languageManager = $language_manager;
  }

}
