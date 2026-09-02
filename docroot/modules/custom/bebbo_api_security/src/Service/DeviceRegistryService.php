<?php

declare(strict_types=1);

namespace Drupal\bebbo_api_security\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Connection;
use Psr\Log\LoggerInterface;

/**
 * Database operations for device registration, tokens, challenges, and logs.
 */
class DeviceRegistryService {

  /**
   * Constructs a DeviceRegistryService.
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
   * Register or re-register a device (UPSERT).
   *
   * On re-registration, preserves the original created timestamp and
   * invalidates any existing refresh tokens and challenges.
   *
   * @return int
   *   Merge status constant.
   */
  public function registerDevice(array $fields): int {
    $device_id = $fields['device_id'];

    // Invalidate stale tokens and challenges on re-registration.
    $this->database->delete('bebbo_api_refresh_tokens')
      ->condition('device_id', $device_id)
      ->execute();
    $this->database->delete('bebbo_api_challenges')
      ->condition('device_id', $device_id)
      ->execute();

    // Preserve original created timestamp on update.
    $update_fields = $fields;
    unset($update_fields['device_id'], $update_fields['created']);

    return (int) $this->database->merge('bebbo_api_devices')
      ->keys(['device_id' => $device_id])
      ->fields($update_fields)
      ->insertFields(['created' => $fields['created'] ?? time()])
      ->execute();
  }

  /**
   * Fetch a device by device_id.
   */
  public function getDevice(string $device_id): ?object {
    return $this->database->select('bebbo_api_devices', 'd')
      ->fields('d')
      ->condition('d.device_id', $device_id)
      ->execute()
      ->fetchObject() ?: NULL;
  }

  /**
   * Fetch a device by Apple App Attest key ID.
   */
  public function getDeviceByAppleKeyId(string $key_id): ?object {
    return $this->database->select('bebbo_api_devices', 'd')
      ->fields('d')
      ->condition('d.apple_key_id', $key_id)
      ->execute()
      ->fetchObject() ?: NULL;
  }

  /**
   * Fetch the newest unused, unexpired challenge for a device.
   */
  public function getActiveChallenge(string $device_id): ?object {
    return $this->database->select('bebbo_api_challenges', 'c')
      ->fields('c')
      ->condition('c.device_id', $device_id)
      ->condition('c.used', 0)
      ->condition('c.expires', time(), '>')
      ->orderBy('c.created', 'DESC')
      ->range(0, 1)
      ->execute()
      ->fetchObject() ?: NULL;
  }

  /**
   * Update device fields.
   */
  public function updateDevice(string $device_id, array $fields): void {
    $this->database->update('bebbo_api_devices')
      ->fields($fields)
      ->condition('device_id', $device_id)
      ->execute();
  }

  /**
   * Set device status to revoked.
   */
  public function revokeDevice(string $device_id): void {
    $this->updateDevice($device_id, [
      'status' => 'revoked',
      'updated' => time(),
    ]);
  }

  /**
   * Insert a security event into the audit log.
   */
  public function logSecurityEvent(string $event_type, ?string $device_id, string $ip, ?array $details = NULL): void {
    $this->database->insert('bebbo_api_security_log')
      ->fields([
        'device_id' => $device_id,
        'event_type' => $event_type,
        'details' => $details ? json_encode($details) : NULL,
        'ip_address' => $ip,
        'created' => time(),
      ])
      ->execute();
  }

  /**
   * Purge expired challenges, revoked tokens, and old log entries.
   *
   * @return array
   *   Counts of purged rows keyed by type.
   */
  public function purgeExpired(): array {
    $now = time();
    $stats = [];

    $stats['challenges'] = $this->database->delete('bebbo_api_challenges')
      ->condition('expires', $now, '<')
      ->execute();

    $config = $this->configFactory->get('bebbo_api_security.settings');
    $retention_days = (int) $config->get('revoked_token_retention_days') ?: 7;
    $stats['tokens_revoked'] = $this->database->delete('bebbo_api_refresh_tokens')
      ->condition('revoked', 1)
      ->condition('created', $now - ($retention_days * 86400), '<')
      ->execute();

    $stats['tokens_expired'] = $this->database->delete('bebbo_api_refresh_tokens')
      ->condition('expires', $now, '<')
      ->execute();

    $max_entries = (int) $config->get('security_log_max_entries') ?: 10000;
    $max_id = $this->database->select('bebbo_api_security_log', 'l')
      ->fields('l', ['id'])
      ->orderBy('id', 'DESC')
      ->range($max_entries, 1)
      ->execute()
      ->fetchField();

    $stats['logs'] = 0;
    if ($max_id) {
      $stats['logs'] = $this->database->delete('bebbo_api_security_log')
        ->condition('id', $max_id, '<=')
        ->execute();
    }

    return $stats;
  }

  /**
   * Truncate all security tables, resetting auto-increment counters.
   *
   * @return array
   *   Row counts per table before truncation.
   */
  public function truncateAll(): array {
    $tables = [
      'devices' => 'bebbo_api_devices',
      'challenges' => 'bebbo_api_challenges',
      'tokens' => 'bebbo_api_refresh_tokens',
      'logs' => 'bebbo_api_security_log',
    ];
    $stats = [];
    foreach ($tables as $key => $table) {
      $stats[$key] = (int) $this->database->select($table)
        ->countQuery()
        ->execute()
        ->fetchField();
      $this->database->truncate($table)->execute();
    }
    return $stats;
  }

}
