<?php

namespace Drupal\pb_custom_field\Plugin\views\filter;

use Drupal\views\Attribute\ViewsFilter;
use Drupal\views\Plugin\views\filter\NumericFilter;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Filters users on the ID of a group they belong to.
 *
 * Replaces a group ID filter reached through a reverse relationship, which
 * duplicates users that belong to more than one group. See
 * \Drupal\pb_custom_field\Plugin\views\filter\UserGroupFilterTrait.
 */
#[ViewsFilter("pb_user_group_id")]
class UserGroupId extends NumericFilter {

  use UserGroupFilterTrait;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->setDatabase($container->get('database'));
    return $instance;
  }

  /**
   * {@inheritdoc}
   *
   * Only the operators the subquery implements are offered.
   */
  public function operators() {
    return array_intersect_key(parent::operators(), array_flip([
      '=',
      '!=',
      'in',
      'not in',
      'empty',
      'not empty',
    ]));
  }

  /**
   * {@inheritdoc}
   */
  public function query() {
    $subquery = $this->membershipSubquery();

    switch ($this->operator) {
      case 'empty':
        $this->addSubqueryCondition($subquery, FALSE);
        return;

      case 'not empty':
        $this->addSubqueryCondition($subquery);
        return;

      case 'in':
      case 'not in':
        $gids = array_filter(array_map('intval', (array) $this->value));
        if (!$gids) {
          return;
        }
        $subquery->condition('pb_gr.gid', $gids, 'IN');
        break;

      default:
        $subquery->condition('pb_gr.gid', (int) ($this->value['value'] ?? 0));
        break;
    }

    $this->addSubqueryCondition($subquery, !in_array($this->operator, ['!=', 'not in'], TRUE));
  }

}
