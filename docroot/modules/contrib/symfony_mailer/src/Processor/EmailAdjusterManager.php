<?php

namespace Drupal\symfony_mailer\Processor;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;
use Drupal\symfony_mailer\EmailInterface;
use Drupal\symfony_mailer\Entity\MailerPolicy;

/**
 * Provides the email adjuster plugin manager.
 */
class EmailAdjusterManager extends DefaultPluginManager implements EmailAdjusterManagerInterface {

  /**
   * Constructs the EmailAdjusterManager object.
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
    parent::__construct('Plugin/EmailAdjuster', $namespaces, $module_handler, 'Drupal\symfony_mailer\Processor\EmailAdjusterInterface', 'Drupal\symfony_mailer\Annotation\EmailAdjuster');
    $this->setCacheBackend($cache_backend, 'symfony_mailer_adjuster_plugins');
    $this->alterInfo('mailer_adjuster_info');
  }

  /**
   * {@inheritdoc}
   */
  public function applyPolicy(EmailInterface $email) {
    $suggestions = $email->getSuggestions('', '.');
    $policy_config = MailerPolicy::loadInheritedConfig(end($suggestions));

    // Include automatic adjusters.
    foreach ($this->getDefinitions() as $id => $definition) {
      if ($definition['automatic']) {
        $policy_config[$id] = [];
      }
    }

    // Add adjusters.
    foreach ($policy_config as $plugin_id => $config) {
      if ($this->hasDefinition($plugin_id)) {
        $this->createInstance($plugin_id, $config)->init($email);
      }
    }
  }

}
