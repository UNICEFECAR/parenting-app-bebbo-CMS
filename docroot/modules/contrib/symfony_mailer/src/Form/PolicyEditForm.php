<?php

namespace Drupal\symfony_mailer\Form;

use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Form\FormStateInterface;

/**
 * Mailer policy edit form.
 */
class PolicyEditForm extends EntityForm {

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    // @todo Display the type, sub-type and entity.
    // @todo Use vertical tabs?
    // @todo Display the inherited adjusters and provide a way to block them.
    // @todo If an adjuster is inherited and not configurable, don't offer to add it.
    // @todo Show an adjuster description.
    // Get the adjusters and synchronise with any existing form state.
    $adjusters = $this->entity->adjusters();
    $config = $form_state->getValue('config');
    if (is_array($config)) {
      $adjusters->setConfiguration($config);
    }

    // Set a div to allow updating the entire form when the type is changed.
    $form['#prefix'] = '<div id="mailer-policy-edit-form">';
    $form['#suffix'] = '</div>';

    $form['label'] = [
      '#markup' => $this->entity->label(),
      '#prefix' => '<h2>',
      '#suffix' => '</h2>',
      '#weight' => -2,
    ];

    // Add adjuster button.
    $ajax = [
      'callback' => '::ajaxUpdate',
      'wrapper' => 'mailer-policy-edit-form',
    ];

    $form['add_actions'] = [
      '#type' => 'actions',
      '#weight' => -1,
      '#attributes' => ['class' => ['container-inline']],
    ];

    // Put the common adjusters first.
    $common_adjusters = array_flip($this->entity->getCommonAdjusters());
    $options = $options2 = [];
    foreach ($this->entity->adjusterDefinitions() as $name => $definition) {
      if (!$adjusters->has($name) && !$definition['automatic']) {
        if (isset($common_adjusters[$name])) {
          $options[$name] = $definition['label'];
        }
        else {
          $options2[$name] = $definition['label'];
        }
      }
    }
    asort($options);
    asort($options2);
    $options += $options2;

    $form['add_actions']['add_select'] = [
      '#type' => 'select',
      '#options' => $options,
      '#empty_value' => '',
      '#empty_option' => $this->t('- Select element to add -'),
    ];

    $form['add_actions']['add'] = [
      '#type' => 'submit',
      '#value' => $this->t('Add element'),
      '#submit' => ['::submitAdd'],
      '#ajax' => $ajax,
    ];

    // Main adjuster config.
    $form['config'] = [
      '#type' => 'container',
    ];

    foreach ($adjusters->sort() as $name => $adjuster) {
      $form['config'][$name] = [
        '#type' => 'details',
        '#title' => $adjuster->getLabel(),
        '#tree' => TRUE,
        '#open' => TRUE,
        '#parents' => ['config', $name],
      ];

      $form['config'][$name] += $adjuster->settingsForm([], $form_state);

      $form['config'][$name]['remove_button'] = [
        '#type' => 'submit',
        '#name' => "remove_$name",
        '#value' => $this->t('Remove'),
        '#submit' => ['::submitRemove'],
        '#ajax' => $ajax,
      ];
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);

    // Update the mailer policy configuration and save.
    $this->entity->setConfiguration($form_state->getValue('config') ?? [])
      ->save();
  }

  /**
   * Ajax callback to update the form.
   */
  public static function ajaxUpdate($form, FormStateInterface $form_state) {
    return $form;
  }

  /**
   * Submit callback for add button.
   */
  public static function submitAdd(array &$form, FormStateInterface $form_state) {
    $name = $form_state->getValue('add_select');
    $form_state->setValue(['config', $name], [])
      ->setRebuild();
  }

  /**
   * Submit callback for remove button.
   */
  public static function submitRemove(array &$form, FormStateInterface $form_state) {
    $button = $form_state->getTriggeringElement();
    $name = $button['#parents'][1];
    $form_state->unsetValue(['config', $name])
      ->setRebuild();
  }

}
