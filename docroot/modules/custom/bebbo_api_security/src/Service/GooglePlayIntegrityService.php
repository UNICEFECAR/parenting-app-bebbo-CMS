<?php

declare(strict_types=1);

namespace Drupal\bebbo_api_security\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\key\KeyRepositoryInterface;
use Firebase\JWT\JWT;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Log\LoggerInterface;

/**
 * Google Play Integrity API verification for Android store builds.
 */
class GooglePlayIntegrityService {

  /**
   * Cached Google OAuth2 access token.
   *
   * @var string|null
   */
  private ?string $googleAccessToken = NULL;

  /**
   * Unix timestamp when the cached token expires.
   *
   * @var int
   */
  private int $tokenExpiresAt = 0;

  /**
   * Constructs a GooglePlayIntegrityService.
   *
   * @param \GuzzleHttp\ClientInterface $httpClient
   *   HTTP client for API requests.
   * @param \Drupal\key\KeyRepositoryInterface $keyRepository
   *   Key repository for service account credentials.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   Config factory for module settings.
   * @param \Psr\Log\LoggerInterface $logger
   *   Logger channel.
   */
  public function __construct(
    protected readonly ClientInterface $httpClient,
    protected readonly KeyRepositoryInterface $keyRepository,
    protected readonly ConfigFactoryInterface $configFactory,
    protected readonly LoggerInterface $logger,
  ) {}

  /**
   * Verify a Play Integrity token from the mobile app.
   *
   * @param string $integrity_token
   *   Opaque token from the app's Play Integrity API call.
   * @param string $device_id
   *   Device identifier — used to verify requestHash binding.
   * @param string|null $expected_request_hash
   *   Expected base64url-encoded SHA-256 of the nonce the app passed to
   *   requestIntegrityToken(). If NULL, defaults to SHA-256(device_id).
   *
   * @return array
   *   Decoded token payload with verdicts.
   *
   * @throws \RuntimeException
   *   On verification failure.
   */
  public function verifyToken(string $integrity_token, string $device_id, ?string $expected_request_hash = NULL): array {
    $config = $this->configFactory->get('bebbo_api_security.settings');
    $package_name = $config->get('google_package_name');

    if (empty($package_name)) {
      throw new \RuntimeException('Google Play package name not configured.');
    }

    $access_token = $this->getGoogleAccessToken();
    $timeout = (int) $config->get('google_api_timeout') ?: 10;

    try {
      $response = $this->httpClient->request('POST',
        "https://playintegrity.googleapis.com/v1/{$package_name}:decodeIntegrityToken",
        [
          'headers' => [
            'Authorization' => "Bearer {$access_token}",
            'Content-Type' => 'application/json',
          ],
          'json' => ['integrity_token' => $integrity_token],
          'timeout' => $timeout,
        ]
      );
    }
    catch (GuzzleException $e) {
      $response_body = '';
      if (method_exists($e, 'getResponse') && $e->getResponse()) {
        $response_body = (string) $e->getResponse()->getBody();
      }
      $this->logger->error('Google Play Integrity API error: @msg | Full response: @body', [
        '@msg' => $e->getMessage(),
        '@body' => $response_body ?: '(no response body)',
      ]);
      throw new \RuntimeException('Failed to verify integrity token with Google.');
    }

    $payload = json_decode((string) $response->getBody(), TRUE);
    if (!$payload) {
      throw new \RuntimeException('Invalid response from Google Play Integrity API.');
    }

    if ((bool) $config->get('debug_logging')) {
      $this->logger->debug('Play Integrity decoded payload: @payload', [
        '@payload' => json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
      ]);
    }

    if ($expected_request_hash === NULL) {
      $expected_request_hash = $device_id;
    }

    $this->verifyVerdicts($payload, $device_id, $expected_request_hash);

    return $payload;
  }

  /**
   * Verify the verdicts in a decoded integrity token payload.
   *
   * @param array $payload
   *   Decoded token payload from Google.
   * @param string $device_id
   *   Device identifier for error context.
   * @param string $expected_request_hash
   *   The challenge string the app passed verbatim as nonce.
   *
   * @throws \RuntimeException
   *   If any verdict check fails.
   */
  private function verifyVerdicts(array $payload, string $device_id, string $expected_request_hash): void {
    $config = $this->configFactory->get('bebbo_api_security.settings');
    $package_name = $config->get('google_package_name');
    $freshness = (int) $config->get('google_verdict_freshness_seconds') ?: 600;

    $details = $payload['tokenPayloadExternal'] ?? [];

    // Verify nonce — binds integrity token to this request.
    $actual_nonce = $details['requestDetails']['nonce'] ?? '';
    if ((bool) $config->get('debug_logging')) {
      $this->logger->debug('Nonce comparison — expected: @expected | actual nonce: @actual', [
        '@expected' => $expected_request_hash,
        '@actual' => $actual_nonce,
      ]);
    }
    if (!hash_equals($expected_request_hash, $actual_nonce)) {
      throw new \RuntimeException('Nonce mismatch: integrity token not bound to this request.');
    }

    // Verify package name.
    $request_package = $details['requestDetails']['requestPackageName'] ?? '';
    if ($request_package !== $package_name) {
      throw new \RuntimeException("Package name mismatch: expected {$package_name}, got {$request_package}");
    }

    // Verify timestamp freshness.
    $timestamp_ms = (int) ($details['requestDetails']['timestampMillis'] ?? 0);
    $age_seconds = abs(time() - (int) ($timestamp_ms / 1000));
    if ($age_seconds > $freshness) {
      throw new \RuntimeException("Integrity token too old: {$age_seconds}s > {$freshness}s");
    }

    // Verify device integrity.
    $device_verdicts = $details['deviceIntegrity']['deviceRecognitionVerdict'] ?? [];
    if (!in_array('MEETS_DEVICE_INTEGRITY', $device_verdicts, TRUE)) {
      throw new \RuntimeException(
        'Device does not meet integrity requirements. Verdicts: '
        . implode(', ', $device_verdicts)
      );
    }

    // Verify app recognition.
    $app_verdict = $details['appIntegrity']['appRecognitionVerdict'] ?? 'UNEVALUATED';
    $allowed_verdicts = ['PLAY_RECOGNIZED'];
    if ((bool) $config->get('google_allow_unrecognized_version')) {
      $allowed_verdicts[] = 'UNRECOGNIZED_VERSION';
    }
    if (!in_array($app_verdict, $allowed_verdicts, TRUE)) {
      throw new \RuntimeException("App not recognized by Play Store: {$app_verdict}");
    }
    if ($app_verdict === 'UNRECOGNIZED_VERSION') {
      $this->logger->warning('Accepted UNRECOGNIZED_VERSION app verdict for device @device — google_allow_unrecognized_version is enabled. This must be off in production.', [
        '@device' => $device_id,
      ]);
    }
  }

  /**
   * Get a Google OAuth2 access token using service account JWT.
   *
   * Caches the token for 55 minutes (Google tokens last 1 hour).
   *
   * @return string
   *   OAuth2 access token.
   *
   * @throws \RuntimeException
   *   If key not configured or token exchange fails.
   */
  private function getGoogleAccessToken(): string {
    if ($this->googleAccessToken && time() < $this->tokenExpiresAt) {
      return $this->googleAccessToken;
    }

    $key = $this->keyRepository->getKey('bebbo_google_sa_key');
    if (!$key) {
      throw new \RuntimeException('Google Service Account key not configured. Check Key entity "bebbo_google_sa_key".');
    }
    $sa_json = json_decode($key->getKeyValue(), TRUE);
    if (!$sa_json || empty($sa_json['client_email']) || empty($sa_json['private_key'])) {
      throw new \RuntimeException('Invalid Google Service Account JSON. Expected client_email and private_key.');
    }

    $now = time();
    $jwt_payload = [
      'iss' => $sa_json['client_email'],
      'scope' => 'https://www.googleapis.com/auth/playintegrity',
      'aud' => 'https://oauth2.googleapis.com/token',
      'iat' => $now,
      'exp' => $now + 3600,
    ];

    $assertion = JWT::encode($jwt_payload, $sa_json['private_key'], 'RS256');

    $timeout = (int) $this->configFactory->get('bebbo_api_security.settings')->get('google_api_timeout') ?: 10;
    try {
      $response = $this->httpClient->request('POST', 'https://oauth2.googleapis.com/token', [
        'form_params' => [
          'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
          'assertion' => $assertion,
        ],
        'timeout' => $timeout,
      ]);
    }
    catch (GuzzleException $e) {
      $this->logger->error('Google OAuth2 token exchange failed: @msg', [
        '@msg' => $e->getMessage(),
      ]);
      throw new \RuntimeException('Failed to obtain Google access token.');
    }

    $token_data = json_decode((string) $response->getBody(), TRUE);
    if (empty($token_data['access_token'])) {
      throw new \RuntimeException('Google OAuth2 response missing access_token.');
    }

    $this->googleAccessToken = $token_data['access_token'];
    $this->tokenExpiresAt = $now + 3300;

    return $this->googleAccessToken;
  }

}
