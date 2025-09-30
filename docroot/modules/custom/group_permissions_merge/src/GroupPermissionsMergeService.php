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
   * Checks if a user has access to a node through any of their group.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The user account.
   * @param \Drupal\node\NodeInterface $node
   *   The node to check access for.
   * @param string $operation
   *   The operation (view, update, delete).
   *
   * @return bool
   *   TRUE if user has access, FALSE otherwise.
   */
  public function hasNodeAccess(AccountInterface $account, $node, $operation) {
    try {
      // Early returns for validation.
      if (!array_intersect($account->getRoles(), self::getTargetRoles()) ||
          !in_array($operation, ['view', 'update'])) {
        return FALSE;
      }

      $memberships = $this->membershipLoader->loadByUser($account);
      if (empty($memberships)) {
        return FALSE;
      }

      $group_content_storage = $this->entityTypeManager->getStorage('group_content');
      $node_bundle = $node->bundle();

      foreach ($memberships as $membership) {
        $group = $membership->getGroup();
        if (!$group) {
          continue;
        }

        // Check if node belongs to this group.
        $group_contents = $group_content_storage->loadByProperties([
          'gid' => $group->id(),
          'entity_id' => $node->id(),
          'type' => 'country-group_node-' . $node_bundle,
        ]);

        // Check permissions if node belongs to group.
        if (!empty($group_contents) &&
            array_reduce($membership->getRoles(), function ($carry, $role) use ($node_bundle, $operation) {
              return $carry || $this->hasRequiredPermission($role, $node_bundle, $operation);
            }, FALSE)) {
          return TRUE;
        }
      }

      return FALSE;
    }
    catch (\Exception $e) {
      return FALSE;
    }
  }

  /**
   * Checks if a group role has the required permission for an operation.
   *
   * @param \Drupal\group\Entity\GroupRoleInterface $group_role
   *   The group role.
   * @param string $bundle
   *   The node bundle.
   * @param string $operation
   *   The operation.
   *
   * @return bool
   *   TRUE if the role has the required permission.
   */
  protected function hasRequiredPermission($group_role, $bundle, $operation) {
    if (!$group_role || empty($bundle) || empty($operation)) {
      return FALSE;
    }

    $permissions = $group_role->getPermissions();
    if (!is_array($permissions)) {
      return FALSE;
    }

    // Define permission patterns for each operation.
    $permission_patterns = [
      'view' => [
        'view unpublished group_node:' . $bundle . ' entity',
        'view latest version',
        'view group_node:' . $bundle . ' entity',
      ],
      'update' => [
        'update any group_node:' . $bundle . ' entity',
        'update own group_node:' . $bundle . ' entity',
      ],
      'delete' => [
        'delete any group_node:' . $bundle . ' entity',
        'delete own group_node:' . $bundle . ' entity',
      ],
    ];

    return isset($permission_patterns[$operation]) &&
           !empty(array_intersect($permission_patterns[$operation], $permissions));
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

    return $has_permission || ($node && $this->hasNodeAccess($account, $node, 'update') && $has_permission);
  }

}
