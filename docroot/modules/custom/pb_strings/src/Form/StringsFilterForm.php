<?php

namespace Drupal\pb_strings\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Filter form for the strings translation list.
 */
class StringsFilterForm extends FormBase {

  /**
   * The language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected LanguageManagerInterface $languageManager;

  /**
   * Constructs a StringsFilterForm.
   *
   * @param \Drupal\Core\Language\LanguageManagerInterface $language_manager
   *   The language manager.
   */
  public function __construct(LanguageManagerInterface $language_manager) {
    $this->languageManager = $language_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('language_manager'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'pb_strings_filter_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $filter = $this->getFilterValues();

    $languages = $this->languageManager->getLanguages();
    $language_options = [];
    foreach ($languages as $langcode => $language) {
      $language_options[$langcode] = $language->getName();
    }

    $form['filters'] = [
      '#type' => 'details',
      '#title' => $this->t('Filter strings'),
      '#open' => TRUE,
    ];

    $form['filters']['langcode'] = [
      '#type' => 'select',
      '#title' => $this->t('Translation language'),
      '#options' => $language_options,
      '#default_value' => $filter['langcode'],
    ];

    $form['filters']['string'] = [
      '#type' => 'textfield',
      '#title' => $this->t('String contains'),
      '#default_value' => $filter['string'],
      '#description' => $this->t('Leave blank to show all strings. The search is case insensitive.'),
    ];

    $form['filters']['actions'] = [
      '#type' => 'actions',
    ];

    $form['filters']['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Filter'),
    ];

    if (!empty($filter['string']) || $filter['langcode'] !== $this->languageManager->getCurrentLanguage()->getId()) {
      $form['filters']['actions']['reset'] = [
        '#type' => 'submit',
        '#value' => $this->t('Reset'),
        '#submit' => ['::resetForm'],
      ];
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $session = $this->getRequest()->getSession();
    $session->set('pb_strings_filter', [
      'langcode' => $form_state->getValue('langcode'),
      'string' => trim($form_state->getValue('string')),
    ]);
    $form_state->setRedirect('pb_strings.strings_list', [
      'taxonomy_vocabulary' => 'strings',
    ]);
  }

  /**
   * Reset form submit handler.
   */
  public function resetForm(array &$form, FormStateInterface $form_state): void {
    $session = $this->getRequest()->getSession();
    $session->remove('pb_strings_filter');
    $form_state->setRedirect('pb_strings.strings_list', [
      'taxonomy_vocabulary' => 'strings',
    ]);
  }

  /**
   * Gets the current filter values from the session.
   *
   * @return array
   *   Array with 'langcode' and 'string' keys.
   */
  public static function getFilterValues(): array {
    $session = \Drupal::request()->getSession();
    $filter = $session->get('pb_strings_filter', []);

    return [
      'langcode' => $filter['langcode'] ?? \Drupal::languageManager()->getCurrentLanguage()->getId(),
      'string' => $filter['string'] ?? '',
    ];
  }

}
