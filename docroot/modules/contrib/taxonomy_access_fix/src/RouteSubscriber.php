<?php

namespace Drupal\taxonomy_access_fix;

use Drupal\Core\Routing\RouteSubscriberBase;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

/**
 * Alters access checks of routes provided by Taxonomy module.
 */
class RouteSubscriber extends RouteSubscriberBase {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  protected function alterRoutes(RouteCollection $collection) {
    // Use access control handler to check for access to vocabulary reset form.
    if ($route = $collection->get('entity.taxonomy_vocabulary.reset_form')) {
      $new_requirements = [
        '_entity_access' => 'taxonomy_vocabulary.reset all weights',
      ];
      if ($route->getRequirements() === $new_requirements) {
        // Running on Drupal 10.1 or later.
        return;
      }
      // Running on Drupal 10.0 or 9.
      $this->verifyRequirements($route, [
        '_permission' => 'administer taxonomy',
      ]);
      $route->setRequirements($new_requirements);
    }
  }

  /**
   * Verifies that a route has the expected requirements.
   *
   * @param \Symfony\Component\Routing\Route $route
   *   Route to check.
   * @param array $expected_requirements
   *   Expected requirements.
   *
   * @throws \LogicException
   *   When unexpected requirements are in use.
   */
  protected function verifyRequirements(Route $route, array $expected_requirements) {
    if ($route->getRequirements() !== $expected_requirements) {
      // If unexpected requirements are in use, we can't guarantee that our
      // access control handler will return correct access results.
      throw new \LogicException($this->t('Unexpected requirements of @route_path route. This might be due to an unexpected change in Drupal Core or due to a conflict with another contributed or custom module in use.', [
        '@route_path' => $route->getPath(),
      ]));
    }
  }

}
