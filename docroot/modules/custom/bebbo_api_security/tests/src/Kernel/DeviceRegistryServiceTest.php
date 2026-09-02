<?php

declare(strict_types=1);

namespace Drupal\Tests\bebbo_api_security\Kernel;

use Drupal\bebbo_api_security\Service\DeviceRegistryService;
use Drupal\KernelTests\KernelTestBase;

/**
 * Kernel tests for DeviceRegistryService CRUD and purge operations.
 *
 * @coversDefaultClass \Drupal\bebbo_api_security\Service\DeviceRegistryService
 * @group bebbo_api_security
 */
class DeviceRegistryServiceTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'bebbo_api_security',
    'key',
    'system',
    'views',
    'user',
    'filter',
  ];

  /**
   * The service under test.
   *
   * @var \Drupal\bebbo_api_security\Service\DeviceRegistryService
   */
  private DeviceRegistryService $registry;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installSchema('bebbo_api_security', [
      'bebbo_api_devices',
      'bebbo_api_refresh_tokens',
      'bebbo_api_challenges',
      'bebbo_api_security_log',
    ]);
    $this->installConfig(['bebbo_api_security']);

    $this->registry = $this->container->get('bebbo_api_security.device_registry');
  }

  /**
   * Helper to insert a device with default fields.
   */
  private function insertDevice(string $device_id, array $overrides = []): int {
    return $this->registry->registerDevice($overrides + [
      'device_id' => $device_id,
      'platform' => 'android',
      'auth_method' => 'play_integrity',
      'status' => 'active',
      'apple_counter' => 0,
      'created' => time(),
      'updated' => time(),
    ]);
  }

  /**
   * Tests registerDevice inserts a record and returns an ID.
   *
   * @covers ::registerDevice
   */
  public function testRegisterDeviceInsertsRecord(): void {
    $id = $this->insertDevice('dev-001');
    $this->assertGreaterThan(0, $id);

    $device = $this->registry->getDevice('dev-001');
    $this->assertNotNull($device);
    $this->assertEquals('dev-001', $device->device_id);
    $this->assertEquals('android', $device->platform);
    $this->assertEquals('active', $device->status);
  }

  /**
   * Tests getDevice returns the device record.
   *
   * @covers ::getDevice
   */
  public function testGetDeviceReturnsDevice(): void {
    $this->insertDevice('dev-002', ['platform' => 'ios', 'auth_method' => 'app_attest']);
    $device = $this->registry->getDevice('dev-002');

    $this->assertNotNull($device);
    $this->assertEquals('ios', $device->platform);
    $this->assertEquals('app_attest', $device->auth_method);
  }

  /**
   * Tests getDevice returns NULL for non-existent device.
   *
   * @covers ::getDevice
   */
  public function testGetDeviceReturnsNullForMissing(): void {
    $this->assertNull($this->registry->getDevice('nonexistent'));
  }

  /**
   * Tests getDeviceByAppleKeyId returns the correct device.
   *
   * @covers ::getDeviceByAppleKeyId
   */
  public function testGetDeviceByAppleKeyId(): void {
    $this->insertDevice('dev-003', [
      'platform' => 'ios',
      'auth_method' => 'app_attest',
      'apple_key_id' => 'abc123keyid',
    ]);

    $device = $this->registry->getDeviceByAppleKeyId('abc123keyid');
    $this->assertNotNull($device);
    $this->assertEquals('dev-003', $device->device_id);
  }

  /**
   * Tests updateDevice modifies specified fields.
   *
   * @covers ::updateDevice
   */
  public function testUpdateDeviceChangesFields(): void {
    $this->insertDevice('dev-004');
    $this->registry->updateDevice('dev-004', [
      'status' => 'suspended',
      'updated' => time() + 100,
    ]);

    $device = $this->registry->getDevice('dev-004');
    $this->assertEquals('suspended', $device->status);
  }

  /**
   * Tests revokeDevice sets status to revoked.
   *
   * @covers ::revokeDevice
   */
  public function testRevokeDeviceSetsStatus(): void {
    $this->insertDevice('dev-005');
    $this->registry->revokeDevice('dev-005');

    $device = $this->registry->getDevice('dev-005');
    $this->assertEquals('revoked', $device->status);
  }

  /**
   * Tests logSecurityEvent inserts audit record.
   *
   * @covers ::logSecurityEvent
   */
  public function testLogSecurityEventInsertsRecord(): void {
    $this->registry->logSecurityEvent('register', 'dev-006', '192.168.1.1', ['foo' => 'bar']);

    $log = $this->container->get('database')
      ->select('bebbo_api_security_log', 'l')
      ->fields('l')
      ->condition('l.device_id', 'dev-006')
      ->execute()
      ->fetchObject();

    $this->assertIsObject($log);
    assert(is_object($log));
    $this->assertEquals('register', $log->event_type);
    $this->assertEquals('192.168.1.1', $log->ip_address);
    $this->assertEquals('{"foo":"bar"}', $log->details);
  }

  /**
   * Helper to insert a challenge row.
   */
  private function insertChallenge(string $device_id, array $overrides = []): string {
    $challenge = $overrides['challenge'] ?? bin2hex(random_bytes(32));
    $this->container->get('database')->insert('bebbo_api_challenges')->fields($overrides + [
      'device_id' => $device_id,
      'challenge' => $challenge,
      'purpose' => 'sideloaded_verify',
      'expires' => time() + 120,
      'used' => 0,
      'created' => time(),
    ])->execute();
    return $challenge;
  }

  /**
   * Tests getActiveChallenge returns the newest unused, unexpired challenge.
   *
   * @covers ::getActiveChallenge
   */
  public function testGetActiveChallengeReturnsNewestUnused(): void {
    $this->insertChallenge('dev-020', ['created' => time() - 60]);
    $newest = $this->insertChallenge('dev-020', ['created' => time()]);

    $row = $this->registry->getActiveChallenge('dev-020');
    $this->assertNotNull($row);
    $this->assertEquals($newest, $row->challenge);
  }

  /**
   * Tests getActiveChallenge skips used and expired challenges.
   *
   * @covers ::getActiveChallenge
   */
  public function testGetActiveChallengeIgnoresUsedAndExpired(): void {
    $this->insertChallenge('dev-021', ['used' => 1]);
    $this->insertChallenge('dev-021', ['expires' => time() - 10]);

    $this->assertNull($this->registry->getActiveChallenge('dev-021'));
    $this->assertNull($this->registry->getActiveChallenge('no-such-device'));
  }

  /**
   * Tests purgeExpired removes expired challenges.
   *
   * @covers ::purgeExpired
   */
  public function testPurgeExpiredCleansUpChallenges(): void {
    $db = $this->container->get('database');
    $db->insert('bebbo_api_challenges')->fields([
      'device_id' => 'dev-007',
      'challenge' => bin2hex(random_bytes(32)),
      'purpose' => 'sideloaded_verify',
      'expires' => time() - 300,
      'used' => 0,
      'created' => time() - 400,
    ])->execute();

    $stats = $this->registry->purgeExpired();
    $this->assertEquals(1, $stats['challenges']);
  }

  /**
   * Tests purgeExpired removes old revoked refresh tokens.
   *
   * @covers ::purgeExpired
   */
  public function testPurgeExpiredCleansUpRevokedTokens(): void {
    $db = $this->container->get('database');
    $db->insert('bebbo_api_refresh_tokens')->fields([
      'device_id' => 'dev-008',
      'token_hash' => hash('sha256', 'old-token'),
      'token_family' => bin2hex(random_bytes(32)),
      'expires' => time() + 86400,
      'revoked' => 1,
      'created' => time() - 700000,
    ])->execute();

    $stats = $this->registry->purgeExpired();
    $this->assertEquals(1, $stats['tokens_revoked']);
  }

  /**
   * Tests purgeExpired removes expired refresh tokens.
   *
   * @covers ::purgeExpired
   */
  public function testPurgeExpiredCleansUpExpiredTokens(): void {
    $db = $this->container->get('database');
    $db->insert('bebbo_api_refresh_tokens')->fields([
      'device_id' => 'dev-009',
      'token_hash' => hash('sha256', 'expired-token'),
      'token_family' => bin2hex(random_bytes(32)),
      'expires' => time() - 100,
      'revoked' => 0,
      'created' => time() - 200,
    ])->execute();

    $stats = $this->registry->purgeExpired();
    $this->assertEquals(1, $stats['tokens_expired']);
  }

  /**
   * Tests purgeExpired preserves non-expired active records.
   *
   * @covers ::purgeExpired
   */
  public function testPurgeExpiredPreservesActiveRecords(): void {
    $db = $this->container->get('database');

    $db->insert('bebbo_api_challenges')->fields([
      'device_id' => 'dev-010',
      'challenge' => bin2hex(random_bytes(32)),
      'purpose' => 'sideloaded_verify',
      'expires' => time() + 300,
      'used' => 0,
      'created' => time(),
    ])->execute();

    $db->insert('bebbo_api_refresh_tokens')->fields([
      'device_id' => 'dev-010',
      'token_hash' => hash('sha256', 'active-token'),
      'token_family' => bin2hex(random_bytes(32)),
      'expires' => time() + 86400,
      'revoked' => 0,
      'created' => time(),
    ])->execute();

    $stats = $this->registry->purgeExpired();
    $this->assertEquals(0, $stats['challenges']);
    $this->assertEquals(0, $stats['tokens_revoked']);
    $this->assertEquals(0, $stats['tokens_expired']);
  }

}
