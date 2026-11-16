<?php

namespace Drupal\bebbo_api_security\Routing;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Routing\RouteSubscriberBase;
use Symfony\Component\Routing\RouteCollection;

/**
 * Keeps device JWTs separate from Simple OAuth's Drupal user tokens.
 */
class DeviceTokenRouteSubscriber extends RouteSubscriberBase {

  public function __construct(protected readonly ConfigFactoryInterface $configFactory) {}

  /**
   * {@inheritdoc}
   */
  protected function alterRoutes(RouteCollection $collection): void {
    $config = $this->configFactory->get('bebbo_api_security.settings');
    $protected = array_filter(array_map('trim', explode("\n", $config->get('protected_api_patterns') ?? '')));
    $excluded = array_filter(array_map('trim', explode("\n", $config->get('excluded_api_patterns') ?? '')));
    foreach ($collection as $route) {
      $path = $route->getPath();
      if (str_starts_with($path, '/api/security/')) {
        $route->setOption('_oauth_skip_auth', TRUE);
        continue;
      }
      foreach ($excluded as $pattern) {
        if (preg_match('#^' . $pattern . '#', $path)) {
          continue 2;
        }
      }
      foreach ($protected as $pattern) {
        if (preg_match('#^' . $pattern . '#', $path)) {
          $route->setOption('_oauth_skip_auth', TRUE);
          break;
        }
      }
    }
  }

}
