<?php

use Drupal\Core\Entity\ContentEntityType;

/**
 * Implements hook_views_data().
 */
function allowed_languages_views_data() {
  $data = [];

  // Base definition for all implementations of the allowed languages filter.
  $allowed_languages_filter = [
    'title' => t('Allowed languages (Node field data)'),
    'filter' => [
      'id' => 'allowed_languages',
      'field' => 'langcode',
      'title' => t('Allowed languages'),
      'help' => t('Filters content based on the current users allowed languages.'),
    ],
  ];

  // Add the allowed languages filter for the data table and revision data
  // table of any entity that is content and can be translated.
  foreach (\Drupal::entityTypeManager()->getDefinitions() as $definition) {
    if (!$definition instanceof ContentEntityType) {
      continue;
    }

    if (!$definition->isTranslatable()) {
      continue;
    }

    $data_table = $definition->getDataTable();
    $revision_table = $definition->getRevisionDataTable();

    if ($data_table) {
      $data[$data_table]['allowed_languages'] = $allowed_languages_filter;
    }

    if ($revision_table) {
      $data[$revision_table]['allowed_languages'] = $allowed_languages_filter;
    }
  }

  return $data;
}
