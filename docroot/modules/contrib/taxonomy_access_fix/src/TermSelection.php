<?php

namespace Drupal\taxonomy_access_fix;

use Drupal\Component\Utility\Html;
use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Query\SelectInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\taxonomy\Plugin\EntityReferenceSelection\TermSelection as OriginalTermSelection;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Extends the original term selection plugin to check our permissions.
 */
class TermSelection extends OriginalTermSelection {

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $connection;

  /**
   * Constructs a new TermSelection object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler service.
   * @param \Drupal\Core\Session\AccountInterface $current_user
   *   The current user.
   * @param \Drupal\Core\Database\Connection $connection
   *   The database connection.
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entity_field_manager
   *   The entity field manager.
   * @param \Drupal\Core\Entity\EntityTypeBundleInfoInterface $entity_type_bundle_info
   *   The entity type bundle info service.
   * @param \Drupal\Core\Entity\EntityRepositoryInterface $entity_repository
   *   The entity repository.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entity_type_manager, ModuleHandlerInterface $module_handler, AccountInterface $current_user, Connection $connection, EntityFieldManagerInterface $entity_field_manager, EntityTypeBundleInfoInterface $entity_type_bundle_info, EntityRepositoryInterface $entity_repository) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $entity_type_manager, $module_handler, $current_user, $entity_field_manager, $entity_type_bundle_info, $entity_repository);
    $this->connection = $connection;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('module_handler'),
      $container->get('current_user'),
      $container->get('database'),
      $container->get('entity_field.manager'),
      $container->get('entity_type.bundle.info'),
      $container->get('entity.repository')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getReferenceableEntities($match = NULL, $match_operator = 'CONTAINS', $limit = 0) {
    if ($match || $limit) {
      return parent::getReferenceableEntities($match, $match_operator, $limit);
    }

    $options = [];

    $bundles = $this->getConfiguredBundles();

    $unpublished_terms = [];
    foreach ($bundles as $bundle) {
      if ($vocabulary = Vocabulary::load($bundle)) {
        /** @var \Drupal\taxonomy\TermInterface[] $terms */
        if ($terms = $this->entityTypeManager->getStorage('taxonomy_term')->loadTree($vocabulary->id(), 0, NULL, TRUE)) {
          foreach ($terms as $term) {
            if (!$term->access('select') || in_array($term->parent->target_id, $unpublished_terms)) {
              $unpublished_terms[] = $term->id();
              continue;
            }
            $options[$vocabulary->id()][$term->id()] = str_repeat('-', $term->depth) . Html::escape($this->entityRepository->getTranslationFromContext($term)->label());
          }
        }
      }
    }

    return $options;
  }

  /**
   * Gets configured bundle names for this term selection.
   *
   * @return string[]
   *   Configured bundle names or all bundle names, if none configured.
   */
  protected function getConfiguredBundles(): array {
    $bundles = $this->entityTypeBundleInfo->getBundleInfo('taxonomy_term');
    return $this->getConfiguration()['target_bundles'] ?: array_keys($bundles);
  }

  /**
   * {@inheritdoc}
   */
  public function entityQueryAlter(SelectInterface $query) {
    $conditions = $query->conditions();

    // If the query has a condition for the "status" field, this user doesn't
    // have permission to "administer taxonomy". We need to remove that
    // condition and add our own conditions based on our own permissions.
    $remove_index = -1;
    $status_table = $this
      ->entityTypeManager
      ->getStorage('taxonomy_term')
      ->getTableMapping()
      ->getFieldTableName('status');
    $status_field = $status_table . '.status';
    foreach ($conditions as $index => $condition) {
      if (!is_array($condition)) {
        continue;
      }
      if ($condition['field'] === $status_field && $condition['value'] === 1 && $condition['operator'] === '=') {
        $remove_index = $index;
        break;
      }
    }
    if ($remove_index < 0) {
      // This user has permission to "administer taxonomy".
      return;
    }
    unset($query->conditions()[$remove_index]);

    $hasAnyPublished = $this->currentUser->hasPermission('select any term');
    $hasAnyUnpublished = $this->currentUser->hasPermission('select any unpublished term');
    if ($hasAnyPublished && $hasAnyUnpublished) {
      // This user has access to select any term.
      return;
    }

    // Prepare new per-vocabulary conditions.
    $or = $this->connection
      ->condition('OR');

    $bundle_table = $this
      ->entityTypeManager
      ->getStorage('taxonomy_term')
      ->getTableMapping()
      ->getFieldTableName('vid');
    $bundle_field = $status_table . '.vid';
    $bundles = $this->getConfiguredBundles();
    foreach ($bundles as $bundle) {
      $hasPublished = $hasAnyPublished || $this->currentUser->hasPermission("select terms in {$bundle}");
      $hasUnpublished = $hasAnyUnpublished || $this->currentUser->hasPermission("select unpublished terms in {$bundle}");
      if (!$hasPublished && !$hasUnpublished) {
        continue;
      }
      if ($hasPublished && $hasUnpublished) {
        $and = $this->connection->condition('AND');
        $and->condition($bundle_field, $bundle, '=');
        $or->condition($and);
        continue;
      }
      if ($hasPublished) {
        $and = $this->connection->condition('AND');
        $and->condition($bundle_field, $bundle, '=');
        $and->condition($status_field, 1, '=');
        $or->condition($and);
        continue;
      }
      if ($hasUnpublished) {
        $and = $this->connection->condition('AND');
        $and->condition($bundle_field, $bundle, '=');
        $and->condition($status_field, 0, '=');
        $or->condition($and);
        continue;
      }
    }

    if (count($or) === 0) {
      // No per-vocabulary conditions have been added. This user has no access
      // at all. Add a condition that won't return any results.
      $and = $this->connection->condition('AND');
      $and->condition($bundle_field, '', '=');
      $or->condition($and);
    }

    // Add per-vocabulary conditions to query.
    $query->condition($or);
  }

  /**
   * {@inheritdoc}
   */
  public function validateReferenceableNewEntities(array $entities) {
    $grandparent = new \ReflectionMethod(get_parent_class(get_parent_class($this)), 'validateReferenceableNewEntities');
    $entities = $grandparent->invoke($this, $entities);

    if (!$this->currentUser->hasPermission('administer taxonomy')) {
      $entities = array_filter($entities, function ($term) {
        /** @var \Drupal\taxonomy\TermInterface $term */
        return $term->access('select');
      });
    }

    return $entities;
  }

}
