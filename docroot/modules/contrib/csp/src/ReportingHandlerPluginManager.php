<?php

namespace Drupal\csp;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;

/**
 * Plugin Manager for CSP Reporting Handlers.
 */
class ReportingHandlerPluginManager extends DefaultPluginManager {

  /**
   * Constructs a RequestHandlerPluginManager object.
   *
   * @param \Traversable $namespaces
   *   An object that implements \Traversable which contains the root paths
   *   keyed by the corresponding namespace to look for plugin implementations.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache_backend
   *   The cache backend.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   */
  public function __construct(
    \Traversable $namespaces,
    CacheBackendInterface $cache_backend,
    ModuleHandlerInterface $module_handler,
  ) {
    parent::__construct(
      'Plugin/CspReportingHandler',
      $namespaces,
      $module_handler,
      'Drupal\csp\Plugin\ReportingHandlerInterface',
      'Drupal\csp\Annotation\CspReportingHandler'
    );

    $this->setCacheBackend($cache_backend, 'csp_reporting_handler_plugins');
  }

}
