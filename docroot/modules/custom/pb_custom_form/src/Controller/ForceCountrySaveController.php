<?php

namespace Drupal\pb_custom_form\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * An pb_custom_form controller.
 */
class ForceCountrySaveController extends ControllerBase {

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
   * Constructs a ForceCountrySaveController object.
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
   * Returns a render-able array for a test page.
   */
  public function content() {
    global $base_url;

    $request = $this->requestStack->getCurrentRequest();
    $country_id = $request->query->get('country_id');
    $flag = $request->query->get('flag');
    $user = $this->entityTypeManager->getStorage('user')->load($this->currentUser->id());
    $uuid = $user->uuid();
    $date = new DrupalDateTime();
    $this->database->insert('forcefull_check_update_api')->fields(
      [
        'flag' => $flag,
        'country_id' => $country_id,
        'updated_at' => $date->getTimestamp(),
        'uuid' => $uuid,
        'created_at' => $date->getTimestamp(),
      ]
    )->execute();
    drupal_flush_all_caches();
    $path = $base_url . '/admin/config/parent-buddy/forcefull-update-check';
    my_goto($path);

    // $build = [
    // '#markup' => 'insert seccefully',
    // ];
    // return $build;
  }

}
