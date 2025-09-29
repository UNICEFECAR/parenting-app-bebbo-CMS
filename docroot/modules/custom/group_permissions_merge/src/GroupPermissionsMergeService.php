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
   * Gets merged permissions for a user across all their group memberships.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The user account.
   *
   * @return array
   *   Array of merged permissions.
   */
  public function getMergedPermissions(AccountInterface $account) {
    $user_roles = $account->getRoles();
    $target_roles = ['editor', 'se', 'sme'];

    if (!array_intersect($user_roles, $target_roles)) {
      return [];
    }

    $memberships = $this->membershipLoader->loadByUser($account);
    $merged_permissions = [];

    foreach ($memberships as $membership) {
      $group_roles = $membership->getRoles();

      foreach ($group_roles as $group_role) {
        $role_permissions = $group_role->getPermissions();
        $merged_permissions = array_merge($merged_permissions, $role_permissions);
      }
    }

    return array_unique($merged_permissions);
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
    $user_roles = $account->getRoles();
    $target_roles = ['editor', 'se', 'sme'];

    if (!array_intersect($user_roles, $target_roles)) {
      return FALSE;
    }

    $memberships = $this->membershipLoader->loadByUser($account);
    $group_content_storage = $this->entityTypeManager->getStorage('group_content');

    foreach ($memberships as $membership) {
      $group = $membership->getGroup();
      $group_roles = $membership->getRoles();

      // Check if the node belongs to this group.
      $group_contents = $group_content_storage->loadByProperties([
        'gid' => $group->id(),
        'entity_id' => $node->id(),
        'type' => 'country-group_node-' . $node->bundle(),
      ]);

      if (!empty($group_contents)) {
        // Check if user has the required permission in any of their group.
        foreach ($group_roles as $group_role) {
          if ($this->hasRequiredPermission($group_role, $node->bundle(), $operation)) {
            return TRUE;
          }
        }
      }
    }

    return FALSE;
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
    $permissions = $group_role->getPermissions();

    $required_permissions = [];
    switch ($operation) {
      case 'view':
        $required_permissions = [
          'view unpublished group_node:' . $bundle . ' entity',
          'view latest version',
        ];
        break;

      case 'update':
        $required_permissions = [
          'update any group_node:' . $bundle . ' entity',
          'update own group_node:' . $bundle . ' entity',
        ];
        break;

      case 'delete':
        $required_permissions = [
          'delete any group_node:' . $bundle . ' entity',
          'delete own group_node:' . $bundle . ' entity',
        ];
        break;
    }

    return !empty(array_intersect($required_permissions, $permissions));
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

}
