<?php

namespace Drupal\pb_custom_field;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceProviderBase;
use Drupal\pb_custom_field\Plugin\Group\RelationHandler\BebboMembershipAccessControl;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Swaps the membership access-control relation handler.
 */
class PbCustomFieldServiceProvider extends ServiceProviderBase {

  /**
   * {@inheritdoc}
   */
  public function alter(ContainerBuilder $container) {
    if ($container->hasDefinition('group.relation_handler.access_control.group_membership')) {
      $definition = $container->getDefinition('group.relation_handler.access_control.group_membership');
      // Wrap Group's handler: our class decorates the original class, which
      // itself decorates the default handler.
      $container->register('pb_custom_field.membership_access_inner', $definition->getClass())
        ->setArguments($definition->getArguments())
        ->setShared(FALSE);
      $definition->setClass(BebboMembershipAccessControl::class)
        ->setArguments([new Reference('pb_custom_field.membership_access_inner')]);
    }
  }

}
