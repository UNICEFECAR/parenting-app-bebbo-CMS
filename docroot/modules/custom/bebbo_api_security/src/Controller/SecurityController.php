<?php

declare(strict_types=1);

namespace Drupal\bebbo_api_security\Controller;

use Drupal\bebbo_api_security\Service\AppleAppAttestService;
use Drupal\bebbo_api_security\Service\DeviceRegistryService;
use Drupal\bebbo_api_security\Service\GooglePlayIntegrityService;
use Drupal\bebbo_api_security\Service\JwtService;
use Drupal\bebbo_api_security\Service\SideloadedVerificationService;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Flood\FloodInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * REST endpoints for device registration, verification, and token management.
 */
class SecurityController extends ControllerBase {

  /**
   * Constructs a SecurityController.
   *
   * @param \Drupal\bebbo_api_security\Service\GooglePlayIntegrityService $googleService
   *   Google Play Integrity verification service.
   * @param \Drupal\bebbo_api_security\Service\AppleAppAttestService $appleService
   *   Apple App Attest verification service.
   * @param \Drupal\bebbo_api_security\Service\SideloadedVerificationService $sideloadedService
   *   Sideloaded device verification service.
   * @param \Drupal\bebbo_api_security\Service\JwtService $jwtService
   *   JWT creation and validation service.
   * @param \Drupal\bebbo_api_security\Service\DeviceRegistryService $registry
   *   Device registry service.
   * @param \Drupal\Core\Flood\FloodInterface $flood
   *   Flood control service.
   * @param \Psr\Log\LoggerInterface $logger
   *   Logger channel.
   */
  public function __construct(
    protected readonly GooglePlayIntegrityService $googleService,
    protected readonly AppleAppAttestService $appleService,
    protected readonly SideloadedVerificationService $sideloadedService,
    protected readonly JwtService $jwtService,
    protected readonly DeviceRegistryService $registry,
    protected readonly FloodInterface $flood,
    protected readonly LoggerInterface $logger,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('bebbo_api_security.google_play_integrity'),
      $container->get('bebbo_api_security.apple_app_attest'),
      $container->get('bebbo_api_security.sideloaded_verification'),
      $container->get('bebbo_api_security.jwt_service'),
      $container->get('bebbo_api_security.device_registry'),
      $container->get('flood'),
      $container->get('logger.channel.bebbo_api_security'),
    );
  }

  /**
   * POST /api/security/register -- Store build attestation (Android/iOS).
   */
  public function register(Request $request): JsonResponse {
    $body = $this->validateRequestBody($request, ['platform', 'device_id']);
    if ($body instanceof JsonResponse) {
      return $body;
    }

    $platform = $body['platform'];
    $device_id = $body['device_id'];
    $ip = $request->getClientIp() ?? '0.0.0.0';

    $config = $this->config('bebbo_api_security.settings');
    $limit = (int) $config->get('register_rate_limit') ?: 10;
    $rate_error = $this->rateLimit($device_id, 'bebbo_register', $limit, 3600);
    if ($rate_error) {
      return $rate_error;
    }

    try {
      if ($platform === 'android') {
        if (empty($body['integrity_token'])) {
          return new JsonResponse([
            'error' => 'missing_field',
            'message' => 'integrity_token is required for Android.',
          ], 400);
        }
        // Prefer the server-issued challenge stored at issuance time; fall
        // back to a client-supplied nonce for callers without a challenge.
        $expected_hash = !empty($body['nonce']) ? $body['nonce'] : NULL;
        $stored_challenge = $this->registry->getActiveChallenge($device_id);
        if ($stored_challenge) {
          $expected_hash = $stored_challenge->challenge;
        }
        if ((bool) $config->get('debug_logging')) {
          $this->logger->debug('Play Integrity nonce source for @device: stored challenge from DB: @stored | body nonce: @body', [
            '@device' => $device_id,
            '@stored' => $stored_challenge->challenge ?? '(none)',
            '@body' => $body['nonce'] ?? '(none)',
          ]);
        }
        $this->googleService->verifyToken($body['integrity_token'], $device_id, $expected_hash);
        $auth_method = 'play_integrity';

        $this->registry->registerDevice([
          'device_id' => $device_id,
          'platform' => 'android',
          'auth_method' => $auth_method,
          'status' => 'active',
          'created' => time(),
          'updated' => time(),
        ]);
      }
      elseif ($platform === 'ios') {
        foreach (['key_id', 'attestation_object', 'client_data_hash'] as $field) {
          if (empty($body[$field])) {
            return new JsonResponse([
              'error' => 'missing_field',
              'message' => "{$field} is required for iOS.",
            ], 400);
          }
        }

        $public_key = $this->appleService->verifyAttestation(
          $body['attestation_object'],
          $body['key_id'],
          $body['client_data_hash'],
          $device_id
        );
        $auth_method = 'app_attest';

        $this->registry->registerDevice([
          'device_id' => $device_id,
          'platform' => 'ios',
          'auth_method' => $auth_method,
          'public_key' => $public_key,
          'apple_key_id' => $body['key_id'],
          'apple_counter' => 0,
          'status' => 'active',
          'created' => time(),
          'updated' => time(),
        ]);
      }
      else {
        return new JsonResponse([
          'error' => 'invalid_platform',
          'message' => 'Platform must be android or ios.',
        ], 400);
      }
    }
    catch (\RuntimeException $e) {
      $this->registry->logSecurityEvent('attest_fail', $device_id, $ip, [
        'platform' => $platform,
        'reason' => $e->getMessage(),
      ]);
      return new JsonResponse([
        'status' => 'rejected',
        'reason' => 'device_integrity_failed',
        'message' => $e->getMessage(),
      ], 403);
    }

    $this->registry->logSecurityEvent('register', $device_id, $ip, [
      'platform' => $platform,
    ]);

    return $this->buildTokenResponse($device_id, $platform, $auth_method);
  }

  /**
   * POST /api/security/device/register -- Sideloaded step 1: issue challenge.
   */
  public function deviceRegister(Request $request): JsonResponse {
    $body = $this->validateRequestBody($request, ['device_id', 'public_key']);
    if ($body instanceof JsonResponse) {
      return $body;
    }

    $ip = $request->getClientIp() ?? '0.0.0.0';
    $config = $this->config('bebbo_api_security.settings');
    $limit = (int) $config->get('device_register_ip_rate_limit') ?: 5;
    $rate_error = $this->rateLimit($ip, 'bebbo_device_register', $limit, 3600);
    if ($rate_error) {
      return $rate_error;
    }

    try {
      $challenge = $this->sideloadedService->registerDevice(
        $body['device_id'],
        $body['public_key']
      );
    }
    catch (\InvalidArgumentException $e) {
      return new JsonResponse([
        'error' => 'invalid_key',
        'message' => $e->getMessage(),
      ], 400);
    }

    $challenge_expiry = (int) $config->get('challenge_expiry_seconds') ?: 120;
    return new JsonResponse([
      'status' => 'challenge_issued',
      'challenge' => $challenge,
      'expires_in' => $challenge_expiry,
    ]);
  }

  /**
   * POST /api/security/device/verify -- Sideloaded step 2: verify signature.
   */
  public function deviceVerify(Request $request): JsonResponse {
    $body = $this->validateRequestBody($request, [
      'device_id',
      'challenge',
      'signature',
    ]);
    if ($body instanceof JsonResponse) {
      return $body;
    }

    $device_id = $body['device_id'];
    $ip = $request->getClientIp() ?? '0.0.0.0';

    $config = $this->config('bebbo_api_security.settings');
    $limit = (int) $config->get('verify_rate_limit') ?: 10;
    $rate_error = $this->rateLimit($device_id, 'bebbo_device_verify', $limit, 3600);
    if ($rate_error) {
      return $rate_error;
    }

    try {
      $valid = $this->sideloadedService->verify(
        $device_id,
        $body['challenge'],
        $body['signature']
      );
    }
    catch (\RuntimeException $e) {
      $this->registry->logSecurityEvent('attest_fail', $device_id, $ip, [
        'platform' => 'sideloaded',
        'reason' => $e->getMessage(),
      ]);
      return new JsonResponse([
        'status' => 'rejected',
        'reason' => 'verification_failed',
        'message' => $e->getMessage(),
      ], 403);
    }

    if (!$valid) {
      $this->registry->logSecurityEvent('attest_fail', $device_id, $ip, [
        'platform' => 'sideloaded',
        'reason' => 'signature_invalid',
      ]);
      return new JsonResponse([
        'status' => 'rejected',
        'reason' => 'signature_invalid',
        'message' => 'Challenge signature verification failed.',
      ], 403);
    }

    $this->registry->logSecurityEvent('register', $device_id, $ip, [
      'platform' => 'sideloaded',
    ]);

    return $this->buildTokenResponse($device_id, 'sideloaded', 'challenge_response');
  }

  /**
   * POST /api/security/refresh -- Token refresh with rotation.
   */
  public function refresh(Request $request): JsonResponse {
    $body = $this->validateRequestBody($request, ['refresh_token']);
    if ($body instanceof JsonResponse) {
      return $body;
    }

    // Throttle per device. Tokens that match no device (garbage or forged)
    // fall back to the client IP so they cannot be sprayed for free.
    $config = $this->config('bebbo_api_security.settings');
    $limit = (int) $config->get('refresh_rate_limit') ?: 30;
    $device_id = $this->jwtService->getDeviceIdForRefreshToken($body['refresh_token']);
    $identifier = $device_id ?? ($request->getClientIp() ?? '0.0.0.0');
    $rate_error = $this->rateLimit($identifier, 'bebbo_refresh', $limit, 3600);
    if ($rate_error) {
      return $rate_error;
    }

    $result = $this->jwtService->refreshTokens($body['refresh_token']);
    if (!$result) {
      return new JsonResponse([
        'status' => 'invalid',
        'message' => 'Refresh token expired or revoked. Re-attestation required.',
      ], 401);
    }

    return new JsonResponse([
      'status' => 'refreshed',
      'access_token' => $result['access_token'],
      'token_type' => 'Bearer',
      'expires_in' => $result['expires_in'],
      'refresh_token' => $result['refresh_token'],
    ]);
  }

  /**
   * POST /api/security/revoke -- Revoke refresh tokens for a device.
   */
  public function revoke(Request $request): JsonResponse {
    $token = $this->extractBearerToken($request);
    if (!$token) {
      return new JsonResponse([
        'error' => 'missing_token',
        'message' => 'Authorization header required.',
      ], 401);
    }

    $payload = $this->jwtService->validateToken($token);
    if (!$payload) {
      return new JsonResponse([
        'error' => 'invalid_token',
        'message' => 'Invalid or expired JWT.',
      ], 401);
    }

    $device_id = $payload['sub'];
    $count = $this->jwtService->revokeTokensForDevice($device_id);
    $ip = $request->getClientIp() ?? '0.0.0.0';
    $this->registry->logSecurityEvent('revoke', $device_id, $ip, [
      'tokens_revoked' => $count,
    ]);

    return new JsonResponse(['status' => 'revoked']);
  }

  /**
   * Decode and validate JSON request body.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The HTTP request.
   * @param array $required
   *   Required field names.
   *
   * @return array|\Symfony\Component\HttpFoundation\JsonResponse
   *   Decoded body array, or error response.
   */
  private function validateRequestBody(Request $request, array $required): array|JsonResponse {
    $body = json_decode($request->getContent(), TRUE);
    if (!is_array($body)) {
      return new JsonResponse([
        'error' => 'invalid_json',
        'message' => 'Request body must be valid JSON.',
      ], 400);
    }

    foreach ($required as $field) {
      if (empty($body[$field])) {
        return new JsonResponse([
          'error' => 'missing_field',
          'message' => "Field '{$field}' is required.",
        ], 400);
      }
    }

    return $body;
  }

  /**
   * Build standard token response after successful registration.
   *
   * @param string $device_id
   *   Device identifier.
   * @param string $platform
   *   Platform name.
   * @param string $auth_method
   *   Authentication method used.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   Token response.
   */
  private function buildTokenResponse(string $device_id, string $platform, string $auth_method): JsonResponse {
    $jwt = $this->jwtService->createToken($device_id, $platform, $auth_method);
    $refresh = $this->jwtService->createRefreshToken($device_id);
    $config = $this->config('bebbo_api_security.settings');
    $expiry = (int) $config->get('jwt_expiry_seconds') ?: 3600;

    return new JsonResponse([
      'status' => 'verified',
      'access_token' => $jwt,
      'token_type' => 'Bearer',
      'expires_in' => $expiry,
      'refresh_token' => $refresh['token'],
    ]);
  }

  /**
   * Check flood control. Returns error response or NULL if allowed.
   *
   * @param string $identifier
   *   Flood identifier (device_id or IP).
   * @param string $event
   *   Flood event name.
   * @param int $threshold
   *   Max requests allowed.
   * @param int $window
   *   Time window in seconds.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse|null
   *   Error response if rate limited, NULL if allowed.
   */
  private function rateLimit(string $identifier, string $event, int $threshold, int $window): ?JsonResponse {
    if (!$this->flood->isAllowed($event, $threshold, $window, $identifier)) {
      return new JsonResponse([
        'error' => 'rate_limited',
        'message' => 'Too many requests. Try again later.',
      ], 429);
    }
    $this->flood->register($event, $window, $identifier);
    return NULL;
  }

  /**
   * Extract Bearer token from Authorization header.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The HTTP request.
   *
   * @return string|null
   *   The token, or NULL if not present.
   */
  private function extractBearerToken(Request $request): ?string {
    $header = $request->headers->get('Authorization', '');
    if (str_starts_with($header, 'Bearer ')) {
      return substr($header, 7);
    }
    return NULL;
  }

}
