<?php

namespace Drupal\languagefield;

use Drupal\Core\Config\Entity\DraggableListBuilder;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormStateInterface;

/**
 * Defines a class to build a listing of custom languages.
 *
 * @see \Drupal\user\Entity\CustomLanguage
 */
class CustomLanguageListBuilder extends DraggableListBuilder {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'languagefield_custom_language_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header['label'] = $this->t('Language name');
    $header['langcode'] = $this->t('Language code');
    $header['native_name'] = $this->t('Display in native language');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    /** @var \Drupal\languagefield\Entity\CustomLanguageInterface $entity */
    $row['label'] = $entity->label();
    $row['langcode'] = ['#markup' => $entity->id()];
    $row['native_name'] = ['#markup' => $entity->getNativeName()];
    return $row + parent::buildRow($entity);
  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultOperations(EntityInterface $entity) {
    $operations = parent::getDefaultOperations($entity);
    return $operations;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);
    $this->messenger()->addStatus($this->t('The language has been updated.'));
  }

  /**
   * {@inheritdoc}
   */
  public function render() {
    $build = parent::render();
    $build['#empty'] = $this->t('There is no custom language.');
    return $build;
  }

}
