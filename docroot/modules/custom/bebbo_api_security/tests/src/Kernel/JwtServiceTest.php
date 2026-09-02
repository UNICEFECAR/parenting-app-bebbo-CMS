<?php

declare(strict_types=1);

namespace Drupal\Tests\bebbo_api_security\Kernel;

use Drupal\bebbo_api_security\Service\JwtService;
use Drupal\key\KeyInterface;
use Drupal\key\KeyRepositoryInterface;
use Drupal\KernelTests\KernelTestBase;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

/**
 * Kernel tests for JwtService token lifecycle.
 *
 * @coversDefaultClass \Drupal\bebbo_api_security\Service\JwtService
 * @group bebbo_api_security
 */
class JwtServiceTest extends KernelTestBase {

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
   * @var \Drupal\bebbo_api_security\Service\JwtService
   */
  private JwtService $service;

  /**
   * RSA private key PEM.
   *
   * @var string
   */
  private string $privateKeyPem;

  /**
   * RSA public key PEM.
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
    $this->installConfig(['bebbo_api_security']);

    $rsa = openssl_pkey_new([
      'private_key_bits' => 2048,
      'private_key_type' => OPENSSL_KEYTYPE_RSA,
    ]);
    $pem = '';
    openssl_pkey_export($rsa, $pem);
    $this->privateKeyPem = $pem;
    $details = openssl_pkey_get_details($rsa);
    $this->publicKeyPem = $details['key'];

    $key = $this->createMock(KeyInterface::class);
    $key->method('getKeyValue')->willReturn($this->privateKeyPem);

    $keyRepo = $this->createMock(KeyRepositoryInterface::class);
    $keyRepo->method('getKey')
      ->with('bebbo_jwt_signing_key')
      ->willReturn($key);
    $this->container->set('key.repository', $keyRepo);

    $this->service = $this->container->get('bebbo_api_security.jwt_service');
  }

  /**
   * Tests that createToken produces a valid RS256 JWT with expected claims.
   *
   * @covers ::createToken
   */
  public function testCreateTokenReturnsValidJwt(): void {
    $token = $this->service->createToken('device-001', 'android', 'play_integrity');

    $decoded = JWT::decode($token, new Key($this->publicKeyPem, 'RS256'));
    $payload = (array) $decoded;

    $this->assertEquals('bebbo-cms', $payload['iss']);
    $this->assertEquals('device-001', $payload['sub']);
    $this->assertEquals('android', $payload['platform']);
    $this->assertEquals('play_integrity', $payload['auth_method']);
    $this->assertArrayHasKey('jti', $payload);
    $this->assertEquals(32, strlen($payload['jti']));
    $this->assertLessThanOrEqual(time() + 3600, $payload['exp']);
    $this->assertGreaterThanOrEqual(time(), $payload['iat']);
  }

  /**
   * Tests that validateToken accepts a freshly created token.
   *
   * @covers ::validateToken
   */
  public function testValidateTokenAcceptsValidToken(): void {
    $token = $this->service->createToken('device-002', 'ios', 'app_attest');
    $payload = $this->service->validateToken($token);

    $this->assertNotNull($payload);
    $this->assertEquals('device-002', $payload['sub']);
    $this->assertEquals('ios', $payload['platform']);
    $this->assertEquals('app_attest', $payload['auth_method']);
  }

  /**
   * Tests that validateToken returns NULL for an expired token.
   *
   * @covers ::validateToken
   */
  public function testValidateTokenRejectsExpiredToken(): void {
    $payload = [
      'iss' => 'bebbo-cms',
      'sub' => 'device-003',
      'iat' => time() - 7200,
      'exp' => time() - 3600,
      'platform' => 'android',
      'auth_method' => 'play_integrity',
      'jti' => bin2hex(random_bytes(16)),
    ];
    $expired_token = JWT::encode($payload, $this->privateKeyPem, 'RS256');

    $result = $this->service->validateToken($expired_token);
    $this->assertNull($result);
  }

  /**
   * Tests that validateToken rejects a token with a tampered payload.
   *
   * @covers ::validateToken
   */
  public function testValidateTokenRejectsTamperedPayload(): void {
    $token = $this->service->createToken('device-004', 'android', 'play_integrity');

    $parts = explode('.', $token);
    $payload = json_decode(base64_decode($parts[1]), TRUE);
    $payload['sub'] = 'evil-device';
    $parts[1] = rtrim(base64_encode(json_encode($payload)), '=');
    $tampered = implode('.', $parts);

    $result = $this->service->validateToken($tampered);
    $this->assertNull($result);
  }

  /**
   * Tests that validateToken rejects a token signed with a different key.
   *
   * @covers ::validateToken
   */
  public function testValidateTokenRejectsWrongKey(): void {
    $other_rsa = openssl_pkey_new([
      'private_key_bits' => 2048,
      'private_key_type' => OPENSSL_KEYTYPE_RSA,
    ]);
    $other_pem = '';
    openssl_pkey_export($other_rsa, $other_pem);

    $payload = [
      'iss' => 'bebbo-cms',
      'sub' => 'device-005',
      'iat' => time(),
      'exp' => time() + 3600,
      'platform' => 'android',
      'auth_method' => 'play_integrity',
      'jti' => bin2hex(random_bytes(16)),
    ];
    $token = JWT::encode($payload, $other_pem, 'RS256');

    $result = $this->service->validateToken($token);
    $this->assertNull($result);
  }

  /**
   * Tests that validateToken returns NULL for a malformed string.
   *
   * @covers ::validateToken
   */
  public function testValidateTokenRejectsMalformedString(): void {
    $result = $this->service->validateToken('not.a.jwt');
    $this->assertNull($result);
  }

  /**
   * Tests that createToken throws when the Key entity is missing.
   *
   * @covers ::createToken
   */
  public function testCreateTokenThrowsWhenKeyMissing(): void {
    $keyRepo = $this->createMock(KeyRepositoryInterface::class);
    $keyRepo->method('getKey')->willReturn(NULL);

    $service = new JwtService(
      $keyRepo,
      $this->container->get('database'),
      $this->container->get('config.factory'),
      $this->container->get('logger.channel.bebbo_api_security'),
    );

    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('JWT signing key not configured');
    $service->createToken('device', 'android', 'play_integrity');
  }

  /**
   * Tests that createToken throws when the Key entity returns empty value.
   *
   * @covers ::createToken
   */
  public function testCreateTokenThrowsWhenKeyEmpty(): void {
    $key = $this->createMock(KeyInterface::class);
    $key->method('getKeyValue')->willReturn('');

    $keyRepo = $this->createMock(KeyRepositoryInterface::class);
    $keyRepo->method('getKey')->willReturn($key);

    $service = new JwtService(
      $keyRepo,
      $this->container->get('database'),
      $this->container->get('config.factory'),
      $this->container->get('logger.channel.bebbo_api_security'),
    );

    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('JWT signing key is empty');
    $service->createToken('device', 'android', 'play_integrity');
  }

  /**
   * Tests that refresh rotates the token by default (refresh_rotation_enabled).
   *
   * @covers ::refreshTokens
   */
  public function testRefreshRotatesByDefault(): void {
    $this->insertDevice('device-006', 'android', 'play_integrity');
    $created = $this->service->createRefreshToken('device-006');

    $result = $this->service->refreshTokens($created['token']);

    $this->assertNotNull($result);
    $this->assertNotEquals($created['token'], $result['refresh_token']);
    $this->assertNotNull($this->service->validateToken($result['access_token']));

    // The presented (now rotated) token must be revoked: reusing it triggers
    // replay detection and returns NULL.
    $this->assertNull($this->service->refreshTokens($created['token']));
  }

  /**
   * Tests that refresh reuses the token when rotation is disabled.
   *
   * @covers ::refreshTokens
   */
  public function testRefreshWithoutRotationReusesToken(): void {
    $this->config('bebbo_api_security.settings')
      ->set('refresh_rotation_enabled', FALSE)
      ->save();

    $this->insertDevice('device-007', 'ios', 'app_attest');
    $created = $this->service->createRefreshToken('device-007');

    $result = $this->service->refreshTokens($created['token']);

    $this->assertNotNull($result);
    // Same refresh token returned, not a rotated one.
    $this->assertEquals($created['token'], $result['refresh_token']);
    $this->assertNotNull($this->service->validateToken($result['access_token']));

    // The token is still valid and reusable (not revoked).
    $this->assertNotNull($this->service->refreshTokens($created['token']));
  }

  /**
   * Inserts a minimal active device row.
   */
  private function insertDevice(string $device_id, string $platform, string $auth_method): void {
    $this->container->get('database')->insert('bebbo_api_devices')->fields([
      'device_id' => $device_id,
      'platform' => $platform,
      'auth_method' => $auth_method,
      'status' => 'active',
      'created' => time(),
      'updated' => time(),
    ])->execute();
  }

}
