<?php

namespace Drupal\language_visibility_control;

use Drupal\group\Entity\Group;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Language\LanguageManagerInterface;

/**
 * Service for managing language visibility in mobile app API.
 */
class LanguageVisibilityService {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * Constructs a LanguageVisibilityService object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Language\LanguageManagerInterface $language_manager
   *   The language manager.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, LanguageManagerInterface $language_manager) {
    $this->entityTypeManager = $entity_type_manager;
    $this->languageManager = $language_manager;
  }

  /**
   * Gets all languages configured for a group.
   *
   * @param \Drupal\group\Entity\Group $group
   *   The group entity.
   *
   * @return array
   *   Array of all language codes for the group.
   */
  public function getAllGroupLanguages(Group $group) {
    $languages = [];

    if ($group->hasField('field_language') && !$group->get('field_language')->isEmpty()) {
      $values = $group->get('field_language')->getValue();
      foreach ($values as $value) {
        $languages[] = $value['value'];
      }
    }

    return $languages;
  }

  /**
   * Gets visible languages for a group.
   *
   * @param \Drupal\group\Entity\Group $group
   *   The group entity.
   *
   * @return array
   *   Array of visible language codes for the group.
   */
  public function getVisibleLanguages(Group $group) {
    $visible_languages = [];

    if ($group->hasField('field_language_visibility_in_app') && !$group->get('field_language_visibility_in_app')->isEmpty()) {
      $values = $group->get('field_language_visibility_in_app')->getValue();
      foreach ($values as $value) {
        if (!empty($value['value'])) {
          $visible_languages[] = $value['value'];
        }
      }
    }

    return $visible_languages;
  }

  /**
   * Filters language data for API based on visibility settings.
   *
   * @param array $languages
   *   The original languages array.
   * @param \Drupal\group\Entity\Group $group
   *   The group entity.
   *
   * @return array
   *   Filtered languages array containing only visible languages.
   */
  public function filterLanguageDataForApi(array $languages, Group $group) {
    $visible_languages = $this->getVisibleLanguages($group);
    $all_group_languages = $this->getAllGroupLanguages($group);

    // If no visibility settings are configured, filter to only include
    // languages that are actually configured for this group.
    if (empty($visible_languages)) {
      if (empty($all_group_languages)) {
        // If group has no languages configured, return all languages (fallback)
        return $languages;
      }

      // Filter to only include group's configured languages.
      $filtered_languages = [];
      foreach ($languages as $language) {
        $langcode = $this->extractLanguageCode($language);
        if (in_array($langcode, $all_group_languages)) {
          $filtered_languages[] = $language;
        }
      }
      return $filtered_languages;
    }

    // Filter languages to only include visible ones.
    $filtered_languages = [];
    foreach ($languages as $language) {
      $langcode = $this->extractLanguageCode($language);
      if (in_array($langcode, $visible_languages)) {
        $filtered_languages[] = $language;
      }
    }

    return $filtered_languages;
  }

  /**
   * Extracts language code from different language data structures.
   *
   * @param mixed $language
   *   The language data (array or string).
   *
   * @return string
   *   The extracted language code.
   */
  private function extractLanguageCode($language) {
    if (is_array($language) && isset($language['langcode'])) {
      return $language['langcode'];
    }
    elseif (is_array($language) && isset($language['code'])) {
      return $language['code'];
    }
    elseif (is_string($language)) {
      return $language;
    }

    return '';
  }

  /**
   * Gets language visibility statistics.
   *
   * @return array
   *   Array containing visibility statistics.
   */
  public function getLanguageVisibilityStats() {
    $stats = [
      'total_groups' => 0,
      'groups_with_visibility_settings' => 0,
      'total_languages' => 0,
      'visible_languages' => 0,
      'hidden_languages' => 0,
    ];

    $groups = $this->entityTypeManager->getStorage('group')->loadByProperties(['type' => 'country']);
    $stats['total_groups'] = count($groups);

    foreach ($groups as $group) {
      $all_languages = $this->getAllGroupLanguages($group);
      $visible_languages = $this->getVisibleLanguages($group);

      $stats['total_languages'] += count($all_languages);
      $stats['visible_languages'] += count($visible_languages);
      $stats['hidden_languages'] += count($all_languages) - count($visible_languages);

      if (!empty($visible_languages)) {
        $stats['groups_with_visibility_settings']++;
      }
    }

    return $stats;
  }

  /**
   * Debug method to check language visibility configuration for a group.
   *
   * @param \Drupal\group\Entity\Group $group
   *   The group entity.
   *
   * @return array
   *   Debug information about the group's language configuration.
   */
  public function debugGroupLanguageConfig(Group $group) {
    $debug_info = [
      'group_id' => $group->id(),
      'group_label' => $group->label(),
      'has_language_field' => $group->hasField('field_language'),
      'has_visibility_field' => $group->hasField('field_language_visibility_in_app'),
      'all_languages' => $this->getAllGroupLanguages($group),
      'visible_languages' => $this->getVisibleLanguages($group),
    ];

    // Add raw field values for debugging.
    if ($group->hasField('field_language')) {
      $debug_info['raw_language_field'] = $group->get('field_language')->getValue();
    }

    if ($group->hasField('field_language_visibility_in_app')) {
      $debug_info['raw_visibility_field'] = $group->get('field_language_visibility_in_app')->getValue();
    }

    return $debug_info;
  }

}
