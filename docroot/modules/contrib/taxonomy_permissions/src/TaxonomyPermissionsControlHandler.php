<?php

namespace Drupal\taxonomy_permissions;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\taxonomy\TermAccessControlHandler;

/**
 * Defines the access control handler for the taxonomy term entity type.
 *
 * @see \Drupal\taxonomy\Entity\Term
 */
class TaxonomyPermissionsControlHandler extends TermAccessControlHandler {

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account) {
    // Same as core.
    if ($account->hasPermission('administer taxonomy')) {
      return AccessResult::allowed()->cachePerPermissions();
    }
    // Only check for the new view permission.
    switch ($operation) {
      case 'view':
        $access_result = AccessResult::allowedIf($account->hasPermission("view terms in {$entity->bundle()}") && $entity->isPublished())
          ->cachePerPermissions()
          ->addCacheableDependency($entity);
        if (!$access_result->isAllowed()) {
          $access_result->setReason("The 'view terms in {$entity->bundle()}' permission is required and the taxonomy term must be published.");
        }
        return $access_result;

      default:
        // Otherwise let core's taxonomy control handler kick in.
        return parent::checkAccess($entity, $operation, $account);
    }
  }

}
