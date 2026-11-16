<?php

declare(strict_types=1);

namespace Drupal\bebbo_serializer\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Drops Cookie variation only for explicitly public, anonymous API responses.
 *
 * Cookie-bearing, authenticated, private, token and error responses retain
 * their variation. Accept-Encoding and other Vary headers always remain.
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

    $request = $event->getRequest();
    $path = $request->getPathInfo();
    if (!preg_match(self::API_PATH_PATTERN, $path)) {
      return;
    }

    $response = $event->getResponse();
    // Only anonymous, explicitly public content can safely share a cookie-less
    // cache entry. Authentication endpoints and personalised/error responses
    // must retain their original cache variation.
    if (!$request->isMethodSafe() || $request->headers->has('Cookie')
      || $request->headers->has('Authorization') || !$response->isSuccessful()
      || !$response->headers->hasCacheControlDirective('public')
      || $response->headers->hasCacheControlDirective('private')
      || $response->headers->hasCacheControlDirective('no-store')
      || $response->headers->getCookies() || str_starts_with($path, '/api/security/')) {
      return;
    }
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
