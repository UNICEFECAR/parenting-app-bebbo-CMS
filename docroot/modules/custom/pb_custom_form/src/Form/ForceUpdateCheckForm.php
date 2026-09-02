<?php

namespace Drupal\pb_custom_form\Form;

use Drupal\Core\Database\Connection;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\bebbo_custom_general\StoreUrl;
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
    return new self(
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
    $update_type = $request->query->get('update_type', 'content_update');
    $flag = $request->query->get('flag', '0');

    $update_type_label = $update_type === 'app_update'
      ? $this->t('Force App Update')
      : $this->t('Force Content Update');
    $flag_label = $flag === '1' ? $this->t('Enable') : $this->t('Disable');

    $form['markup_text'] = [
      '#type' => 'markup',
      '#markup' => '<b>' . $this->t(
        'Are you sure you want to @flag @type for @country Country?',
        [
          '@flag' => $flag_label,
          '@type' => $update_type_label,
          '@country' => $country_name,
        ]
      ) . '</b>',
    ];

    $form['actions'] = [
      '#type' => 'actions',
    ];

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
    $update_type = $request->query->get('update_type', 'content_update');
    $google_play_url = trim((string) $request->query->get('google_play_url', ''));
    $app_store_url = trim((string) $request->query->get('app_store_url', ''));

    // These arrive as query parameters rather than form values, so the entry
    // form's validation guarantees nothing about what reaches the insert.
    // Drop anything that is not a real store listing instead of storing a
    // link the apps cannot follow.
    if ($google_play_url !== '' && !StoreUrl::isGooglePlay($google_play_url)) {
      $this->messenger()->addWarning($this->t('The Google Play URL was not a store listing and has been ignored.'));
      $google_play_url = '';
    }
    if ($app_store_url !== '' && !StoreUrl::isAppStore($app_store_url)) {
      $this->messenger()->addWarning($this->t('The App Store URL was not a store listing and has been ignored.'));
      $app_store_url = '';
    }

    $user = $this->entityTypeManager->getStorage('user')->load($this->currentUser->id());
    $uuid = $user->uuid();
    $date = new DrupalDateTime();

    if ($flag !== NULL && $flag !== '' && $country_id !== NULL && $country_id !== '') {
      $this->database->insert('forcefull_check_update_api')->fields(
        [
          'flag_status' => $flag,
          'countries_id' => $country_id,
          'uuid' => $uuid,
          'created_at' => $date->getTimestamp(),
          'update_type' => in_array($update_type, ['content_update', 'app_update'], TRUE)
            ? $update_type
            : 'content_update',
          'google_play_url' => $google_play_url ?: NULL,
          'app_store_url' => $app_store_url ?: NULL,
        ]
      )->execute();
      drupal_flush_all_caches();
      $path = $base_url . '/admin/config/parent-buddy/forcefull-update-check';
      pb_custom_form_my_goto($path);
      $this->messenger()->addStatus($this->t('Force update record saved successfully.'));
    }
    else {
      $path = $base_url . '/admin/config/parent-buddy/forcefull-update-check';
      pb_custom_form_my_goto($path);
      $this->messenger()->addWarning($this->t('Please select a country and flag.'));
    }
  }

}
