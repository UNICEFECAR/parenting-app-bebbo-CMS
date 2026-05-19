<?php

namespace Drupal\bebbo_serializer\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Sets ETag headers on V2 API responses and handles 304 Not Modified.
 *
 * Works with BebboSerializer::checkEtag() which computes the ETag from
 * a lightweight data signature and stores it on the request attributes.
 */
class EtagResponseSubscriber implements EventSubscriberInterface {

  /**
   * Adds ETag header and converts to 304 when data is unchanged.
   *
   * @param \Symfony\Component\HttpKernel\Event\ResponseEvent $event
   *   The response event.
   */
  public function onResponse(ResponseEvent $event): void {
    $request = $event->getRequest();
    $etag = $request->attributes->get('bebbo_etag');

    if ($etag === NULL) {
      return;
    }

    $response = $event->getResponse();
    $response->headers->set('ETag', $etag);

    if ($request->attributes->get('bebbo_etag_match')) {
      $response->setStatusCode(304);
      $response->setContent('');
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [KernelEvents::RESPONSE => ['onResponse', 0]];
  }

}
