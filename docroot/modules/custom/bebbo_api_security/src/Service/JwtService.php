<?php

declare(strict_types=1);

namespace Drupal\bebbo_api_security\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Connection;
use Drupal\key\KeyRepositoryInterface;
use Firebase\JWT\ExpiredException;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Firebase\JWT\SignatureInvalidException;
use Psr\Log\LoggerInterface;

/**
 * JWT creation, validation, and refresh token management.
 */
class JwtService {

  /**
   * Cached RSA private key PEM string.
   *
   * @var string|null
   */
  private ?string $privateKey = NULL;

  /**
   * Cached RSA public key PEM string.
   *
   * @var string|null
   */
  private ?string $publicKey = NULL;

  public function __construct(
    protected readonly KeyRepositoryInterface $keyRepository,
    protected readonly Connection $database,
    protected readonly ConfigFactoryInterface $configFactory,
    protected readonly LoggerInterface $logger,
  ) {}

  /**
   * Create a signed JWT for a verified device.
   */
  public function createToken(string $device_id, string $platform, string $auth_method): string {
    $config = $this->configFactory->get('bebbo_api_security.settings');
    $expiry = (int) $config->get('jwt_expiry_seconds') ?: 3600;

    $payload = [
      'iss' => 'bebbo-cms',
      'sub' => $device_id,
      'iat' => time(),
      'exp' => time() + $expiry,
      'platform' => $platform,
      'auth_method' => $auth_method,
      'jti' => bin2hex(random_bytes(16)),
    ];

    return JWT::encode($payload, $this->getPrivateKey(), 'RS256');
  }

  /**
   * Validate a JWT and return the decoded payload, or NULL on failure.
   */
  public function validateToken(string $token): ?array {
    try {
      $decoded = JWT::decode($token, new Key($this->getPublicKey(), 'RS256'));
      return (array) $decoded;
    }
    catch (ExpiredException) {
      return NULL;
    }
    catch (SignatureInvalidException) {
      $this->logger->warning('Invalid JWT signature');
      return NULL;
    }
    catch (\Exception $e) {
      $this->logger->warning('JWT validation failed: @msg', ['@msg' => $e->getMessage()]);
      return NULL;
    }
  }

  /**
   * Create a new opaque refresh token and store its hash.
   *
   * @return array
   *   Keys: 'token' (raw opaque string), 'family' (rotation family ID).
   */
  public function createRefreshToken(string $device_id): array {
    $config = $this->configFactory->get('bebbo_api_security.settings');
    $expiry = (int) $config->get('refresh_expiry_seconds') ?: 2592000;

    $raw_token = bin2hex(random_bytes(32));
    $family = bin2hex(random_bytes(32));
    $hash = hash('sha256', $raw_token);

    $this->database->insert('bebbo_api_refresh_tokens')->fields([
      'device_id' => $device_id,
      'token_hash' => $hash,
      'token_family' => $family,
      'expires' => time() + $expiry,
      'revoked' => 0,
      'created' => time(),
    ])->execute();

    return ['token' => $raw_token, 'family' => $family];
  }

  /**
   * Validate a refresh token and issue a fresh access token.
   *
   * When `refresh_rotation_enabled` is TRUE (default) the presented refresh
   * token is revoked and a new one is issued in the same family (rotation).
   * When FALSE, the presented refresh token is reused unchanged and only a new
   * access token is minted.
   *
   * Implements family-based replay detection: if a revoked token is reused,
   * the entire token family is revoked (compromised session).
   *
   * @return array|null
   *   Keys: access_token, refresh_token, expires_in. NULL on any failure.
   */
  public function refreshTokens(string $raw_refresh_token): ?array {
    $hash = hash('sha256', $raw_refresh_token);

    $row = $this->database->select('bebbo_api_refresh_tokens', 'rt')
      ->fields('rt')
      ->condition('rt.token_hash', $hash)
      ->execute()
      ->fetchObject();

    if (!$row) {
      return NULL;
    }

    if ((int) $row->revoked === 1) {
      $this->database->update('bebbo_api_refresh_tokens')
        ->fields(['revoked' => 1])
        ->condition('token_family', $row->token_family)
        ->execute();
      $this->logger->error('Refresh token replay detected for device @device, family @family', [
        '@device' => $row->device_id,
        '@family' => $row->token_family,
      ]);
      return NULL;
    }

    if ((int) $row->expires < time()) {
      return NULL;
    }

    $config = $this->configFactory->get('bebbo_api_security.settings');
    $rotation_enabled = $config->get('refresh_rotation_enabled') ?? TRUE;

    // Look up the device first so a missing device cannot leave a rotated
    // token orphaned (old revoked, new issued, but no response returned).
    $device = $this->database->select('bebbo_api_devices', 'd')
      ->fields('d', ['platform', 'auth_method'])
      ->condition('d.device_id', $row->device_id)
      ->execute()
      ->fetchObject();

    if (!$device) {
      return NULL;
    }

    if ($rotation_enabled) {
      // Rotate: revoke the presented token and issue a new one in the same
      // family.
      $this->database->update('bebbo_api_refresh_tokens')
        ->fields(['revoked' => 1])
        ->condition('id', $row->id)
        ->execute();

      $expiry = (int) $config->get('refresh_expiry_seconds') ?: 2592000;
      $new_raw = bin2hex(random_bytes(32));
      $new_hash = hash('sha256', $new_raw);

      $this->database->insert('bebbo_api_refresh_tokens')->fields([
        'device_id' => $row->device_id,
        'token_hash' => $new_hash,
        'token_family' => $row->token_family,
        'expires' => time() + $expiry,
        'revoked' => 0,
        'created' => time(),
      ])->execute();

      $refresh_token = $new_raw;
    }
    else {
      // Rotation disabled: reuse the presented refresh token unchanged.
      $refresh_token = $raw_refresh_token;
    }

    $jwt = $this->createToken($row->device_id, $device->platform, $device->auth_method);

    return [
      'access_token' => $jwt,
      'refresh_token' => $refresh_token,
      'expires_in' => (int) $config->get('jwt_expiry_seconds') ?: 3600,
    ];
  }

  /**
   * Resolve the device a refresh token belongs to, without validating it.
   *
   * Intended for rate-limit keying only. Revoked and expired tokens are
   * deliberately still resolved so replay attempts count against the same
   * device budget instead of escaping the limit.
   *
   * @param string $raw_refresh_token
   *   The refresh token as presented by the client.
   *
   * @return string|null
   *   The owning device ID, or NULL if the token matches no stored row.
   */
  public function getDeviceIdForRefreshToken(string $raw_refresh_token): ?string {
    $device_id = $this->database->select('bebbo_api_refresh_tokens', 'rt')
      ->fields('rt', ['device_id'])
      ->condition('rt.token_hash', hash('sha256', $raw_refresh_token))
      ->execute()
      ->fetchField();

    return $device_id === FALSE ? NULL : (string) $device_id;
  }

  /**
   * Revoke all refresh tokens for a device.
   */
  public function revokeTokensForDevice(string $device_id): int {
    return $this->database->update('bebbo_api_refresh_tokens')
      ->fields(['revoked' => 1])
      ->condition('device_id', $device_id)
      ->condition('revoked', 0)
      ->execute();
  }

  /**
   * Load the RSA private key from Key module.
   */
  private function getPrivateKey(): string {
    if ($this->privateKey === NULL) {
      $key = $this->keyRepository->getKey('bebbo_jwt_signing_key');
      if (!$key) {
        throw new \RuntimeException('JWT signing key not configured. Check Key module entity "bebbo_jwt_signing_key".');
      }
      $this->privateKey = $key->getKeyValue();
      if (empty($this->privateKey)) {
        throw new \RuntimeException('JWT signing key is empty. Check BEBBO_JWT_PRIVATE_KEY environment variable.');
      }
    }
    return $this->privateKey;
  }

  /**
   * Derive the public key from the private key.
   */
  private function getPublicKey(): string {
    if ($this->publicKey === NULL) {
      $private = openssl_pkey_get_private($this->getPrivateKey());
      if (!$private) {
        throw new \RuntimeException('Invalid RSA private key.');
      }
      $details = openssl_pkey_get_details($private);
      $this->publicKey = $details['key'];
    }
    return $this->publicKey;
  }

}
