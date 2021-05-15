<?php

namespace Drupal\group\Entity\Access;

use Drupal\Core\Session\AccountInterface;
use Drupal\entity\QueryAccess\ConditionGroup;
use Drupal\group\Access\CalculatedGroupPermissionsItemInterface as CGPII;

/**
 * Controls query access for group entities.
 *
 * @see \Drupal\entity\QueryAccess\QueryAccessHandler
 */
class GroupQueryAccessHandler extends QueryAccessHandlerBase {

  /**
   * Retrieves the group permission name for the given operation.
   *
   * @param string $operation
   *   The access operation. Usually one of "view", "update" or "delete".
   *
   * @return string
   *   The group permission name.
   */
  protected function getPermissionName($operation) {
    switch ($operation) {
      // @todo Could use the below if permission were named 'update group'.
      case 'update':
        $permission = 'edit group';
        break;

      case 'delete':
      case 'view':
        $permission = "$operation group";
        break;

      default:
        $permission = 'view group';
    }

    return $permission;
  }

  /**
   * Builds the conditions for the given operation and account.
   *
   * @param string $operation
   *   The access operation. Usually one of "view", "update" or "delete".
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The user for which to restrict access.
   *
   * @return \Drupal\entity\QueryAccess\ConditionGroup
   *   The conditions.
   */
  protected function buildConditions($operation, AccountInterface $account) {
    $conditions = new ConditionGroup('OR');

    // @todo Remove these lines once we kill the bypass permission.
    // If the account can bypass group access, we do not alter the query at all.
    $conditions->addCacheContexts(['user.permissions']);
    if ($account->hasPermission('bypass group access')) {
      return $conditions;
    }

    $permission = $this->getPermissionName($operation);
    $conditions->addCacheContexts(['user.group_permissions']);

    $calculated_permissions = $this->groupPermissionCalculator->calculatePermissions($account);
    $allowed_ids = $all_ids = [];
    foreach ($calculated_permissions->getItems() as $item) {
      $all_ids[$item->getScope()][] = $item->getIdentifier();
      if ($item->hasPermission($permission)) {
        $allowed_ids[$item->getScope()][] = $item->getIdentifier();
      }
    }

    // If no group type or group gave access, we deny access altogether.
    if (empty($allowed_ids)) {
      $conditions->alwaysFalse();
      return $conditions;
    }

    // Add the allowed group types to the query (if any).
    if (!empty($allowed_ids[CGPII::SCOPE_GROUP_TYPE])) {
      $sub_condition = new ConditionGroup();
      $sub_condition->addCondition('type', $allowed_ids[CGPII::SCOPE_GROUP_TYPE]);

      // If the user had memberships, we need to make sure they are excluded
      // from group type based matches as the memberships' permissions take
      // precedence.
      if (!empty($all_ids[CGPII::SCOPE_GROUP])) {
        $sub_condition->addCondition('id', $all_ids[CGPII::SCOPE_GROUP], 'NOT IN');
      }

      $conditions->addCondition($sub_condition);
    }

    // Add the memberships with access to the query (if any).
    if (!empty($allowed_ids[CGPII::SCOPE_GROUP])) {
      $conditions->addCondition('id', $allowed_ids[CGPII::SCOPE_GROUP]);
    }

    return $conditions;
  }

}
