<?php

namespace Drupal\languagefield\Controller;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Returns autocomplete responses for countries.
 */
class LanguageAutocompleteController {

  /**
   * Returns response for the language autocomplete widget.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current request object containing the search string.
   * @param string $field_name
   *   The name of the field with the autocomplete widget.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   A JSON response containing the autocomplete suggestions for languages.
   *
   * @see getMatches()
   */
  public function autocomplete(Request $request, $field_name) {
    $matches = $this->getMatches($request->query->get('q'), $field_name);
    return new JsonResponse($matches);
  }

  /**
   * Get matches for the auto completion of languages.
   *
   * @param string $string
   *   The name of the autocomplete field.
   * @param string $field_name
   *   Field name.
   *
   * @return array
   *   An array containing the matching languages.
   */
  public function getMatches($string, $field_name) {
    $matches = [];
    if ($string) {
      $languages = \Drupal::cache('data')->get('languagefield:languages:' . $field_name)->data;
      foreach ($languages as $langcode => $language) {
        if (strpos(mb_strtolower($language), mb_strtolower($string)) !== FALSE) {
          $matches[] = ['value' => $language, 'label' => $language];
        }
      }
    }
    return $matches;
  }

}
