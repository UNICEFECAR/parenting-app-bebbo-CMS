<?php

namespace Drupal\pb_custom_field\Plugin\views\field;

use Drupal\Component\Utility\Html;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\CacheableDependencyInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\views\Attribute\ViewsField;
use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\Plugin\views\query\Sql;
use Drupal\views\ResultRow;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Lists every group a user belongs to inside a single row.
 *
 * The alternative - a reverse group_relationship relationship plus the group
 * label field - joins a one-to-many table into the result set, so a user in
 * two groups is listed twice. Enabling the query's distinct option does not
 * help there, because the duplicated rows differ in the group columns. This
 * handler keeps the listing at one row per user by reading the memberships
 * separately, after the query has run.
 *
 * Memberships for every row are fetched in a single query during preRender,
 * so the number of rows does not drive the number of queries.
 */
#[ViewsField("pb_user_groups")]
class UserGroups extends FieldPluginBase implements ContainerFactoryPluginInterface, CacheableDependencyInterface {

  /**
   * Group labels of the rendered rows, keyed by user ID.
   *
   * @var array
   */
  protected array $labels = [];

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected Connection $database;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The entity repository.
   *
   * @var \Drupal\Core\Entity\EntityRepositoryInterface
   */
  protected EntityRepositoryInterface $entityRepository;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = new static($configuration, $plugin_id, $plugin_definition);
    $instance->database = $container->get('database');
    $instance->entityTypeManager = $container->get('entity_type.manager');
    $instance->entityRepository = $container->get('entity.repository');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function query() {
    assert($this->query instanceof Sql);
    $this->ensureMyTable();
    $this->field_alias = $this->query->addField($this->tableAlias, $this->realField);
  }

  /**
   * {@inheritdoc}
   */
  public function clickSortable() {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function preRender(&$values) {
    $this->labels = [];

    $uids = [];
    foreach ($values as $row) {
      $uid = (int) $this->getValue($row);
      if ($uid) {
        $uids[$uid] = $uid;
      }
    }
    if (!$uids) {
      return;
    }

    $memberships = $this->database->select('group_relationship_field_data', 'gr')
      ->fields('gr', ['entity_id', 'gid'])
      ->condition('gr.plugin_id', 'group_membership')
      ->condition('gr.entity_id', $uids, 'IN')
      ->execute()
      ->fetchAll();
    if (!$memberships) {
      return;
    }

    $gids = array_unique(array_column($memberships, 'gid'));
    $groups = $this->entityTypeManager->getStorage('group')->loadMultiple($gids);

    foreach ($memberships as $membership) {
      $group = $groups[$membership->gid] ?? NULL;
      if (!$group) {
        continue;
      }
      $uid = (int) $membership->entity_id;
      $this->labels[$uid][$group->id()] = $this->entityRepository->getTranslationFromContext($group)->label();
    }

    foreach ($this->labels as &$labels) {
      natcasesort($labels);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function render(ResultRow $values) {
    $labels = $this->labels[(int) $this->getValue($values)] ?? [];
    if (!$labels) {
      return '';
    }

    return [
      '#markup' => implode(', ', array_map([Html::class, 'escape'], $labels)),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTags() {
    return ['group_relationship_list', 'group_list'];
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheContexts() {
    return ['languages:language_content'];
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheMaxAge() {
    return Cache::PERMANENT;
  }

}
