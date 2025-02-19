<?php

namespace Drupal\taxonomy_access_fix;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityHandlerInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\taxonomy\VocabularyAccessControlHandler as OriginalVocabularyAccessControlHandler;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Extends access control for Taxonomy Vocabulary entities.
 */
class VocabularyAccessControlHandler extends OriginalVocabularyAccessControlHandler implements EntityHandlerInterface {

  use ReasonTrait;

  /**
   * {@inheritdoc}
   */
  protected $viewLabelOperation = TRUE;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a new VocabularyAccessControlHandler instance.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type definition.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(EntityTypeInterface $entity_type, EntityTypeManagerInterface $entity_type_manager) {
    parent::__construct($entity_type);
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type) {
    return new static($entity_type, $container->get('entity_type.manager'));
  }

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account) {
    if (!in_array($operation, [
      'reorder_terms',
      'reset all weights',
      'view label',
    ], TRUE)) {
      $access_result = parent::checkAccess($entity, $operation, $account);
      if (in_array($operation, ['access taxonomy overview', 'view'])) {
        $taxonomy_term_access_control_handler = $this->entityTypeManager->getAccessControlHandler('taxonomy_term');
        $access_result_operation = AccessResult::allowedIf($taxonomy_term_access_control_handler->createAccess($entity->id(), $account))
          ->orIf(AccessResult::allowedIf($account->hasPermission('delete terms in ' . $entity->id())))
          ->orIf(AccessResult::allowedIf($account->hasPermission('delete any term')))
          ->orIf(AccessResult::allowedIf($account->hasPermission('edit terms in ' . $entity->id())))
          ->orIf(AccessResult::allowedIf($account->hasPermission('update any term')))
          ->orIf($this->checkAccess($entity, 'reorder_terms', $account))
          ->orIf($this->checkAccess($entity, 'reset all weights', $account));
        /** @var \Drupal\Core\Access\AccessResult $access_result */
        $access_result = $access_result
          ->andIf($access_result_operation);
        $access_result->cachePerPermissions()
          ->addCacheableDependency($entity);
        if (!$access_result->isAllowed()) {
          /** @var \Drupal\Core\Access\AccessResultReasonInterface $access_result */
          $access_result->setReason("The 'access taxonomy overview' and one of the 'create terms in {$entity->id()}', 'create any term', 'delete terms in {$entity->id()}', 'delete any term', 'edit terms in {$entity->id()}', 'update any term', 'reorder terms in {$entity->id()}', 'reorder terms in any vocabulary', 'reset {$entity->id()}', 'reset any vocabulary' permissions OR the 'administer taxonomy' permission are required.");
        }
      }
      return $access_result;
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
    if ($operation === 'view label' && !$access_result->isAllowed()) {
      $access_result = $access_result->orIf($this->checkAccess($entity, 'view', $account));
    }
    if (!$access_result->isAllowed()) {
      /** @var \Drupal\Core\Access\AccessResultReasonInterface $access_result */
      $access_result->setReason($this->getReason($permissions));
    }
    return $access_result;
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
    /** @var \Drupal\taxonomy\VocabularyInterface $entity */
    if ($operation === 'reorder_terms') {
      return [
        "reorder terms in {$entity->id()}",
        'reorder terms in any vocabulary',
      ];
    }
    elseif ($operation === 'reset all weights') {
      return [
        "reset {$entity->id()}",
        "reset any vocabulary",
      ];
    }
    elseif ($operation === 'view label') {
      return [
        "view vocabulary name of {$entity->id()}",
        'view any vocabulary name',
      ];
    }
    return [];
  }

}
