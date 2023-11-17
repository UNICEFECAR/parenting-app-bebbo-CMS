<?php

namespace Drupal\symfony_mailer\Processor;

use Drupal\Core\Plugin\DefaultLazyPluginCollection;

/**
 * A collection of email adjusters.
 */
class AdjusterPluginCollection extends DefaultLazyPluginCollection {

  /**
   * {@inheritdoc}
   */
  protected function initializePlugin($instance_id) {
    $configuration = $this->configurations[$instance_id];
    $this->set($instance_id, $this->manager->createInstance($instance_id, $configuration));
  }

  /**
   * Provides uasort() callback to sort plugins.
   */
  public function sortHelper($aID, $bID) {
    $a = $this->get($aID);
    $b = $this->get($bID);
    return strnatcasecmp($a->getPluginDefinition()['label'], $b->getPluginDefinition()['label']);
  }

}
