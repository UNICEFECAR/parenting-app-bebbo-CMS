<?php

namespace Drupal\symfony_mailer\Form;

use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\symfony_mailer\Processor\OverrideManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Provides a form to confirm an override action.
 */
class OverrideActionForm extends ConfirmFormBase {

  /**
   * The override manager.
   *
   * @var \Drupal\symfony_mailer\OverrideManagerInterface
   */
  protected $overrideManager;

  /**
   * The override ID.
   *
   * @var string
   */
  protected $id;

  /**
   * The action to execute.
   *
   * @var string
   */
  protected $action;

  /**
   * Human-readable label for the action.
   *
   * @var string
   */
  protected $actionName;

  /**
   * Human-readable description for the action.
   *
   * @var string
   */
  protected $description;

  /**
   * Human-readable string arguments to use for translation.
   *
   * @var string[]
   */
  protected $args;

  /**
   * Constructs a new OverrideActionForm object.
   *
   * @param \Drupal\symfony_mailer\Processor\OverrideManagerInterface $override_manager
   *   The override manager.
   */
  public function __construct(OverrideManagerInterface $override_manager) {
    $this->overrideManager = $override_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('symfony_mailer.override_manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return ($this->id == OverrideManagerInterface::ALL_OVERRIDES) ?
      $this->t('Are you sure you want to do %action for all overrides?', $this->args) :
      $this->t('Are you sure you want to do %action for override %name?', $this->args);
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl() {
    return new Url('symfony_mailer.override.status');
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    return $this->description;
  }

  /**
   * {@inheritdoc}
   */
  public function getConfirmText() {
    return $this->actionName;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'symfony_mailer_override_action_form';
  }

  /**
   * {@inheritdoc}
   *
   * @param array $form
   *   A nested array form elements comprising the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   * @param string $id
   *   The override ID.
   * @param string $action
   *   The action to execute.
   */
  public function buildForm(array $form, FormStateInterface $form_state, string $id = '', string $action = '') {
    $this->id = $id;
    $this->action = $action;
    $info = $this->overrideManager->getInfo($id);
    $this->actionName = $info['action_names'][$action] ?? NULL;
    if (!$this->actionName) {
      throw new NotFoundHttpException();
    }
    $this->args = ['%name' => $info['name'], '%action' => $this->actionName];

    // Use the last warning as the description.
    $warnings = $this->overrideManager->action($id, $action, TRUE);
    $disabled = empty($warnings);
    $this->description = $warnings ? array_pop($warnings) : $this->t('No available actions');
    $form['warnings'] = [
      '#theme' => 'item_list',
      '#title' => $this->t('Warnings'),
      '#items' => $warnings,
      '#access' => !empty($warnings),
    ];

    $form = parent::buildForm($form, $form_state);
    $form['actions']['submit']['#attributes']['disabled'] = $disabled;
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->overrideManager->action($this->id, $this->action);
    $message = ($this->id == OverrideManagerInterface::ALL_OVERRIDES) ?
      $this->t('Completed %action for all overrides', $this->args) :
      $this->t('Completed %action for override %name', $this->args);
    $this->messenger()->addStatus($message);
    $this->logger('symfony_mailer')->notice($message);
    $form_state->setRedirectUrl($this->getCancelUrl());
  }

}
