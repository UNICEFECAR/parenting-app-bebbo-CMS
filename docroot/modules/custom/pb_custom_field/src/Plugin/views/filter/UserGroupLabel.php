<?php

namespace Drupal\pb_custom_field\Plugin\views\filter;

use Drupal\views\Attribute\ViewsFilter;
use Drupal\views\Plugin\views\filter\StringFilter;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Filters users on the name of a group they belong to.
 *
 * Replaces a group label filter reached through a reverse relationship, which
 * duplicates users that belong to more than one group. See
 * \Drupal\pb_custom_field\Plugin\views\filter\UserGroupFilterTrait.
 */
#[ViewsFilter("pb_user_group_label")]
class UserGroupLabel extends StringFilter {

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
      'contains',
      'starts',
      'empty',
      'not empty',
    ]));
  }

  /**
   * {@inheritdoc}
   */
  public function query() {
    $subquery = $this->membershipSubquery();
    $value = (string) $this->value;

    switch ($this->operator) {
      case 'empty':
        $this->addSubqueryCondition($subquery, FALSE);
        return;

      case 'not empty':
        $this->addSubqueryCondition($subquery);
        return;

      case 'contains':
        $subquery->condition('pb_g.label', '%' . $this->database->escapeLike($value) . '%', 'LIKE');
        break;

      case 'starts':
        $subquery->condition('pb_g.label', $this->database->escapeLike($value) . '%', 'LIKE');
        break;

      default:
        $subquery->condition('pb_g.label', $value);
        break;
    }

    $this->addSubqueryCondition($subquery, $this->operator !== '!=');
  }

}
