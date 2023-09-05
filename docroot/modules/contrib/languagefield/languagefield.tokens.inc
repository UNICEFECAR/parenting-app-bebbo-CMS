<?php

/**
 * @file
 * Provides Token integration for languagefield module.
 *
 * Pretty much copy-pasted from folowoing sources:
 * @see address module
 * @see token.tokens.inc
 */

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Render\BubbleableMetadata;

/**
 * Implements hook_token_info().
 */
function languagefield_token_info() {
  $types = [];
  $tokens = [];

  $module = 'languagefield';
  $types[$module] = [
    'name' => t('Language field'),
    'description' => t('Tokens related to language field.'),
    'module' => $module,
    'type' => $module,
    'needs-data' => $module,
  ];

  if (!\Drupal::hasService('token.entity_mapper')) {
    return;
  }

  foreach (\Drupal::entityTypeManager()->getDefinitions() as $entity_type_id => $entity_type) {
    if (!$entity_type->entityClassImplements(ContentEntityInterface::class)) {
      continue;
    }
    $token_type = \Drupal::service('token.entity_mapper')->getTokenTypeForEntityType($entity_type_id);
    if (empty($token_type)) {
      continue;
    }

    // Add [entity:language_field] field tokens if they do not already exist.
    $fields = \Drupal::service('entity_field.manager')->getFieldStorageDefinitions($entity_type_id);
    foreach ($fields as $field_name => $field) {
      /** @var \Drupal\Core\Field\FieldStorageDefinitionInterface $field */
      if ($field->getType() != 'language_field') {
        continue;
      }

      // Language tokens.
      $tokens[$token_type . '-' . $field_name]['language'] = [
        'name' => t('Language'),
        'description' => t('Tokens related to language field.'),
        'module' => 'languagefield',
        'type' => 'languagefield',
        // 'needs-data' => 'language',
      ];

      $tokens[$token_type . '-' . $field_name]['name'] = [
        'name' => t('Language name'),
        'description' => t('The language name.'),
        // 'module' => 'languagefield',
      ];
      $tokens[$token_type . '-' . $field_name]['langcode'] = [
        'name' => t('Language code'),
        'description' => t('The ISO 639 language code.'),
        // 'module' => 'languagefield',
      ];
    }
  }

  return [
    'types' => $types,
    'tokens' => $tokens,
  ];
}

/**
 * Implements hook_tokens().
 */
function languagefield_tokens($type, $tokens, array $data, array $options, BubbleableMetadata $bubbleable_metadata) {
  $replacements = [];

  if (empty($data['field_property'])) {
    return $replacements;
  }
  if (!isset($data['field_name']) || !isset($data[$data['field_name']])) {
    return $replacements;
  }

  $token_service = \Drupal::token();
  $url_options = ['absolute' => TRUE];
  $language_manager = \Drupal::languageManager();
  if (isset($options['langcode'])) {
    $url_options['language'] = $language_manager->getLanguage($options['langcode']);
    $langcode = $options['langcode'];
  }
  else {
    $langcode = $language_manager->getCurrentLanguage()->getId();
  }

  foreach ($tokens as $token => $original) {
    $delta = 0;
    $parts = explode(':', $token);
    if (is_numeric($parts[0])) {
      if (count($parts) > 1) {
        $delta = $parts[0];
        $property_name = $parts[1];
      }
      else {
        continue;
      }
    }
    else {
      $property_name = $parts[0];
    }
    $field_item = $data[$data['field_name']][$delta];
    $langcode = $field_item->value;
    $language = \Drupal\languagefield\Entity\CustomLanguageManager::createFromLangcode($langcode);

    if ($language) {
      foreach ($tokens as $name => $original) {
        switch ($name) {
          case 'name':
            $replacements[$original] = $language->getName();
            break;

          case 'langcode':
            // Not used, use field:value.
            $replacements[$original] = $langcode;
            break;

        }
      }
    }
  }
  return $replacements;
}
