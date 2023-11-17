<?php

namespace Drupal\symfony_mailer;

use Symfony\Component\Mailer\Transport\TransportFactoryInterface;

/**
 * Defines the interface for the Transport Factory Manager.
 */
interface TransportFactoryManagerInterface {

  /**
   * Adds a transport factory.
   *
   * @param \Symfony\Component\Mailer\Transport\TransportFactoryInterface $factory
   *   The transport factory.
   */
  public function addFactory(TransportFactoryInterface $factory);

  /**
   * Gets all available transport factories.
   *
   * @return Symfony\Component\Mailer\Transport\TransportFactoryInterface[]
   *   The transport factories.
   */
  public function getFactories();

}
