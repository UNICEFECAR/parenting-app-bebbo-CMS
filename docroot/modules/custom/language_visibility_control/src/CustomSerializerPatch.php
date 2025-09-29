<?php

namespace Drupal\language_visibility_control;

use Drupal\group\Entity\Group;

/**
 * Patch for CustomSerializer to add language visibility control.
 */
class CustomSerializerPatch {

  /**
   * Filters languages based on visibility settings.
   *
   * @param array $languages
   *   The original languages array.
   * @param int $group_id
   *   The group ID.
   *
   * @return array
   *   Filtered languages array.
   */
  public static function filterLanguagesByVisibility(array $languages, $group_id) {
    $group = Group::load($group_id);
    if (!$group) {
      return $languages;
    }

    $visibility_service = \Drupal::service('language_visibility_control.service');
    return $visibility_service->filterLanguageDataForApi($languages, $group);
  }

  /**
   * Gets the patch code to insert into CustomSerializer.
   *
   * @return string
   *   The PHP code to insert.
   */
  public static function getPatchCode() {
    return '
      // Language Visibility Control - Filter languages based on visibility settings
      if (strpos($request_uri, "api/country-groups") !== FALSE && isset($rendered_data["CountryID"])) {
        $visibility_service = \Drupal::service("language_visibility_control.service");
        if (isset($rendered_data["languages"]) && is_array($rendered_data["languages"])) {
          $group = \Drupal\group\Entity\Group::load($rendered_data["CountryID"]);
          if ($group) {
            $rendered_data["languages"] = $visibility_service->filterLanguageDataForApi($rendered_data["languages"], $group);
          }
        }
      }
    ';
  }

}
