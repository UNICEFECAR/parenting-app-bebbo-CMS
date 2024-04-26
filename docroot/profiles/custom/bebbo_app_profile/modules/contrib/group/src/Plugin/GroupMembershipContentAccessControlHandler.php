<?php

namespace Drupal\group\Plugin;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Provides access control specific to group membership and related users.
 */
class GroupMembershipContentAccessControlHandler extends GroupContentAccessControlHandler {

  /**
   * {@inheritdoc}
   */
  public function entityAccess(EntityInterface $entity, $operation, AccountInterface $account, $return_as_object = FALSE) {
    if ($entity->id() == 1) {
      return $return_as_object ? AccessResult::neutral() : FALSE;
    }

    // Bypass own user.
    if ($entity->id() == $account->id()) {
      return $return_as_object ? AccessResult::neutral() : FALSE;
    }

    /** @var \Drupal\group\Entity\Storage\GroupContentStorageInterface $storage */
    $storage = $this->entityTypeManager->getStorage('group_content');
    $group_contents = $storage->loadByEntity($entity);

    foreach ($group_contents as $group_content) {
      if ($operation == 'view') {
        $access = AccessResult::allowedIf($group_content->getGroup()->hasPermission('view group_membership entity', $account));
        if ($access->isAllowed()) {
          return $return_as_object ? $access : TRUE;
        }
        continue;
      }

      // Allow if can edit any.
      $allowed = $group_content->getGroup()->hasPermission($operation . ' any group_membership entity', $account);
      // Allow if can edit own.
      if (!$allowed  && $group_content->getOwnerId() == $account->id()) {
        $allowed = $group_content->getGroup()->hasPermission($operation . ' own group_membership entity', $account);
      }
      if ($allowed) {
        return $return_as_object ? AccessResult::allowed() : TRUE;
      }
    }

    return $return_as_object ? AccessResult::neutral() : FALSE;
  }

}
