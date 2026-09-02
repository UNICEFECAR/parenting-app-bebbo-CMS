<?php

declare(strict_types=1);

namespace Drupal\bebbo_api_security\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Connection;
use Psr\Log\LoggerInterface;

/**
 * Challenge-response verification for sideloaded/enterprise app builds.
 */
class SideloadedVerificationService {

  /**
   * Constructs a SideloadedVerificationService.
   *
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   * @param \Psr\Log\LoggerInterface $logger
   *   The logger channel.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config factory.
   */
  public function __construct(
    protected readonly Connection $database,
    protected readonly LoggerInterface $logger,
    protected readonly ConfigFactoryInterface $configFactory,
  ) {}

  /**
   * Register a sideloaded device and issue a challenge nonce.
   *
   * @param string $device_id
   *   SHA-256 hashed device identifier.
   * @param string $public_key_pem
   *   PEM-encoded EC P-256 public key.
   *
   * @return string
   *   Hex-encoded 32-byte challenge nonce (64 hex chars).
   *
   * @throws \InvalidArgumentException
   *   If the public key is not a valid EC P-256 key.
   */
  public function registerDevice(string $device_id, string $public_key_pem): string {
    $key = openssl_pkey_get_public($public_key_pem);
    if (!$key) {
      throw new \InvalidArgumentException('Invalid public key format.');
    }
    $details = openssl_pkey_get_details($key);
    if ($details['type'] !== OPENSSL_KEYTYPE_EC
      || ($details['ec']['curve_name'] ?? '') !== 'prime256v1') {
      throw new \InvalidArgumentException('Public key must be EC P-256 (prime256v1).');
    }

    $this->database->merge('bebbo_api_devices')
      ->keys(['device_id' => $device_id])
      ->fields([
        'platform' => 'sideloaded',
        'auth_method' => 'challenge_response',
        'public_key' => $public_key_pem,
        'status' => 'pending',
        'created' => time(),
        'updated' => time(),
      ])
      ->execute();

    $challenge = bin2hex(random_bytes(32));
    $this->database->insert('bebbo_api_challenges')->fields([
      'device_id' => $device_id,
      'challenge' => $challenge,
      'purpose' => 'sideloaded_verify',
      'expires' => time() + ((int) $this->configFactory->get('bebbo_api_security.settings')->get('challenge_expiry_seconds') ?: 120),
      'used' => 0,
      'created' => time(),
    ])->execute();

    return $challenge;
  }

  /**
   * Verify an ECDSA signature of a challenge nonce.
   *
   * @param string $device_id
   *   Device identifier.
   * @param string $challenge
   *   Hex-encoded challenge nonce.
   * @param string $signature_b64
   *   Base64-encoded ECDSA signature.
   *
   * @return bool
   *   TRUE if signature is valid and device is activated.
   *
   * @throws \RuntimeException
   *   If challenge not found, expired, or already used.
   */
  public function verify(string $device_id, string $challenge, string $signature_b64): bool {
    $row = $this->database->select('bebbo_api_challenges', 'c')
      ->fields('c')
      ->condition('c.device_id', $device_id)
      ->condition('c.challenge', $challenge)
      ->condition('c.purpose', 'sideloaded_verify')
      ->execute()
      ->fetchObject();

    if (!$row) {
      throw new \RuntimeException('Challenge not found.');
    }
    if ((int) $row->used === 1) {
      throw new \RuntimeException('Challenge already used.');
    }
    if ((int) $row->expires < time()) {
      throw new \RuntimeException('Challenge expired.');
    }

    $device = $this->database->select('bebbo_api_devices', 'd')
      ->fields('d', ['public_key'])
      ->condition('d.device_id', $device_id)
      ->execute()
      ->fetchObject();

    if (!$device || empty($device->public_key)) {
      throw new \RuntimeException('Device public key not found.');
    }

    $signature = base64_decode($signature_b64, TRUE);
    if ($signature === FALSE) {
      throw new \RuntimeException('Invalid base64 signature.');
    }

    $challenge_bytes = hex2bin($challenge);
    $result = openssl_verify(
      $challenge_bytes,
      $signature,
      $device->public_key,
      OPENSSL_ALGO_SHA256
    );

    // Mark challenge as used regardless of outcome.
    $this->database->update('bebbo_api_challenges')
      ->fields(['used' => 1])
      ->condition('id', $row->id)
      ->execute();

    if ($result !== 1) {
      return FALSE;
    }

    $this->database->update('bebbo_api_devices')
      ->fields(['status' => 'active', 'updated' => time()])
      ->condition('device_id', $device_id)
      ->execute();

    return TRUE;
  }

}
