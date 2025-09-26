<?php

namespace Drupal\pb_custom_field\Plugin\Action;

use Drupal\views_bulk_operations\Action\ViewsBulkOperationsActionBase;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\group\GroupMembershipLoaderInterface;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Psr\Log\LoggerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * Action description.
 *
 * @Action(
 *   id = "pb_custom_field_publish_to_senioreditor",
 *   label = @Translation("Publish to Archive then Senior Editor Review"),
 *   type = "node",
 *   confirm = FALSE
 * )
 */
class MovefrompublishtosenioreditorAction extends ViewsBulkOperationsActionBase implements ContainerFactoryPluginInterface {

  use StringTranslationTrait;

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
   * The current user service.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * The group membership loader service.
   *
   * @var \Drupal\group\GroupMembershipLoaderInterface
   */
  protected $groupMembershipLoader;

  /**
   * The time service.
   *
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  protected $time;

  /**
   * The messenger service.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * The logger service.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a new MovefrompublishtosenioreditorAction object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Session\AccountProxyInterface $current_user
   *   The current user service.
   * @param \Drupal\group\GroupMembershipLoaderInterface $group_membership_loader
   *   The group membership loader service.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time service.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger service.
   * @param \Psr\Log\LoggerInterface $logger
   *   The logger service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, AccountProxyInterface $current_user, GroupMembershipLoaderInterface $group_membership_loader, TimeInterface $time, MessengerInterface $messenger, LoggerInterface $logger, EntityTypeManagerInterface $entity_type_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->currentUser = $current_user;
    $this->groupMembershipLoader = $group_membership_loader;
    $this->time = $time;
    $this->messenger = $messenger;
    $this->logger = $logger;
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new self(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('current_user'),
      $container->get('group.membership_loader'),
      $container->get('datetime.time'),
      $container->get('messenger'),
      $container->get('logger.factory')->get('Content Bulk updated'),
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function execute(?ContentEntityInterface $entity = NULL) {
    $uid = $this->currentUser->id();
    $user = $this->entityTypeManager->getStorage('user')->load($uid);
    // $groups = array();
    $grps = $this->groupMembershipLoader->loadByUser($user);
    if (!empty($grps)) {
      foreach ($grps as $grp) {
        $groups = $grp->getGroup();
      }
      $grp_country_language = $groups->get('field_language')->getValue();
      $grp_country_new_array = array_column($grp_country_language, 'value');
    }
    $this->processItem = $this->processItem + 1;
    $list = $this->context['list'];
    $list_count = count($list);
    $message = "";
    $error_message = "";
    $current_language = $entity->get('langcode')->value;
    $nid = $entity->get('nid')->getString();
    $archive_node = $this->entityTypeManager->getStorage('node')->load($nid);
    $ids = array_column($list, '0');
    $all_ids = implode(',', $ids);
    $node_lang_archive = $archive_node->getTranslation($current_language);
    $current_state = $node_lang_archive->moderation_state->value;
    if ($current_state == 'published' && empty($grps)) {
      /* Change status from publish to archive. */
      $uid = $this->currentUser->id();
      $node_lang_archive->set('moderation_state', 'archive');
      $node_lang_archive->set('uid', $uid);
      $node_lang_archive->set('content_translation_source', $current_language);
      $node_lang_archive->set('changed', time());
      $node_lang_archive->set('created', time());

      $node_lang_archive->setNewRevision(TRUE);
      $node_lang_archive->revision_log = 'Content changed  from "Published" to "Archive" and than "Senior Editor Review"';
      $node_lang_archive->setRevisionCreationTime($this->time->getRequestTime());
      $node_lang_archive->setRevisionUserId($uid);
      $node_lang_archive->setRevisionTranslationAffected(NULL);
      $node_lang_archive->save();
      $archive_node->save();
      /* Change status from publish to senior_editor_review. */
      $draft_node = $this->entityTypeManager->getStorage('node')->load($nid);
      $node_lang_draft = $draft_node->getTranslation($current_language);
      $node_lang_draft->set('moderation_state', 'senior_editor_review');
      $node_lang_draft->set('uid', $uid);
      $node_lang_draft->set('content_translation_source', $current_language);
      $node_lang_draft->set('changed', time());
      $node_lang_draft->set('created', time());
      $node_lang_draft->save();
      $draft_node->save();
      $this->assigned = $this->assigned + 1;
    }
    elseif ($current_state == 'published' && !empty($grps)) {
      if (in_array($current_language, $grp_country_new_array)) {
        /* Change status from publish to archive. */
        $uid = $this->currentUser->id();
        $node_lang_archive->set('moderation_state', 'archive');
        $node_lang_archive->set('uid', $uid);
        $node_lang_archive->set('content_translation_source', $current_language);
        $node_lang_archive->set('changed', time());
        $node_lang_archive->set('created', time());

        $node_lang_archive->setNewRevision(TRUE);
        $node_lang_archive->revision_log = 'Content changed  from "Published" to "Archive" and than "Senior Editor Review"';
        $node_lang_archive->setRevisionCreationTime($this->time->getRequestTime());
        $node_lang_archive->setRevisionUserId($uid);
        $node_lang_archive->setRevisionTranslationAffected(NULL);
        $node_lang_archive->save();
        $archive_node->save();
        /* Change status from publish to senior_editor_review. */
        $draft_node = $this->entityTypeManager->getStorage('node')->load($nid);
        $node_lang_draft = $draft_node->getTranslation($current_language);
        $node_lang_draft->set('moderation_state', 'senior_editor_review');
        $node_lang_draft->set('uid', $uid);
        $node_lang_draft->set('content_translation_source', $current_language);
        $node_lang_draft->set('changed', time());
        $node_lang_draft->set('created', time());
        $node_lang_draft->save();
        $draft_node->save();
        $this->assigned = $this->assigned + 1;
      }
      else {
        $this->countryRestrict = $this->countryRestrict + 1;

      }
    }
    else {
      $this->nonAssigned = $this->nonAssigned + 1;

    }
    if ($this->nonAssigned > 0) {
      $error_message = $this->t("Please Select Published Content ( @nonassigned ) <br/>", ['@nonassigned' => $this->nonAssigned]);
    }
    else {
      $message = $this->t("Content Changed Into Senior Editor Review Successfully ( @assigned ) <br/>", ['@assigned' => $this->assigned]);
    }

    if ($this->countryRestrict > 0) {
      $error_message = $this->t("This content belongs to Master content and cannot be edited. It has to be assigned to your country to allow for further editing and contextualization. ( @countryRestrict ) <br/>", ['@countryRestrict' => $this->countryRestrict]);
    }

    /* $message.="Please visit Country content page to view.";*/
    if ($list_count == $this->processItem) {
      if (!empty($message)) {
        // drupal_set_message($message, 'status');.
        $this->messenger->addStatus($message);
      }
      if (!empty($error_message)) {
        // drupal_set_message($error_message, 'error');.
        $this->messenger->addError($error_message);
      }
    }
    if ($this->processItem == 1) {
      /* Please add the entity */
      $message = 'Content Bulk updated from archieve to Senior Editor Review by' . $uid . " content id - " . $all_ids;
      $this->logger->info($message);
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
