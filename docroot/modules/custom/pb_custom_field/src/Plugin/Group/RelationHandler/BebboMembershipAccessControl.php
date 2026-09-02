<?php

namespace Drupal\pb_custom_field\Plugin\Group\RelationHandler;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\group\Plugin\Group\RelationHandler\AccessControlInterface;
use Drupal\group\Plugin\Group\RelationHandler\AccessControlTrait;

/**
 * Membership access control with uid-1 and self guards.
 *
 * Decorates Group's GroupMembershipAccessControl; group-level permissions
 * must never grant a group admin access to uid 1 or to their own account
 * (port of GroupMembershipContentAccessControlHandler, patch #2949408).
 */
class BebboMembershipAccessControl implements AccessControlInterface {

  use AccessControlTrait;

  /**
   * Constructs a new BebboMembershipAccessControl.
   *
   * @param \Drupal\group\Plugin\Group\RelationHandler\AccessControlInterface $parent
   *   The parent access control handler.
   */
  public function __construct(AccessControlInterface $parent) {
    $this->parent = $parent;
  }

  /**
   * {@inheritdoc}
   */
  public function entityAccess(EntityInterface $entity, $operation, AccountInterface $account, $return_as_object = FALSE) {
    if ((int) $entity->id() === 1 || (int) $entity->id() === (int) $account->id()) {
      $result = AccessResult::neutral();
      return $return_as_object ? $result : FALSE;
    }
    return $this->parent->entityAccess($entity, $operation, $account, $return_as_object);
  }

}
