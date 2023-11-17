<?php

namespace Drupal\symfony_mailer\Processor;

use Drupal\Core\Plugin\PluginBase;

/**
 * Defines the base class for EmailProcessorInterface implementations.
 *
 * This base class is for plug-ins. Use EmailProcessorCustomBase for custom
 * processors.
 */
abstract class EmailProcessorBase extends PluginBase implements EmailProcessorInterface {

  use EmailProcessorTrait;

  /**
   * {@inheritdoc}
   */
  public function getWeight(int $phase) {
    $weight = $this->getPluginDefinition()['weight'] ?? static::DEFAULT_WEIGHT;
    return is_array($weight) ? $weight[$phase] : $weight;
  }

  /**
   * {@inheritdoc}
   */
  public function getId() {
    return static::class;
  }

}
