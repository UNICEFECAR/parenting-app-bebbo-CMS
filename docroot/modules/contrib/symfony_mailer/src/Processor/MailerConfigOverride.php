<?php

namespace Drupal\symfony_mailer\Processor;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Config\ConfigFactoryOverrideInterface;
use Drupal\Core\Config\StorageInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;

/**
 * Symfony Mailer configuration override.
 */
class MailerConfigOverride implements ConfigFactoryOverrideInterface {

  /**
   * Whether cache has been built.
   *
   * @var bool
   */
  protected $builtCache = FALSE;

  /**
   * Array of config overrides.
   *
   * As required by ConfigFactoryOverrideInterface::loadOverrides().
   *
   * @var array
   */
  protected $configOverrides = [];

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * Constructs the MailerConfigOverride object.
   *
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   */
  public function __construct(ModuleHandlerInterface $module_handler) {
    $this->moduleHandler = $module_handler;
  }

  /**
   * {@inheritdoc}
   */
  public function loadOverrides($names) {
    $this->buildCache();
    return array_intersect_key($this->configOverrides, array_flip($names));
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheSuffix() {
    return 'MailerConfigOverride';
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheableMetadata($name) {
    return new CacheableMetadata();
  }

  /**
   * {@inheritdoc}
   */
  public function createConfigObject($name, $collection = StorageInterface::DEFAULT_COLLECTION) {
    return NULL;
  }

  /**
   * Build cache of config overrides.
   */
  protected function buildCache() {
    if (!$this->builtCache && $this->moduleHandler->isLoaded()) {
      // Getting the definitions can cause reading of config which triggers
      // `loadOverrides()` to call this function. Protect against an infinite
      // loop by marking the cache as built before starting.
      $this->builtCache = TRUE;

      // We cannot use dependency injection because that creates a circular
      // dependency.
      /** @var \Drupal\symfony_mailer\Processor\EmailBuilderManagerInterface $builderManager */
      $builderManager = \Drupal::service('plugin.manager.email_builder');

      foreach ($builderManager->getDefinitions() as $definition) {
        // During upgrade to 1.3.x, this function can get called before the
        // updated annotation that sets 'config_overrides' to [] has been read.
        // Add in defaulting here.
        $this->configOverrides = array_merge($this->configOverrides, $definition['config_overrides'] ?? []);
      }
    }
  }

}
