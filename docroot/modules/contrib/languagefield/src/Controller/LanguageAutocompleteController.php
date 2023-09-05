<?php

namespace Drupal\languagefield\Controller;

use Drupal\field\Entity\FieldConfig;
use Drupal\languagefield\Entity\CustomLanguageManager;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Returns autocomplete responses for Languagefield.
 */
class LanguageAutocompleteController {

  /**
   * Returns response for the language autocomplete widget.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current request object containing the search string.
   * @param string $entity_type
   *   The type of entity that owns the field.
   * @param string $bundle
   *   The name of the bundle that owns the field.
   * @param string $field_name
   *   The name of the field with the autocomplete widget.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   A JSON response containing the autocomplete suggestions.
   *
   * @see getMatches()
   */
  public function autocomplete(Request $request, $entity_type, $bundle, $field_name) {
    $matches = [];

    $string = $request->query->get('q');
    if ($string) {
      /** @var \Drupal\Core\Field\FieldDefinitionInterface $field_definition */
      $field_definition = FieldConfig::loadByName($entity_type, $bundle, $field_name);
      $settings = $field_definition->getSettings();
      $languages = CustomLanguageManager::allowedValues($settings);

      foreach ($languages as $language) {
        if (strpos(mb_strtolower($language), mb_strtolower($string)) !== FALSE) {
          $matches[] = ['value' => $language, 'label' => $language];
        }
      }

    }
    return new JsonResponse($matches);
  }

}
