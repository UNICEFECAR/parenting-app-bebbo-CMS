<?php

namespace Drupal\taxonomy_access_fix;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\taxonomy\TermAccessControlHandler as OriginalTermAccessControlHandler;

/**
 * Extends Core's access control handler with a view permission by bundle.
 */
class TermAccessControlHandler extends OriginalTermAccessControlHandler {

  use ReasonTrait;

  /**
   * {@inheritdoc}
   */
  protected $viewLabelOperation = TRUE;

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account) {
    if (!in_array($operation, ['view', 'view label', 'select'], TRUE)) {
      if (in_array($operation, ['update', 'delete'], TRUE) && $account->hasPermission("{$operation} any term")) {
        return AccessResult::allowed()->cachePerPermissions();
      }
      return parent::checkAccess($entity, $operation, $account);
    }
    if ($account->hasPermission('administer taxonomy')) {
      return AccessResult::allowed()->cachePerPermissions();
    }
    $permissions = $this->getPermissions($entity, $operation);
    if (empty($permissions)) {
      throw new \LogicException('No permissions for requested operation. Requested operation is not supported.');
    }
    $access_result = AccessResult::allowedIfHasPermissions($account, $permissions, 'OR')
      ->cachePerPermissions()
      ->addCacheableDependency($entity);
    if (!$access_result->isAllowed()) {
      /** @var \Drupal\Core\Access\AccessResultReasonInterface $access_result */
      $access_result->setReason($this->getReason($permissions));
    }
    return $access_result;
  }

  /**
   * {@inheritdoc}
   */
  protected function checkCreateAccess(AccountInterface $account, array $context, $entity_bundle = NULL) {
    if ($account->hasPermission('create any term')) {
      return AccessResult::allowed()->cachePerPermissions();
    }
    return parent::checkCreateAccess($account, $context, $entity_bundle);
  }

  /**
   * {@inheritdoc}
   */
  protected function checkFieldAccess($operation, FieldDefinitionInterface $field_definition, AccountInterface $account, ?FieldItemListInterface $items = NULL) {
    if ($items !== NULL && $field_definition->getName() === $this->entityType->getKey('label')) {
      $entity = $items->getEntity();
      return $this->checkAccess($entity, 'view label', $account);
    }
    return parent::checkFieldAccess($operation, $field_definition, $account, $items);
  }

  /**
   * Gets permissions allowing an operation for an entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity to get permissions for.
   * @param string $operation
   *   The entity operation to get permissions for.
   *
   * @return string[]
   *   Permission names that allow the operation for the entity. If the array
   *   is empty, the requested operation is not supported.
   */
  protected function getPermissions(EntityInterface $entity, string $operation): array {
    /** @var \Drupal\taxonomy\TermInterface $entity */
    if (in_array($operation, ['view', 'select'], TRUE)) {
      return $entity->isPublished() ? [
        "{$operation} terms in {$entity->bundle()}",
        "{$operation} any term",
      ] : [
        "{$operation} unpublished terms in {$entity->bundle()}",
        "{$operation} any unpublished term",
      ];
    }
    elseif ($operation === 'view label') {
      return $entity->isPublished() ? [
        "view term names in {$entity->bundle()}",
        'view any term name',
      ] : [
        "view unpublished term names in {$entity->bundle()}",
        'view any unpublished term name',
      ];
    }
    return [];
  }

}
