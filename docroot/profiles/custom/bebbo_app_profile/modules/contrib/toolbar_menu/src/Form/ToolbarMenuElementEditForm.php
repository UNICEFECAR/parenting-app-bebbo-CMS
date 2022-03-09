<?php

namespace Drupal\toolbar_menu\Form;

use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Form\FormStateInterface;

/**
 * Edit form for toolbar menu elements.
 */
class ToolbarMenuElementEditForm extends EntityForm {

  /**
   * The toolbar menu element entity.
   *
   * @var \Drupal\toolbar_menu\Entity\ToolbarMenuElement
   */
  protected $entity;

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Label'),
      '#maxlength' => 255,
      '#default_value' => $this->entity->label(),
      '#required' => TRUE,
    ];

    $form['id'] = [
      '#type' => 'machine_name',
      '#title' => $this->t('ID'),
      '#maxlength' => 255,
      '#default_value' => $this->entity->id(),
      '#required' => TRUE,
      '#disabled' => !$this->entity->isNew(),
      '#machine_name' => [
        'exists' => 'Drupal\toolbar_menu\Entity\ToolbarMenuElement::load',
      ],
    ];

    $existing_menu = [];
    foreach ($this->entityTypeManager
      ->getStorage('menu')
      ->loadByProperties(['status' => TRUE]) as $menu) {
      $existing_menu[$menu->id()] = $menu->label();
    }

    $form['menu'] = [
      '#type' => 'select',
      '#title' => $this->t('Menu'),
      '#options' => $existing_menu,
      '#target_type' => 'menu',
      '#validate_reference' => TRUE,
      '#default_value' => $this->entity->menu(),
      '#required' => TRUE,
    ];

    $form['rewrite_label'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Display the menu label in toolbar instead of this entity label'),
      '#validate_reference' => TRUE,
      '#default_value' => $this->entity->rewriteLabel(),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    parent::save($form, $form_state);
    $this->messenger()->addMessage($this->t('Toolbar menu element @label saved.', ['@label' => $this->entity->label()]));
    $form_state->setRedirectUrl($this->entity->toUrl('collection'));
  }

}
