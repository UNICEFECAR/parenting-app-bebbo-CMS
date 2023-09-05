<?php

/**
 * @file
 * Theme file stub for tmgmt.
 */

use Drupal\Component\Utility\Unicode;
use Drupal\Core\Template\Attribute;

/**
 * Implememnts template_preprocess_HOOK().
 */
function template_preprocess_tmgmt_local_translation_form_element(array &$variables) {
  $element = $variables['element'];
  $parts = explode('|', $element['#parents'][0]);
  $header_title = Unicode::ucfirst(str_replace('_', ' ', $parts[0]));

  $variables['ajax_container'] = [
    '#type' => 'container',
    '#attributes' => [
      'id' => 'tmgmt-status-messages-' . mb_strtolower($element['#parent_label'][0]),
    ],
  ];
  $variables['form_element'] = [
    '#type' => 'table',
    '#attributes' => [
      'id' => $element['#ajaxid'],
      'class' => [$element['#zebra']],
    ],
    '#header' => [
      [
        'data' => $header_title,
        'colspan' => 4,
      ]
    ],
    '#rows' => [
      [
        'data' => [
          \Drupal::service('renderer')->render($element['status']),
          \Drupal::service('renderer')->render($element['source']),
          \Drupal::service('renderer')->render($element['translation']),
          \Drupal::service('renderer')->render($element['actions']),
        ],
      ],
    ],
  ];
}

/**
 * Implements template_preprocess_HOOK().
 */
function template_preprocess_tmgmt_local_translation_form_element_status(array &$variables) {
  $classes = ['tmgmt-ui-icon', 'tmgmt-ui-icon-32'];
  switch ($variables['status']['#value']) {
    case TMGMT_DATA_ITEM_STATE_COMPLETED:
      $title = t('Accepted');
      $icon = 'core/misc/icons/73b355/check.svg';
      break;
    case TMGMT_DATA_ITEM_STATE_TRANSLATED:
      $title = t('Translated');
      $icon = \Drupal::service('extension.list.module')->getPath('tmgmt') . '/icons/gray-check.svg';
      break;
    case TMGMT_DATA_ITEM_STATE_UNTRANSLATED:
    default:
      $title = t('Untranslated');
      $icon = \Drupal::service('extension.list.module')->getPath('tmgmt') . '/icons/ready.svg';
      break;
  }
  $variables['wrapper_attributes'] = new Attribute([
    'class' => $classes,
  ]);
  $variables['icon_url'] =  \Drupal::service('file_url_generator')->generateString($icon);
  $variables['title'] = $title;
}
