<?php

namespace Drupal\group_country_field\Routing;

use Drupal\Core\Routing\RouteSubscriberBase;
use Drupal\group_country_field\Access\AiTranslateLanguageAccess;
use Symfony\Component\Routing\RouteCollection;

/**
 * Adds a language check to the AI translation route.
 */
class RouteSubscriber extends RouteSubscriberBase {

  /**
   * {@inheritdoc}
   */
  protected function alterRoutes(RouteCollection $collection) {
    if ($route = $collection->get('ai_translate.translate_content')) {
      $route->setRequirement('_custom_access', AiTranslateLanguageAccess::class . '::access');
    }
  }

}
