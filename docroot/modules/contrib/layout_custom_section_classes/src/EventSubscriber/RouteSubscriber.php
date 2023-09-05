<?php

namespace Drupal\layout_custom_section_classes\EventSubscriber;

use Drupal\Core\Routing\RouteSubscriberBase;
use Symfony\Component\Routing\RouteCollection;

/**
 * Alters core ConfigureSectionForm to add a getter for layout object.
 */
class RouteSubscriber extends RouteSubscriberBase {

  /**
   * {@inheritdoc}
   */
  protected function alterRoutes(RouteCollection $collection) {
    // Needed until https://www.drupal.org/i/3044117 is in.
    $configureSectionRoute = $collection->get('layout_builder.configure_section');
    if ($configureSectionRoute) {
      $configureSectionRoute->setDefault('_form', '\Drupal\layout_custom_section_classes\Form\ConfigureSectionForm');
    }
  }

}
