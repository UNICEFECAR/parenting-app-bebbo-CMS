<?php

namespace Drupal\symfony_mailer;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;

/**
 * Provides the mailer transport plugin manager.
 */
class TransportManager extends DefaultPluginManager {

  /**
   * Constructs a RecipientHandlerManager object.
   *
   * @param \Traversable $namespaces
   *   An object that implements \Traversable which contains the root paths
   *   keyed by the corresponding namespace to look for plugin implementations.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache_backend
   *   Cache backend instance to use.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler to invoke the alter hook with.
   */
  public function __construct(\Traversable $namespaces, CacheBackendInterface $cache_backend, ModuleHandlerInterface $module_handler) {
    parent::__construct('Plugin/MailerTransport', $namespaces, $module_handler, 'Drupal\symfony_mailer\TransportPluginInterface', 'Drupal\symfony_mailer\Annotation\MailerTransport');
    $this->setCacheBackend($cache_backend, 'symfony_mailer_transport_plugins');
    $this->alterInfo('mailer_transport_info');
  }

}
