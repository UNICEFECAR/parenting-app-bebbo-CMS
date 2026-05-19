<?php

namespace Drupal\bebbo_serializer\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Registers the bebbo_json format with Symfony's Request MIME type map.
 *
 * Without this, Symfony does not know that bebbo_json corresponds to
 * application/json, causing a 406 NotAcceptable when the route's _format
 * requirement includes bebbo_json and the request lacks ?_format=bebbo_json.
 */
class RequestFormatSubscriber implements EventSubscriberInterface {

  /**
   * Registers the bebbo_json MIME type on every incoming request.
   *
   * @param \Symfony\Component\HttpKernel\Event\RequestEvent $event
   *   The request event.
   */
  public function onRequest(RequestEvent $event): void {
    $event->getRequest()->setFormat('bebbo_json', ['application/json']);
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    // Run very early, before routing.
    return [KernelEvents::REQUEST => ['onRequest', 1000]];
  }

}
