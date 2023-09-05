<?php

namespace Drupal\languagefield\Form;

use Drupal\Component\Utility\Html;
use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\languagefield\Entity\CustomLanguageManager;

/**
 * Form controller for the CustomLanguage entity edit forms.
 *
 * @todo Copy more code from \Drupal\language\Form\LanguageEditForm.
 */
class CustomLanguageForm extends EntityForm {

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    /** @var \Drupal\languagefield\Entity\CustomLanguageInterface $language */
    $language = $this->entity;

    if ($language->getId()) {
      $form['langcode_view'] = [
        '#type' => 'item',
        '#title' => $this->t('Language code'),
        '#markup' => $language->id(),
      ];
      $form['langcode'] = [
        '#type' => 'value',
        '#value' => $language->id(),
      ];
    }
    else {
      $form['langcode'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Language code'),
        '#maxlength' => CustomLanguageManager::LANGUAGEFIELD_LANGCODE_MAXLENGTH,
        '#required' => TRUE,
        '#default_value' => '',
        '#disabled' => FALSE,
        '#description' => $this->t('Use language codes as <a href=":w3ctags">defined by the W3C</a> for interoperability. <em>Examples: "en", "en-gb" and "zh-hant".</em>', [':w3ctags' => 'http://www.w3.org/International/articles/language-tags/']),
      ];
    }
    $form['label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Language name'),
      '#maxlength' => 64,
      '#default_value' => $language->label(),
      '#required' => TRUE,
    ];
    $form['native_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Display in native language'),
      '#default_value' => $language->getNativeName(),
      '#maxlength' => 64,
      '#required' => TRUE,
    ];
    $form['direction'] = [
      '#type' => 'radios',
      '#title' => $this->t('Direction'),
      '#required' => TRUE,
      '#description' => $this->t('Direction that text in this language is presented.'),
      '#default_value' => $language->getDirection(),
      '#options' => [
        LanguageInterface::DIRECTION_LTR => $this->t('Left to right'),
        LanguageInterface::DIRECTION_RTL => $this->t('Right to left'),
      ],
    ];

    return parent::form($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $status = parent::save($form, $form_state);
    /** @var \Drupal\languagefield\Entity\CustomLanguageInterface $language */
    $language = $this->entity;

    // $edit_link = $this->entity->toLink($this->t('Edit'),'edit-form');
    $action = $status == SAVED_UPDATED ? 'updated' : 'added';

    // Tell the user we've updated their custom language.
    $this->messenger()->addStatus($this->t(
      'The language %label has been %action.', [
        '%label' => $language->label(),
        '%action' => $action,
      ])
    );
    $this->logger('languagefield')->notice(
      'The language %label has been %action.', [
        '%label' => $language->label(),
        '%action' => $action,
      ]
    );

    // Redirect back to the list view.
    $form_state->setRedirect('languagefield.custom_language.collection');
  }

  /**
   * {@inheritdoc}
   */
  protected function actions(array $form, FormStateInterface $form_state) {
    $actions = parent::actions($form, $form_state);
    $actions['submit']['#value'] = ($this->entity->isNew()) ? $this->t('Add custom language') : $this->t('Save language');
    return $actions;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    // Ensure sane field values for langcode and names.
    if (!isset($form['langcode_view']) && !preg_match('@^[a-zA-Z]{1,8}(-[a-zA-Z0-9]{1,8})*$@', $form_state->getValue('langcode'))) {
      $form_state->setErrorByName('langcode', $this->t('%field must be a valid language tag as <a href=":url">defined by the W3C</a>.', [
        '%field' => $form['langcode']['#title'],
        ':url' => 'http://www.w3.org/International/articles/language-tags/',
      ]));
    }
    foreach (['label', 'native_name'] as $field) {
      if ($form_state->getValue($field) != Html::escape($form_state->getValue($field))) {
        $form_state->setErrorByName($field, $this->t('%field cannot contain any markup.', ['%field' => $form[$field]['#title']]));
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function copyFormValuesToEntity(EntityInterface $entity, array $form, FormStateInterface $form_state) {
    $langcode = trim($form_state->getValue('langcode'));
    $label = trim($form_state->getValue('label'));
    $native = trim($form_state->getValue('native_name'));
    $direction = $form_state->getValue('direction');

    $entity->set('id', $langcode);
    $entity->set('label', $label);
    $entity->set('native_name', $native);
    $entity->set('direction', $direction);
  }

}
