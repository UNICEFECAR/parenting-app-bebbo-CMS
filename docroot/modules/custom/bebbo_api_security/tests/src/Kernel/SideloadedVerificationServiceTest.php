<?php

declare(strict_types=1);

namespace Drupal\Tests\bebbo_api_security\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\bebbo_api_security\Service\SideloadedVerificationService;

/**
 * @coversDefaultClass \Drupal\bebbo_api_security\Service\SideloadedVerificationService
 * @group bebbo_api_security
 */
class SideloadedVerificationServiceTest extends KernelTestBase {

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
   * The sideloaded verification service.
   *
   * @var \Drupal\bebbo_api_security\Service\SideloadedVerificationService
   */
  private SideloadedVerificationService $service;

  /**
   * PEM-encoded EC private key for test signing.
   *
   * @var string
   */
  private string $privateKeyPem;

  /**
   * PEM-encoded EC public key for test verification.
   *
   * @var string
   */
  private string $publicKeyPem;

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

    $this->service = $this->container->get('bebbo_api_security.sideloaded_verification');

    // Generate EC P-256 key pair for testing.
    $key = openssl_pkey_new([
      'curve_name' => 'prime256v1',
      'private_key_type' => OPENSSL_KEYTYPE_EC,
    ]);
    $privatePem = '';
    openssl_pkey_export($key, $privatePem);
    $this->privateKeyPem = $privatePem;
    $details = openssl_pkey_get_details($key);
    $this->publicKeyPem = $details['key'];
  }

  /**
   * Tests that registerDevice returns a 64-char hex challenge.
   *
   * @covers ::registerDevice
   */
  public function testRegisterDeviceReturnsChallenge(): void {
    $challenge = $this->service->registerDevice('test-device-001', $this->publicKeyPem);
    $this->assertNotEmpty($challenge);
    $this->assertEquals(64, strlen($challenge));

    $device = \Drupal::database()->select('bebbo_api_devices', 'd')
      ->fields('d')
      ->condition('d.device_id', 'test-device-001')
      ->execute()
      ->fetchObject();
    $this->assertIsObject($device);
    assert(is_object($device));
    $this->assertEquals('pending', $device->status);
    $this->assertEquals('sideloaded', $device->platform);
  }

  /**
   * Tests successful ECDSA signature verification activates the device.
   *
   * @covers ::verify
   */
  public function testVerifyValidSignature(): void {
    $challenge = $this->service->registerDevice('test-device-002', $this->publicKeyPem);

    $challenge_bytes = hex2bin($challenge);
    openssl_sign($challenge_bytes, $signature, $this->privateKeyPem, OPENSSL_ALGO_SHA256);
    $signature_b64 = base64_encode($signature);

    $result = $this->service->verify('test-device-002', $challenge, $signature_b64);
    $this->assertTrue($result);

    $device = \Drupal::database()->select('bebbo_api_devices', 'd')
      ->fields('d')
      ->condition('d.device_id', 'test-device-002')
      ->execute()
      ->fetchObject();
    $this->assertIsObject($device);
    assert(is_object($device));
    $this->assertEquals('active', $device->status);
  }

  /**
   * Tests that an invalid signature returns FALSE.
   *
   * @covers ::verify
   */
  public function testVerifyInvalidSignature(): void {
    $challenge = $this->service->registerDevice('test-device-003', $this->publicKeyPem);

    $result = $this->service->verify('test-device-003', $challenge, base64_encode('invalid'));
    $this->assertFalse($result);
  }

  /**
   * Tests that an expired challenge throws RuntimeException.
   *
   * @covers ::verify
   */
  public function testVerifyExpiredChallenge(): void {
    $challenge = $this->service->registerDevice('test-device-004', $this->publicKeyPem);

    \Drupal::database()->update('bebbo_api_challenges')
      ->fields(['expires' => time() - 1])
      ->condition('challenge', $challenge)
      ->execute();

    $challenge_bytes = hex2bin($challenge);
    openssl_sign($challenge_bytes, $signature, $this->privateKeyPem, OPENSSL_ALGO_SHA256);

    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('Challenge expired');
    $this->service->verify('test-device-004', $challenge, base64_encode($signature));
  }

  /**
   * Tests that reusing a challenge throws RuntimeException.
   *
   * @covers ::verify
   */
  public function testVerifyChallengeReuse(): void {
    $challenge = $this->service->registerDevice('test-device-005', $this->publicKeyPem);

    $challenge_bytes = hex2bin($challenge);
    openssl_sign($challenge_bytes, $signature, $this->privateKeyPem, OPENSSL_ALGO_SHA256);
    $signature_b64 = base64_encode($signature);

    $this->service->verify('test-device-005', $challenge, $signature_b64);

    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('Challenge already used');
    $this->service->verify('test-device-005', $challenge, $signature_b64);
  }

  /**
   * Tests that a non-EC key is rejected during registration.
   *
   * @covers ::registerDevice
   */
  public function testRejectNonEcKey(): void {
    $rsa = openssl_pkey_new([
      'private_key_bits' => 2048,
      'private_key_type' => OPENSSL_KEYTYPE_RSA,
    ]);
    $details = openssl_pkey_get_details($rsa);
    $rsa_pub = $details['key'];

    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage('EC P-256');
    $this->service->registerDevice('test-device-rsa', $rsa_pub);
  }

}
