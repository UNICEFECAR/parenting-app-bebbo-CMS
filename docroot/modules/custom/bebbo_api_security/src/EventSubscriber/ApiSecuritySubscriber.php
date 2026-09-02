<?php

declare(strict_types=1);

namespace Drupal\bebbo_api_security\EventSubscriber;

use Drupal\bebbo_api_security\Service\JwtService;
use Drupal\Core\Config\ConfigFactoryInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * JWT validation on protected API routes.
 */
class ApiSecuritySubscriber implements EventSubscriberInterface {

  public function __construct(
    protected readonly JwtService $jwtService,
    protected readonly ConfigFactoryInterface $configFactory,
    protected readonly LoggerInterface $logger,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [KernelEvents::REQUEST => ['onRequest', 300]];
  }

  /**
   * Validate JWT on protected API routes based on enforcement mode.
   */
  public function onRequest(RequestEvent $event): void {
    $request = $event->getRequest();
    $path = $request->getPathInfo();

    if (!$this->isProtectedPath($path)) {
      return;
    }

    $config = $this->configFactory->get('bebbo_api_security.settings');
    $mode = $config->get('enforcement_mode') ?? 'disabled';

    if ($mode === 'disabled') {
      return;
    }

    if ($this->isDevBypass($request)) {
      return;
    }

    $token = $this->extractBearerToken($request);

    if ($mode === 'grace_period') {
      $this->handleGracePeriod($request, $token, $path);
      return;
    }

    // Enforced mode.
    if (!$token) {
      $event->setResponse($this->buildUnauthorizedResponse(
        'missing_token',
        'A valid JWT token is required to access this resource.'
      ));
      return;
    }

    $payload = $this->jwtService->validateToken($token);
    if (!$payload) {
      $event->setResponse($this->buildUnauthorizedResponse(
        'invalid_token',
        'The provided JWT token is invalid or expired.'
      ));
      return;
    }

    $request->attributes->set('bebbo_device_id', $payload['sub']);
  }

  /**
   * Check if a path should be protected by JWT validation.
   */
  private function isProtectedPath(string $path): bool {
    $config = $this->configFactory->get('bebbo_api_security.settings');

    $excluded_raw = trim($config->get('excluded_api_patterns') ?? '');
    foreach (array_filter(array_map('trim', explode("\n", $excluded_raw))) as $pattern) {
      if (preg_match('#^' . $pattern . '#', $path)) {
        return FALSE;
      }
    }

    $protected_raw = trim($config->get('protected_api_patterns') ?? '');
    foreach (array_filter(array_map('trim', explode("\n", $protected_raw))) as $pattern) {
      if (preg_match('#^' . $pattern . '#', $path)) {
        return TRUE;
      }
    }

    return FALSE;
  }

  /**
   * Handle grace period mode — log but always allow through.
   */
  private function handleGracePeriod(Request $request, ?string $token, string $path): void {
    if (!$token) {
      return;
    }

    $payload = $this->jwtService->validateToken($token);
    if ($payload) {
      $request->attributes->set('bebbo_device_id', $payload['sub']);
    }
    else {
      $this->logger->warning('Invalid JWT in grace period from @ip for @path', [
        '@ip' => $request->getClientIp(),
        '@path' => $path,
      ]);
    }
  }

  /**
   * Check if the request IP is in the dev bypass list.
   */
  private function isDevBypass(Request $request): bool {
    $config = $this->configFactory->get('bebbo_api_security.settings');
    if (!$config->get('dev_bypass_enabled')) {
      return FALSE;
    }
    $allowed_ips = array_filter(array_map('trim',
      explode("\n", $config->get('dev_bypass_ips') ?? '')
    ));
    return in_array($request->getClientIp(), $allowed_ips, TRUE);
  }

  /**
   * Extract Bearer token from Authorization header.
   */
  private function extractBearerToken(Request $request): ?string {
    $header = $request->headers->get('Authorization', '');
    if (str_starts_with($header, 'Bearer ')) {
      return substr($header, 7);
    }
    return NULL;
  }

  /**
   * Build a 401 JSON response with WWW-Authenticate header.
   */
  private function buildUnauthorizedResponse(string $error, string $description): JsonResponse {
    $response = new JsonResponse([
      'error' => $error,
      'error_description' => $description,
    ], 401);
    $response->headers->set('WWW-Authenticate', 'Bearer realm="Bebbo API"');
    return $response;
  }

}
