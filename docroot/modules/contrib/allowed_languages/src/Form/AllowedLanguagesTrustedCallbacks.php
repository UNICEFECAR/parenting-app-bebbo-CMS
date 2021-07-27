<?php

namespace Drupal\allowed_languages\Form;

use Drupal\Core\Security\TrustedCallbackInterface;
use Drupal\Core\Language\LanguageInterface;

/**
 * Trusted callbacks for allowed languages form alters.
 */
class AllowedLanguagesTrustedCallbacks implements TrustedCallbackInterface {

  /**
   * {@inheritdoc}
   */
  public static function trustedCallbacks() {
    return ['languageSelectWidgetPreRender'];
  }

  /**
   * Removes any languages that the user is not allowed to create content for.
   */
  public static function languageSelectWidgetPreRender($build) {
    $allowed_languages = \Drupal::service('allowed_languages.allowed_languages_manager')->assignedLanguages();

    // Remove any languages that the user is not allowed to add content for.
    foreach ($build['value']['#options'] as $language_code => $language_option) {
      // If the language is allowed then continue.
      if (in_array($language_code, $allowed_languages)) {
        continue;
      }

      // Always allow the not specified and not applicable language options.
      if ($language_code === LanguageInterface::LANGCODE_NOT_SPECIFIED
        || $language_code === LanguageInterface::LANGCODE_NOT_APPLICABLE) {
        continue;
      }

      unset($build['value']['#options'][$language_code]);
    }

    return $build;
  }

}
