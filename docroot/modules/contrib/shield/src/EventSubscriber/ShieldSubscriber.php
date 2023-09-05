<?php

namespace Drupal\shield\EventSubscriber;

use Drupal\Core\Cache\CacheableResponseInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Listen for responses to add shield header and cache tag.
 */
class ShieldSubscriber implements EventSubscriberInterface {

  /**
   * The shield.settings configuration.
   *
   * @var \Drupal\Core\Config\Config
   */
  protected $config;

  /**
   * Constructs a new event listener.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   */
  public function __construct(ConfigFactoryInterface $config_factory) {
    $this->config = $config_factory->get('shield.settings');
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return [KernelEvents::RESPONSE => ['onResponse', 10]];
  }

  /**
   * Add shield header and cache tag.
   *
   * @param \Symfony\Component\HttpKernel\Event\ResponseEvent $event
   *   The response event.
   */
  public function onResponse(ResponseEvent $event) {
    $response = $event->getResponse();

    // If configured, add the debug header calculated in ShieldMiddleware.
    if ($this->config->get('debug_header') && $event->getRequest()->headers->has('X-Shield-Status')) {
      $response->headers->set('X-Shield-Status', $event->getRequest()->headers->get('X-Shield-Status'));
    }

    // Add the config:shield.settings cache tag to the response.
    if (!$response instanceof CacheableResponseInterface) {
      return;
    }
    $response->addCacheableDependency($this->config);
  }

}
