<?php

namespace Drupal\symfony_mailer\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\symfony_mailer\TransportManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a form with a mailer transport add button.
 */
class TransportAddButtonForm extends FormBase {

  /**
   * The mailer transport plugin manager.
   *
   * @var \Drupal\symfony_mailer\TransportManager
   */
  protected $manager;

  /**
   * Constructs a new TransportAddButtonForm.
   *
   * @param \Drupal\symfony_mailer\TransportManager $manager
   *   The mailer transport plugin manager.
   */
  public function __construct(TransportManager $manager) {
    $this->manager = $manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('plugin.manager.mailer_transport')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'mailer_transport_add_button';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    foreach ($this->manager->getDefinitions() as $id => $definition) {
      $options[$id] = $definition['label'];
    }

    $form['plugin'] = [
      '#type' => 'select',
      '#title' => $this->t('Transport type'),
      '#empty_option' => $this->t('- Choose transport type -'),
      '#options' => $options,
      '#required' => TRUE,
    ];

    $form['actions']['#type'] = 'actions';
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Add transport'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $form_state->setRedirect(
      'entity.mailer_transport.add_form',
      ['plugin_id' => $form_state->getValue('plugin')]
    );
  }

}
