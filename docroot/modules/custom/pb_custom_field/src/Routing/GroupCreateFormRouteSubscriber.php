<?php

namespace Drupal\pb_custom_field\Routing;

use Drupal\Core\Routing\RouteSubscriberBase;
use Symfony\Component\Routing\RouteCollection;

/**
 * Swaps the relationship create-form controller for the membership wizard.
 */
class GroupCreateFormRouteSubscriber extends RouteSubscriberBase {

  /**
   * {@inheritdoc}
   */
  protected function alterRoutes(RouteCollection $collection): void {
    if ($route = $collection->get('entity.group_relationship.create_form')) {
      $route->setDefault('_controller', '\Drupal\pb_custom_field\Controller\GroupMembershipCreateController::createForm');
    }
  }

}
