<?php

declare(strict_types=1);

namespace Drupal\bebbo_serializer\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Drops Cookie from the Vary header on the API responses.
 *
 * Core marks every cacheable response Vary: Cookie, which is right for HTML:
 * an editor with a session and an anonymous visitor request the same URL and
 * must not share a cached copy. The API endpoints are anonymous JSON whose
 * body does not depend on any cookie, so there the header states a dependency
 * that does not exist.
 *
 * Shared caches believe it. Varnish and Fastly key on the declared headers, so
 * a request carrying any cookie cannot reuse the copy stored for a cookie-less
 * one - a miss, and a full origin render, for a byte-identical response. The
 * mobile app sends no cookies today, which is the only reason the current hit
 * rate survives; anything that starts attaching one would fork the cache
 * silently.
 *
 * Only Cookie is removed. Accept-Encoding and anything else an edge added stay,
 * and the HTML responses keep Vary: Cookie untouched.
 *
 * @see \Drupal\Core\EventSubscriber\FinishResponseSubscriber::setResponseCacheable()
 */
final class ApiVaryResponseSubscriber implements EventSubscriberInterface {

  /**
   * Paths whose responses do not vary by cookie.
   *
   * Covers /api/* and the versioned prefixes, /v2/api/* today.
   */
  private const API_PATH_PATTERN = '#^/(v\d+/)?api/#';

  /**
   * Removes Cookie from Vary on API responses.
   *
   * @param \Symfony\Component\HttpKernel\Event\ResponseEvent $event
   *   The response event.
   */
  public function onResponse(ResponseEvent $event): void {
    if (!$event->isMainRequest()) {
      return;
    }

    $path = $event->getRequest()->getPathInfo();
    if (!preg_match(self::API_PATH_PATTERN, $path)) {
      return;
    }

    $response = $event->getResponse();
    $vary = $response->getVary();
    if (!$vary) {
      return;
    }

    $kept = array_values(array_filter(
      $vary,
      static fn(string $header): bool => strcasecmp(trim($header), 'Cookie') !== 0,
    ));

    if (count($kept) === count($vary)) {
      return;
    }

    if ($kept) {
      $response->setVary($kept, TRUE);
    }
    else {
      $response->headers->remove('Vary');
    }
  }

  /**
   * {@inheritdoc}
   *
   * Core sets Vary at priority 0, so this has to run after it.
   */
  public static function getSubscribedEvents(): array {
    return [KernelEvents::RESPONSE => ['onResponse', -10]];
  }

}
