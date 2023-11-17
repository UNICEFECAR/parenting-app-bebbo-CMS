<?php

namespace Drupal\tmgmt_deepl;

use Drupal\Core\Entity\EntityFormInterface;
use Drupal\tmgmt\TranslatorPluginUiBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * DeepL translator UI.
 */
class DeeplTranslatorUi extends TranslatorPluginUiBase {

  use StringTranslationTrait;

  /**
   * Overrides TMGMTDefaultTranslatorUIController::pluginSettingsForm().
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form = parent::buildConfigurationForm($form, $form_state);

    // Check for valid form object - we should receive entity form object here.
    if (!$form_state->getFormObject() instanceof EntityFormInterface) {
      return $form;
    }

    /** @var \Drupal\tmgmt\TranslatorInterface $translator */
    $translator = $form_state->getFormObject()->getEntity();

    /** @var \Drupal\tmgmt_deepl\Plugin\tmgmt\Translator\DeeplTranslator $deepl_translator */
    $deepl_translator = $translator->getPlugin();

    $form['auth_key'] = [
      '#type' => 'textfield',
      '#title' => $this->t('DeepL API authentication key'),
      '#required' => TRUE,
      '#default_value' => $translator->getSetting('auth_key'),
      '#description' => $this->t('Please enter your DeepL API authentication key or visit <a href="@url" target="_blank">DeepL API registration</a> to create new one.',
        ['@url' => 'https://www.deepl.com/de/pro#developer']),
    ];

    // Add 'connect' button for testing valid key.
    $form += parent::addConnectButton();

    // Get custom translator URL for testing purposes, if available.
    $custom_translator_url = $translator->getSetting('test_url');
    $translator_url = !empty($custom_translator_url) ? $custom_translator_url : $deepl_translator->getTranslatorUrl();
    $form['url'] = [
      '#type' => 'value',
      '#value' => $translator_url,
    ];

    // Get custom translator URL for testing purposes, if available.
    $custom_usage_url = $translator->getSetting('test_url_usage');
    $usage_url = !empty($custom_usage_url) ? $custom_usage_url : $deepl_translator->getUsageUrl();
    $form['url_usage'] = [
      '#type' => 'value',
      '#value' => $usage_url,
    ];

    // Additional query options.
    $split_sentences = !empty($translator->getSetting('split_sentences')) || $translator->getSetting('split_sentences') == '0'  ? strval($translator->getSetting('split_sentences')) : '1';
    $form['split_sentences'] = [
      '#type' => 'select',
      '#title' => $this->t('Split sentences'),
      '#options' => [
        '0' => $this->t('No splitting at all, whole input is treated as one sentence'),
        '1' => $this->t('Splits on interpunction and on newlines (default)'),
        'nonewlines' => $this->t('Splits on interpunction only, ignoring newlines'),
      ],
      '#description' => $this->t('Sets whether the translation engine should first split the input into sentences.'),
      '#default_value' => $split_sentences,
      '#prefix' => '<p>' . $this->t('Please have a look at the <a href="@url" target="_blank">DeepL API documentation</a> for more information on the settings listed below.',
        ['@url' => 'https://www.deepl.com/en/docs-api/']) . '</p>',
      '#required' => TRUE,
    ];

    $form['formality'] = [
      '#type' => 'select',
      '#title' => $this->t('Formality'),
      '#options' => [
        'default' => $this->t('default'),
        'more' => $this->t('more - for a more formal language'),
        'less' => $this->t('less - for a more informal language'),
        'prefer_more' => $this->t('prefer_more - for a more formal language if available, otherwise fallback to default formality'),
        'prefer_less' => $this->t('prefer_less - for a more informal language if available, otherwise fallback to default formality'),
      ],
      '#description' => $this->t('Sets whether the translated text should lean towards formal or informal language. This feature currently only works for target languages <strong>"DE" (German), "FR" (French), "IT" (Italian), "ES" (Spanish), "NL" (Dutch), "PL" (Polish), "PT-PT", "PT-BR" (Portuguese) and "RU" (Russian).</strong> To prevent possible errors while translating, it is recommended to use the <em>prefer_...</em> options.'),
      '#default_value' => !empty($translator->getSetting('formality')) ? $translator->getSetting('formality') : 'default',
    ];

    $form['preserve_formatting'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Preserve formatting'),
      '#description' => $this->t('Sets whether the translation engine should preserve some aspects of the formatting, even if it would usually correct some aspects.'),
      '#default_value' => !empty($translator->getSetting('preserve_formatting')) ? $translator->getSetting('preserve_formatting') : 0,
    ];

    $form['tag_handling'] = [
      '#type' => 'select',
      '#title' => $this->t('Tag handling'),
      '#options' => [
        '0' => $this->t('off'),
        'xml' => 'xml',
        'html' => 'html',
      ],
      '#description' => $this->t('Sets which kind of tags should be handled. By default, the translation engine does not take tags into account. For the translation of generic XML content use <a href="@url_xml" target="_blank">xml</a>, for HTML content use <a href="@url_html" target="_blank">html</a>.',
        [
          '@url_xml' => 'https://www.deepl.com/docs-api/xml/',
          '@url_html' => 'https://www.deepl.com/docs-api/html/',
        ]
      ),
      '#default_value' => !empty($translator->getSetting('tag_handling')) ? $translator->getSetting('tag_handling') : 0,
      '#required' => TRUE,
    ];

    $form['non_splitting_tags'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Non-splitting tags'),
      '#description' => $this->t('Comma-separated list of XML tags which never split sentences.'),
      '#default_value' => !empty($translator->getSetting('non_splitting_tags')) ? $translator->getSetting('non_splitting_tags') : '',
      '#states' => [
        'visible' => [
          [':input[name="settings[tag_handling]"]' => ['value' => 'xml']],
          'or',
          [':input[name="settings[tag_handling]"]' => ['value' => 'html']],
        ],
      ],
    ];

    $form['splitting_tags'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Splitting tags'),
      '#description' => $this->t('Comma-separated list of XML tags which always cause splits.'),
      '#default_value' => !empty($translator->getSetting('splitting_tags')) ? $translator->getSetting('splitting_tags') : '',
      '#states' => [
        'visible' => [
          [':input[name="settings[tag_handling]"]' => ['value' => 'xml']],
          'or',
          [':input[name="settings[tag_handling]"]' => ['value' => 'html']],
        ],
      ],
    ];

    $form['ignore_tags'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Ignore tags'),
      '#description' => $this->t('Comma-separated list of XML tags whose content is never translated.'),
      '#default_value' => !empty($translator->getSetting('ignore_tags')) ? $translator->getSetting('ignore_tags') : '',
      '#states' => [
        'visible' => [
          [':input[name="settings[tag_handling]"]' => ['value' => 'xml']],
          'or',
          [':input[name="settings[tag_handling]"]' => ['value' => 'html']],
        ],
      ],
    ];

    $form['outline_detection'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Automatic outline detection'),
      '#description' => $this->t('The automatic detection of the XML structure won\'t yield best results in all XML files. You can disable this automatic mechanism altogether by setting the outline_detection parameter to 0 and selecting the tags that should be considered structure tags. This will split sentences using the splitting_tags parameter.'),
      '#default_value' => !empty($translator->getSetting('outline_detection')) ? $translator->getSetting('outline_detection') : 0,
      '#states' => [
        'visible' => [
          [':input[name="settings[tag_handling]"]' => ['value' => 'xml']],
          'or',
          [':input[name="settings[tag_handling]"]' => ['value' => 'html']],
        ],
      ],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {
    parent::validateConfigurationForm($form, $form_state);

    // Check for valid form object - we should receive entity form object here.
    if (!$form_state->getFormObject() instanceof EntityFormInterface) {
      return $form;
    }

    /** @var \Drupal\tmgmt\TranslatorInterface $translator */
    $translator = $form_state->getFormObject()->getEntity();
    // Get actual usage data from API - if numeric sth. went wrong.
    /** @var \Drupal\tmgmt_deepl\Plugin\tmgmt\Translator\DeeplTranslator $deepl_translator */
    $deepl_translator = $translator->getPlugin();
    $usage_data = $deepl_translator->getUsageData($translator);

    if (is_numeric($usage_data)) {
      $form_state->setErrorByName('settings][auth_key', $this->t('The "DeepL API authentication key" is not correct.'));
    }

    // Reset outline_detection, if tag_handling is not set.
    $settings = $form_state->getValue('settings');
    if ($settings['tag_handling'] === '0') {
      $form_state->setValueForElement($form['plugin_wrapper']['settings']['outline_detection'], 0);
    }
  }

}
