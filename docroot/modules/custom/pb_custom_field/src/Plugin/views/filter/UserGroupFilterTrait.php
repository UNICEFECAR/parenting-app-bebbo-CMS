<?php

namespace Drupal\pb_custom_field\Plugin\views\filter;

use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Query\SelectInterface;
use Drupal\views\Plugin\views\query\Sql;

/**
 * Filters users by group membership without joining the membership table.
 *
 * A relationship onto group_relationship_field_data multiplies the result set,
 * because a user can hold several memberships and Views selects the related
 * entity IDs, which the query's distinct option therefore cannot collapse.
 * These filters keep the listing at one row per user by matching the
 * memberships in a subquery instead.
 */
trait UserGroupFilterTrait {

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected Connection $database;

  /**
   * Sets the database connection.
   *
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   */
  public function setDatabase(Connection $database): void {
    $this->database = $database;
  }

  /**
   * Builds a subquery returning the user IDs of the matching memberships.
   *
   * @return \Drupal\Core\Database\Query\SelectInterface
   *   Select query on the membership table, joined to the group data table.
   */
  protected function membershipSubquery(): SelectInterface {
    $subquery = $this->database->select('group_relationship_field_data', 'pb_gr');
    $subquery->addField('pb_gr', 'entity_id');
    $subquery->join('groups_field_data', 'pb_g', 'pb_g.id = pb_gr.gid');
    $subquery->condition('pb_gr.plugin_id', 'group_membership');
    $subquery->condition('pb_g.default_langcode', 1);
    return $subquery;
  }

  /**
   * Restricts the view to the users the subquery does or does not return.
   *
   * @param \Drupal\Core\Database\Query\SelectInterface $subquery
   *   The membership subquery.
   * @param bool $match
   *   TRUE to keep the users it returns, FALSE to keep the ones it does not.
   */
  protected function addSubqueryCondition(SelectInterface $subquery, bool $match = TRUE): void {
    assert($this->query instanceof Sql);
    $this->ensureMyTable();
    $this->query->addWhere(
      $this->options['group'],
      "$this->tableAlias.uid",
      $subquery,
      $match ? 'IN' : 'NOT IN'
    );
  }

}
