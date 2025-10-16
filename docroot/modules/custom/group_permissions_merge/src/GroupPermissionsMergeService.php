<?php

namespace Drupal\group_permissions_merge;

use Drupal\Core\Session\AccountInterface;
use Drupal\group\GroupMembershipLoaderInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * Service for merging group permissions across multiple countries.
 */
class GroupPermissionsMergeService {

  /**
   * The group membership loader.
   *
   * @var \Drupal\group\GroupMembershipLoaderInterface
   */
  protected $membershipLoader;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a GroupPermissionsMergeService object.
   *
   * @param \Drupal\group\GroupMembershipLoaderInterface $membership_loader
   *   The group membership loader.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(GroupMembershipLoaderInterface $membership_loader, EntityTypeManagerInterface $entity_type_manager) {
    $this->membershipLoader = $membership_loader;
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * Gets the target roles that should be processed by this module.
   *
   * @return array
   *   Array of target role machine names.
   */
  public static function getTargetRoles() {
    return ['editor', 'se', 'sme'];
  }

  /**
   * Gets merged permissions for a user across all their group memberships.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The user account.
   *
   * @return array
   *   Array of merged permissions.
   */
  public function getMergedPermissions(AccountInterface $account) {
    try {
      // Early return if user doesn't have target roles.
      if (!array_intersect($account->getRoles(), self::getTargetRoles())) {
        return [];
      }

      $memberships = $this->membershipLoader->loadByUser($account);
      if (empty($memberships)) {
        return [];
      }

      $merged_permissions = [];

      foreach ($memberships as $membership) {
        $group = $membership->getGroup();
        if (!$group) {
          continue;
        }

        // Extract permissions from all valid roles.
        $role_permissions = array_reduce($membership->getRoles(), function ($carry, $role) {
          return ($role && is_array($role->getPermissions()))
            ? array_merge($carry, $role->getPermissions())
            : $carry;
        }, []);

        $merged_permissions = array_merge($merged_permissions, $role_permissions);
      }

      return array_unique(array_filter($merged_permissions));
    }
    catch (\Exception $e) {
      return [];
    }
  }

  /**
   * Gets all workflow transition permissions for a user across all groups.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The user account.
   *
   * @return array
   *   Array of workflow transition permissions.
   */
  public function getWorkflowTransitionPermissions(AccountInterface $account) {
    $merged_permissions = $this->getMergedPermissions($account);

    // Filter for workflow transition permissions.
    $workflow_permissions = array_filter($merged_permissions, function ($permission) {
      return strpos($permission, 'use group_workflow transition') === 0;
    });

    return $workflow_permissions;
  }

  /**
   * Checks if a user can perform specific workflow transitions.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The user account.
   * @param string $from_state
   *   The current moderation state.
   * @param string $to_state
   *   The target moderation state.
   *
   * @return bool
   *   TRUE if the user can perform this transition.
   */
  public function canPerformStateTransition(AccountInterface $account, $from_state, $to_state) {
    $user_roles = $account->getRoles();

    // Define allowed transitions per role based on acceptance criteria.
    $allowed_transitions = [
      'editor' => [
        'review_after_translation' => ['draft'],
      ],
      'sme' => [
        'sme_review' => ['senior_editor_review'],
      ],
      'se' => [
        'senior_editor_review' => ['review_after_translation', 'draft', 'sme_review', 'published'],
      ],
    ];

    // Check if user has any of the target roles.
    foreach (['editor', 'sme', 'se'] as $role) {
      if (in_array($role, $user_roles)) {
        if (isset($allowed_transitions[$role][$from_state]) &&
            in_array($to_state, $allowed_transitions[$role][$from_state])) {
          return TRUE;
        }
      }
    }

    return FALSE;
  }

  /**
   * Checks if a user can perform a specific workflow transition.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The user account.
   * @param string $transition_id
   *   The workflow transition ID.
   * @param \Drupal\node\NodeInterface $node
   *   The node entity (optional).
   *
   * @return bool
   *   TRUE if user can perform the transition, FALSE otherwise.
   */
  public function canPerformWorkflowTransition(AccountInterface $account, $transition_id, $node = NULL) {
    // Early return if user doesn't have target roles.
    if (!array_intersect($account->getRoles(), self::getTargetRoles())) {
      return FALSE;
    }

    $workflow_permissions = $this->getWorkflowTransitionPermissions($account);
    $required_permission = 'use group_workflow transition ' . $transition_id;
    $has_permission = in_array($required_permission, $workflow_permissions);

    return $has_permission;
  }

}
