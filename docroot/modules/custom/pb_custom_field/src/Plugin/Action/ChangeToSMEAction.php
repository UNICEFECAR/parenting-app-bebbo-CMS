<?php

namespace Drupal\pb_custom_field\Plugin\Action;

use Drupal\views_bulk_operations\Action\ViewsBulkOperationsActionBase;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\node\Entity\Node;
use Drupal\user\Entity\User;
use Symfony\Component\DependencyInjection\ContainerInterface;

/* use Drupal\node\Entity\Node;
use Drupal\user\Entity\User;
 */

/**
 * Action description.
 *
 * @Action(
 *   id = "pb_custom_field_change_to_sme",
 *   label = @Translation("Change to SME"),
 *   type = "node",
 *   confirm = FALSE
 * )
 */
class ChangeToSMEAction extends ViewsBulkOperationsActionBase {
  use StringTranslationTrait;

  /**
   * The current user.
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
   * The messenger service.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * The logger channel.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * The request stack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * The time service.
   *
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  protected $time;

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
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, $current_user, $group_membership_loader, $messenger, $logger, $request_stack, $time) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->currentUser = $current_user;
    $this->groupMembershipLoader = $group_membership_loader;
    $this->messenger = $messenger;
    $this->logger = $logger;
    $this->requestStack = $request_stack;
    $this->time = $time;
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
      $container->get('messenger'),
      $container->get('logger.factory')->get('bulk_action'),
      $container->get('request_stack'),
      $container->get('datetime.time')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function execute(?ContentEntityInterface $entity = NULL) {
    $uid = $this->currentUser->id();
    $user = User::load($uid);
    $grp_membership_service = $this->groupMembershipLoader;
    $grps = $grp_membership_service->loadByUser($user);
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
    $this->processItem = $this->processItem + 1;
    $list = $this->context['list'];
    $list_count = count($list);
    $message = "";
    $error_message = "";
    $log = [];
    $current_language = $entity->get('langcode')->value;
    $nid = $entity->get('nid')->getString();
    $archive_node = Node::load($nid);
    array_column($list, '0');
    $node_lang_archive = $archive_node->getTranslation($current_language);
    $current_state = $node_lang_archive->moderation_state->value;
    if ($current_state !== 'sme_review' && empty($grps)) {
      $uid = $this->currentUser->id();
      $node_lang_archive->set('moderation_state', 'sme_review');
      $node_lang_archive->set('uid', $uid);
      $node_lang_archive->set('content_translation_source', $current_language);
      $node_lang_archive->set('changed', time());
      $node_lang_archive->setNewRevision(TRUE);
      $node_lang_archive->revision_log = 'Content changed  into SME Review State';
      $node_lang_archive->setRevisionCreationTime($this->time->getRequestTime());
      $node_lang_archive->setRevisionUserId($uid);
      $node_lang_archive->setRevisionTranslationAffected(NULL);
      $node_lang_archive->save();
      $archive_node->save();
      $this->assigned = $this->assigned + 1;
    }
    elseif ($current_state !== 'sme_review' && !empty($grps)) {
      if (in_array($current_language, $grp_country_new_array)) {
        $uid = $this->currentUser->id();
        $node_lang_archive->set('moderation_state', 'sme_review');
        $node_lang_archive->set('uid', $uid);
        $node_lang_archive->set('content_translation_source', $current_language);
        $node_lang_archive->set('changed', time());
        $node_lang_archive->setNewRevision(TRUE);
        $node_lang_archive->revision_log = 'Content changed  into SME Review State';
        $node_lang_archive->setRevisionCreationTime($this->time->getRequestTime());
        $node_lang_archive->setRevisionUserId($uid);
        $node_lang_archive->setRevisionTranslationAffected(NULL);
        $node_lang_archive->save();
        $archive_node->save();
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
      $error_message = $this->t("Selected content is already in SME Review state ( @nonassigned ) <br/>", ['@nonassigned' => $this->nonAssigned]);
      $log["status"] = $error_message;
    }
    if ($this->assigned > 0) {
      $message = $this->t("Content changed into SME Review successfully ( @assigned ) <br/>", ['@assigned' => $this->assigned]);
      $log["status"] = $message;
    }
    if ($this->countryRestrict > 0) {
      $error_message = $this->t("This content belongs to Master content and cannot be edited. It has to be assigned to your country to allow for further editing and contextualization. ( @countryRestrict ) <br/>", ['@countryRestrict' => $this->countryRestrict]);
      $log["status"] = $error_message;
    }
    $logs = json_encode($log);
    $this->logger->info($logs);
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
