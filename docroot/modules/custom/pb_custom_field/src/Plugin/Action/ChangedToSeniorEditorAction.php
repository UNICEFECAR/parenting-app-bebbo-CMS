<?php

namespace Drupal\pb_custom_field\Plugin\Action;

use Drupal\views_bulk_operations\Action\ViewsBulkOperationsActionBase;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\node\NodeStorageInterface;
use Drupal\user\UserStorageInterface;
use Drupal\group\GroupMembershipLoaderInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;

/**
 * Action description.
 *
 * @Action(
 *   id = "pb_custom_field_change_to_senior_editor",
 *   label = @Translation("Change to Senior Editor"),
 *   type = "node",
 *   confirm = FALSE
 * )
 */
class ChangedToSeniorEditorAction extends ViewsBulkOperationsActionBase implements ContainerFactoryPluginInterface {

  use StringTranslationTrait;

  /**
   * The group membership loader service.
   *
   * @var \Drupal\group\GroupMembershipLoaderInterface
   */
  protected $groupMembershipLoader;

  /**
   * The messenger service.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * The time service.
   *
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  protected $time;

  /**
   * The logger factory service.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface
   */
  protected $loggerFactory;

  /**
   * The request stack service.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * The current user service.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * The user storage service.
   *
   * @var \Drupal\user\UserStorageInterface
   */
  protected $userStorage;

  /**
   * The node storage service.
   *
   * @var \Drupal\node\NodeStorageInterface
   */
  protected $nodeStorage;

  /**
   * Get the total translated count.
   *
   * @var int
   */
  public $assigned = 0;

  /**
   * Get the total non translated count.
   *
   * @var int
   */
  public $nonAssigned = 0;

  /**
   * Get the total non translated count.
   *
   * @var int
   */
  public $countryRestrict = 0;

  /**
   * Get the total items processed.
   *
   * @var int
   */
  public $processItem = 0;

  /**
   * Constructs a new ChangedToSeniorEditorAction object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin ID for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\group\GroupMembershipLoaderInterface $group_membership_loader
   *   The group membership loader service.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger service.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time service.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger factory service.
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   The request stack service.
   * @param \Drupal\Core\Session\AccountInterface $current_user
   *   The current user service.
   * @param \Drupal\user\UserStorageInterface $user_storage
   *   The user storage service.
   * @param \Drupal\node\NodeStorageInterface $node_storage
   *   The node storage.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, GroupMembershipLoaderInterface $group_membership_loader, MessengerInterface $messenger, TimeInterface $time, LoggerChannelFactoryInterface $logger_factory, RequestStack $request_stack, AccountInterface $current_user, UserStorageInterface $user_storage, NodeStorageInterface $node_storage) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->groupMembershipLoader = $group_membership_loader;
    $this->messenger = $messenger;
    $this->time = $time;
    $this->loggerFactory = $logger_factory;
    $this->requestStack = $request_stack;
    $this->currentUser = $current_user;
    $this->userStorage = $user_storage;
    $this->nodeStorage = $node_storage;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new self(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('group.membership_loader'),
      $container->get('messenger'),
      $container->get('datetime.time'),
      $container->get('logger.factory'),
      $container->get('request_stack'),
      $container->get('current_user'),
      $container->get('entity_type.manager')->getStorage('user'),
      $container->get('entity_type.manager')->getStorage('node')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function execute(?ContentEntityInterface $entity = NULL) {
    $uid = $this->currentUser->id();
    $user = $this->userStorage->load($uid);

    $grp_country_new_array = [];

    if ($user) {
      $grps = $this->groupMembershipLoader->loadByUser($user);
      $grp_country_new_array = [];
      if (!empty($grps)) {
        // Collect languages from ALL groups the user belongs to.
        foreach ($grps as $grp) {
          $group = $grp->getGroup();
          if ($group && $group->hasField('field_language') && !$group->get('field_language')->isEmpty()) {
            $grp_country_language = $group->get('field_language')->getValue();
            $group_languages = array_column($grp_country_language, 'value');
            $grp_country_new_array = array_merge($grp_country_new_array, $group_languages);
          }
        }
        $grp_country_new_array = array_unique($grp_country_new_array);
      }
    }

    $this->processItem = $this->processItem + 1;
    $list = $this->context['list'];
    $list_count = count($list);
    $message = "";
    $error_message = "";
    $current_language = $entity->get('langcode')->value;
    $nid = $entity->get('nid')->getString();
    $archive_node = $this->nodeStorage->load($nid);
    array_column($list, '0');
    $node_lang_archive = $archive_node->getTranslation($current_language);
    $current_state = $node_lang_archive->moderation_state->value;

    if ($current_state !== 'senior_editor_review' && empty($grps)) {
      /* Change status from publish to archive. */
      $node_lang_archive->set('moderation_state', 'senior_editor_review');
      $node_lang_archive->set('uid', $uid);
      $node_lang_archive->set('content_translation_source', $current_language);
      $node_lang_archive->set('changed', $this->time->getRequestTime());

      $node_lang_archive->setNewRevision(TRUE);
      $node_lang_archive->revision_log = 'Content changed into Senior Editor Review State';
      $node_lang_archive->setRevisionCreationTime($this->time->getRequestTime());
      $node_lang_archive->setRevisionUserId($uid);
      $node_lang_archive->setRevisionTranslationAffected(TRUE);
      $node_lang_archive->save();
      $this->assigned = $this->assigned + 1;
    }
    elseif ($current_state !== 'senior_editor_review' && !empty($grps)) {
      if (in_array($current_language, $grp_country_new_array)) {
        /* Change status into "Senior Editor Review" state. */
        $node_lang_archive->set('moderation_state', 'senior_editor_review');
        $node_lang_archive->set('uid', $uid);
        $node_lang_archive->set('content_translation_source', $current_language);
        $node_lang_archive->set('changed', $this->time->getRequestTime());

        $node_lang_archive->setNewRevision(TRUE);
        $node_lang_archive->revision_log = 'Content changed into Senior Editor Review State';
        $node_lang_archive->setRevisionCreationTime($this->time->getRequestTime());
        $node_lang_archive->setRevisionUserId($uid);
        $node_lang_archive->setRevisionTranslationAffected(TRUE);
        $node_lang_archive->save();
        $this->assigned = $this->assigned + 1;
      }
      else {
        $this->countryRestrict = $this->countryRestrict + 1;
      }
    }
    else {
      $this->nonAssigned = $this->nonAssigned + 1;
    }

    $log["source_language"] = $current_language;
    $log["nid"] = $nid;
    $log["uid"] = $uid;
    $current_uri = $this->requestStack->getCurrentRequest()->getRequestUri();
    $log["requested_url"] = $current_uri;

    if ($this->nonAssigned > 0) {
      $error_message = $this->t("Selected content is already in Senior Editor Review state ( @nonassigned ) <br/>", ['@nonassigned' => $this->nonAssigned]);
      $log["status"] = $error_message;
    }
    if ($this->assigned > 0) {
      $message = $this->t("Content changed into Senior Editor Review successfully ( @assigned ) <br/>", ['@assigned' => $this->assigned]);
      $log["status"] = $message;
    }
    if ($this->countryRestrict > 0) {
      $error_message = $this->t("This content belongs to Master content and cannot be edited. It has to be assigned to your country to allow for further editing and contextualization. ( @countryRestrict ) <br/>", ['@countryRestrict' => $this->countryRestrict]);
      $log["status"] = $error_message;
    }

    $logs = json_encode($log);
    $this->loggerFactory->get('bulk_action')->info($logs);

    if ($list_count == $this->processItem) {
      if (!empty($message)) {
        $this->messenger->addStatus($message);
      }
      if (!empty($error_message)) {
        $this->messenger->addError($error_message);
      }
    }

    return $this->t("Total content selected");
  }

  /**
   * {@inheritdoc}
   */
  public function access($object, ?AccountInterface $account = NULL, $return_as_object = FALSE) {
    if ($object->getEntityType() === 'node') {
      $access = $object->access('update', $account, TRUE)
        ->andIf($object->status->access('edit', $account, TRUE));
      return $return_as_object ? $access : $access->isAllowed();
    }
    return TRUE;
  }

}
