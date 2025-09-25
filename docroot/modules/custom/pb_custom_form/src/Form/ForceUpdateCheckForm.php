<?php

namespace Drupal\pb_custom_form\Form;

use Drupal\Core\Database\Connection;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Action description.
 *
 * @Action(
 *   id = "force_update_check",
 *   label = @Translation("Force Update Check"),
 *   type = "node",
 *   confirm = FALSE
 * )
 */
class ForceUpdateCheckForm extends FormBase {

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The current user service.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * The request stack service.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * Constructs a ForceUpdateCheckForm object.
   *
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   * @param \Drupal\Core\Session\AccountProxyInterface $current_user
   *   The current user service.
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   The request stack service.
   */
  public function __construct(Connection $database, EntityTypeManagerInterface $entity_type_manager, AccountProxyInterface $current_user, RequestStack $request_stack) {
    $this->database = $database;
    $this->entityTypeManager = $entity_type_manager;
    $this->currentUser = $current_user;
    $this->requestStack = $request_stack;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('database'),
      $container->get('entity_type.manager'),
      $container->get('current_user'),
      $container->get('request_stack')
    );
  }

  /**
   * Get form id.
   */
  public function getFormId() {
    return 'forcefull_check_update';
  }

  /**
   * Force update check build form.
   *
   * @param array $form
   *   The custom form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The custom form state.
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    global $base_url;
    $request = $this->requestStack->getCurrentRequest();
    $country_name = $request->query->get('country_name');

    $form['markup_text'] = [
      '#type' => 'markup',
      '#markup' => '<b> Are you sure you want to proceed with a force update for ' . $country_name . ' Country</b>',

    ];

    $form['actions'] = [
      '#type' => 'actions',
    ];

    /* Add a submit button. */
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Yes'),
      '#button_type' => 'primary',
    ];

    $form['actions']['submits'] = [
      '#type' => 'inline_template',
      '#template' => '<a href = "' . $base_url . '/admin/config/parent-buddy/forcefull-update-check"><button type="button" class="button">No</button></a>',
    ];

    return $form;
  }

  /**
   * Submit the form.
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    global $base_url;

    $request = $this->requestStack->getCurrentRequest();
    $country_id = $request->query->get('country_id');
    $flag = $request->query->get('flag');
    $user = $this->entityTypeManager->getStorage('user')->load($this->currentUser->id());
    $uuid = $user->uuid();
    $date = new DrupalDateTime();
    if ($flag != '' && $country_id != '') {
      $this->database->insert('forcefull_check_update_api')->fields(
      [
        'flag_status' => $flag,
        'countries_id' => $country_id,
        'uuid' => $uuid,
        'created_at' => $date->getTimestamp(),
      ]
      )->execute();
      drupal_flush_all_caches();
      $path = $base_url . '/admin/config/parent-buddy/forcefull-update-check';
      my_goto($path);
      // drupal_set_message(t('data inserted successfully'), 'status', TRUE);.
      $this->messenger()->addStatus('data inserted successfully');
    }
    else {
      $path = $base_url . '/admin/config/parent-buddy/forcefull-update-check';
      my_goto($path);
      // drupal_set_message(t('Please Select Country And Flag'),
      // 'warning', TRUE);.
      $this->messenger()->addWarning('Please Select Country And Flag');
    }
  }

}
