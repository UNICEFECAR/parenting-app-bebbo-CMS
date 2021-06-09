<?php

namespace Drupal\gcontent_moderation;

use Drupal\content_moderation\ModerationInformationInterface;
use Drupal\content_moderation\StateTransitionValidation;
use Drupal\content_moderation\StateTransitionValidationInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\group\Context\GroupRouteContextTrait;
use Drupal\group\Plugin\GroupContentEnablerManagerInterface;
use Drupal\workflows\StateInterface;
use Drupal\workflows\Transition;
use Drupal\workflows\WorkflowInterface;

/**
 * Validates whether a certain state transition is allowed.
 */
class GroupStateTransitionValidation extends StateTransitionValidation implements StateTransitionValidationInterface {

  use GroupRouteContextTrait;

  /**
   * The content moderation state transition validation service.
   *
   * @var \Drupal\content_moderation\StateTransitionValidationInterface
   */
  protected $inner;

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The moderation information service.
   *
   * @var \Drupal\content_moderation\ModerationInformationInterface
   */
  protected $moderationInformation;

  /**
   * The route match service.
   *
   * @var \Drupal\Core\Routing\RouteMatchInterface
   */
  protected $currentRouteMatch;

  /**
   * The group content enabler plugin manager.
   *
   * @var \Drupal\group\Plugin\GroupContentEnablerManagerInterface
   */
  protected $groupContentEnablerManager;

  /**
   * Constructs the group state transition validation object.
   *
   * @param \Drupal\content_moderation\StateTransitionValidationInterface $inner
   *   The content moderation state transition validation service.
   * @param \Drupal\content_moderation\ModerationInformationInterface $moderation_information
   *   The moderation information service.
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The current route match service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   * @param \Drupal\group\Plugin\GroupContentEnablerManagerInterface $group_content_enabler_manager
   *   The group content enabler plugin manager.
   */
  public function __construct(StateTransitionValidationInterface $inner, ModerationInformationInterface $moderation_information, RouteMatchInterface $route_match, EntityTypeManagerInterface $entity_type_manager, GroupContentEnablerManagerInterface $group_content_enabler_manager) {
    $this->inner = $inner;
    $this->entityTypeManager = $entity_type_manager;
    $this->moderationInformation = $moderation_information;
    $this->currentRouteMatch = $route_match;
    $this->groupContentEnablerManager = $group_content_enabler_manager;
  }

  /**
   * {@inheritdoc}
   */
  public function getValidTransitions(ContentEntityInterface $entity, AccountInterface $user) {
    // New entities are not yet associated with a group, but if we are using the
    // wizard we can discover the group from the route parameters.
    if ($entity->isNew()) {
      $group = $this->getGroupFromRoute();
      if ($group) {
        return array_merge(
          $this->allowedTransitions($user, $entity, [$group]),
          $this->inner->getValidTransitions($entity, $user)
        );
      }
      return $this->inner->getValidTransitions($entity, $user);
    }

    // Only act if there are group content types for this entity bundle.
    $group_content_types = $this->entityTypeManager->getStorage('group_content_type')->loadByContentPluginId($this->getPluginId($entity));
    if (empty($group_content_types)) {
      return $this->inner->getValidTransitions($entity, $user);
    }

    // Load all the group content for this entity.
    $group_contents = $this->entityTypeManager
      ->getStorage('group_content')
      ->loadByProperties([
        'type' => array_keys($group_content_types),
        'entity_id' => $entity->id(),
      ]);

    // If the entity does not belong to any group, we have nothing to say.
    if (empty($group_contents)) {
      return $this->inner->getValidTransitions($entity, $user);
    }

    /** @var \Drupal\group\Entity\GroupInterface[] $groups */
    $groups = [];
    foreach ($group_contents as $group_content) {
      /** @var \Drupal\group\Entity\GroupContentInterface $group_content */
      $group = $group_content->getGroup();
      $groups[$group->id()] = $group;
    }

    // Merge the inner service transitions.
    return array_merge(
      $this->inner->getValidTransitions($entity, $user),
      $this->allowedTransitions($user, $entity, $groups)
    );
  }

  /**
   * Create a plugin ID based on an entity.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity to get the Plugin for.
   *
   * @return string
   *   The plugin ID
   */
  public function getPluginId(ContentEntityInterface $entity) {
    $generic = FALSE;
    foreach ($this->groupContentEnablerManager->getDefinitions() as $id => $def) {
      if ($def['entity_type_id'] == $entity->getEntityTypeId()
        && $def['entity_bundle'] == $entity->bundle()) {

        return $id;
      }
      elseif ($def['entity_type_id'] === $entity->getEntityTypeId()) {
        $generic = $id;
      }
    }
    return $generic ?: FALSE;
  }

  /**
   * Run the permissions checks against this set of groups.
   *
   * @param \Drupal\Core\Session\AccountInterface $user
   *   The user to check access to transitions for.
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The moderated entity.
   * @param array $groups
   *   The groups the entity belongs to.
   *
   * @return \Drupal\workflows\TransitionInterface[]
   *   Array of valid transitions.
   */
  public function allowedTransitions(AccountInterface $user, ContentEntityInterface $entity, array $groups) {
    // Load the workflow and current state for this entity.
    $workflow = $this->moderationInformation->getWorkflowForEntity($entity);
    $current_state = $entity->moderation_state->value ?
      $workflow->getTypePlugin()->getState($entity->moderation_state->value) :
      $workflow->getTypePlugin()->getInitialState($entity);

    // Check the group access. If you are not granted access for a transition
    // in any of the groups the entity belongs to, we will check for global
    // access to that transition instead.
    return array_filter($current_state->getTransitions(), function (Transition $transition) use ($workflow, $user, $groups) {
      foreach ($groups as $group) {
        if ($group->hasPermission('use ' . $workflow->id() . ' transition ' . $transition->id(), $user)) {
          return TRUE;
        }
      }
    });
  }

  /**
   * {@inheritdoc}
   */
  public function isTransitionValid(WorkflowInterface $workflow, StateInterface $original_state, StateInterface $new_state, AccountInterface $user, ContentEntityInterface $entity = NULL) {
    // We can only make a determination if we have the entity, otherwise we
    // won't be able to reference the participants.
    if ($entity) {
      $original_moderation_state = $entity->get('moderation_state')->value;
      // As this may be occurring during validation, the moderation state on the
      // entity may be the new state, rather than the current state, so make
      // sure we're working with the current version.
      if ($entity->isNew()) {
        $inital_moderation_state = $workflow->getTypePlugin()->getInitialState($entity);
        $entity->set('moderation_state', $inital_moderation_state->id());

        $original_entity = $entity;
      }
      else {
        $original_revision = $this->entityTypeManager->getStorage($entity->getEntityTypeId())->loadRevision($entity->getLoadedRevisionId());
        $original_entity = $original_revision->hasTranslation($entity->language()->getId()) ? $original_revision->getTranslation($entity->language()->getId()) : $original_revision;
      }

      $transition = $workflow->getTypePlugin()->getTransitionFromStateToState($original_state->id(), $new_state->id());
      $is_valid = in_array($transition->id(), array_keys($this->getValidTransitions($original_entity, $user)));
      $entity->set('moderation_state', $original_moderation_state);
      return $is_valid;
    }

    return FALSE;
  }

}
