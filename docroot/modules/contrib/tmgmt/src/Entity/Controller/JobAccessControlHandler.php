<?php

namespace Drupal\tmgmt\Entity\Controller;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Access control handler for the job entity.
 *
 * @see \Drupal\tmgmt\Entity\Job.
 */
class JobAccessControlHandler extends EntityAccessControlHandler {

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account) {
    /** @var \Drupal\tmgmt\JobInterface $entity */
    if ($account->hasPermission('administer tmgmt')) {
      // Administrators can do everything.
      return AccessResult::allowed()->cachePerPermissions();
    }

    switch ($operation) {
      case 'view':
      case 'update':
        return AccessResult::allowedIfHasPermission($account, 'create translation jobs')->orIf(AccessResult::allowedIfHasPermission($account, 'accept translation jobs'));
        break;

      case 'delete':
        return AccessResult::allowedIfHasPermission($account, 'delete translation jobs')->andIf(AccessResult::allowedIf(!$entity->isActive()))->addCacheableDependency($entity);
        break;

      // Custom operations.
      case 'submit':
        return AccessResult::allowedIfHasPermission($account, 'submit translation jobs');
        break;

      case 'accept':
        return AccessResult::allowedIfHasPermission($account, 'accept translation jobs');
        break;

      case 'abort':
      case 'resubmit':
        return AccessResult::allowedIfHasPermission($account, 'submit translation jobs');
        break;
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function checkCreateAccess(AccountInterface $account, array $context, $entity_bundle = NULL) {
    return AccessResult::allowedIfHasPermission($account, 'create translation jobs')->orIf(AccessResult::allowedIfHasPermission($account, 'administer tmgmt'));
  }


}
