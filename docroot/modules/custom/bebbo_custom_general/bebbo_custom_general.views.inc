<?php

/**
 * @file
 * Views data for Bebbo Custom General.
 */

/**
 * Implements hook_views_data_alter().
 */
function bebbo_custom_general_views_data_alter(array &$data) {
  foreach (['node_field_data', 'node_field_revision'] as $table) {
    if (!isset($data[$table]['title'])) {
      continue;
    }
    $data[$table]['title_natural'] = [
      'title' => t('Title (language-neutral sort)'),
      'help' => t('Sorts titles A to Z across languages, ignoring case, accents and leading whitespace. Titles starting with a number are placed last.'),
      'real field' => 'title',
      'sort' => ['id' => 'bebbo_natural_title'],
    ];
  }
}
