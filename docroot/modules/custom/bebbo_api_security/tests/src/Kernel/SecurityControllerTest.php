<?php

declare(strict_types=1);

namespace Drupal\Tests\bebbo_api_security\Kernel;

use Drupal\bebbo_api_security\Controller\SecurityController;
use Drupal\key\KeyInterface;
use Drupal\key\KeyRepositoryInterface;
use Drupal\KernelTests\KernelTestBase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Kernel tests for SecurityController endpoint methods.
 *
 * Exercises the sideloaded device flow end-to-end (register -> sign -> verify),
 * refresh token rotation, and input validation — all through the real service
 * layer with only the Key repository mocked (to provide a test RSA key).
 *
 * @coversDefaultClass \Drupal\bebbo_api_security\Controller\SecurityController
 * @group bebbo_api_security
 */
class SecurityControllerTest extends KernelTestBase {

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

    // Generate an RSA key pair and mock the Key repository so JwtService
    // can sign/verify tokens without the Key module entity existing.
    $rsa = openssl_pkey_new([
      'private_key_bits' => 2048,
      'private_key_type' => OPENSSL_KEYTYPE_RSA,
    ]);
    openssl_pkey_export($rsa, $private_pem);

    $key = $this->createMock(KeyInterface::class);
    $key->method('getKeyValue')->willReturn($private_pem);

    $key_repo = $this->createMock(KeyRepositoryInterface::class);
    $key_repo->method('getKey')
      ->with('bebbo_jwt_signing_key')
      ->willReturn($key);
    $this->container->set('key.repository', $key_repo);

    $this->installConfig(['bebbo_api_security']);
  }

  /**
   * Instantiate the controller via its DI factory.
   */
  private function getController(): SecurityController {
    return SecurityController::create($this->container);
  }

  /**
   * Tests successful sideloaded device registration (step 1).
   *
   * @covers ::deviceRegister
   */
  public function testDeviceRegisterSuccess(): void {
    $key = openssl_pkey_new([
      'curve_name' => 'prime256v1',
      'private_key_type' => OPENSSL_KEYTYPE_EC,
    ]);
    $details = openssl_pkey_get_details($key);

    $request = Request::create('/api/security/device/register', 'POST', [], [], [], [], json_encode([
      'device_id' => 'test-001',
      'public_key' => $details['key'],
    ]));

    $response = $this->getController()->deviceRegister($request);
    $this->assertEquals(200, $response->getStatusCode());

    $data = json_decode($response->getContent(), TRUE);
    $this->assertEquals('challenge_issued', $data['status']);
    $this->assertEquals(64, strlen($data['challenge']));
    $this->assertEquals(120, $data['expires_in']);
  }

  /**
   * Tests that device registration rejects an invalid public key.
   *
   * @covers ::deviceRegister
   */
  public function testDeviceRegisterRejectsInvalidKey(): void {
    $request = Request::create('/api/security/device/register', 'POST', [], [], [], [], json_encode([
      'device_id' => 'test-002',
      'public_key' => 'not-a-valid-key',
    ]));

    $response = $this->getController()->deviceRegister($request);
    $this->assertEquals(400, $response->getStatusCode());
  }

  /**
   * Tests the full sideloaded flow: register -> sign -> verify -> tokens.
   *
   * @covers ::deviceRegister
   * @covers ::deviceVerify
   */
  public function testDeviceVerifyFullFlow(): void {
    $key = openssl_pkey_new([
      'curve_name' => 'prime256v1',
      'private_key_type' => OPENSSL_KEYTYPE_EC,
    ]);
    openssl_pkey_export($key, $private_pem);
    $details = openssl_pkey_get_details($key);

    // Step 1: Register -- obtain a challenge.
    $request = Request::create('/api/security/device/register', 'POST', [], [], [], [], json_encode([
      'device_id' => 'test-003',
      'public_key' => $details['key'],
    ]));
    $response = $this->getController()->deviceRegister($request);
    $data = json_decode($response->getContent(), TRUE);
    $challenge = $data['challenge'];

    // Step 2: Sign the challenge with the EC private key.
    $challenge_bytes = hex2bin($challenge);
    openssl_sign($challenge_bytes, $signature, $private_pem, OPENSSL_ALGO_SHA256);

    // Step 3: Verify -- present the signed challenge.
    $request = Request::create('/api/security/device/verify', 'POST', [], [], [], [], json_encode([
      'device_id' => 'test-003',
      'challenge' => $challenge,
      'signature' => base64_encode($signature),
    ]));
    $response = $this->getController()->deviceVerify($request);
    $this->assertEquals(200, $response->getStatusCode());

    $data = json_decode($response->getContent(), TRUE);
    $this->assertEquals('verified', $data['status']);
    $this->assertNotEmpty($data['access_token']);
    $this->assertEquals('Bearer', $data['token_type']);
    $this->assertNotEmpty($data['refresh_token']);
  }

  /**
   * Tests refresh token rotation returns new tokens.
   *
   * @covers ::refresh
   */
  public function testRefreshTokenFlow(): void {
    // Register + verify a device to obtain tokens.
    $key = openssl_pkey_new([
      'curve_name' => 'prime256v1',
      'private_key_type' => OPENSSL_KEYTYPE_EC,
    ]);
    openssl_pkey_export($key, $private_pem);
    $details = openssl_pkey_get_details($key);

    $request = Request::create('/api/security/device/register', 'POST', [], [], [], [], json_encode([
      'device_id' => 'test-004',
      'public_key' => $details['key'],
    ]));
    $reg_data = json_decode(
      $this->getController()->deviceRegister($request)->getContent(),
      TRUE,
    );

    $challenge_bytes = hex2bin($reg_data['challenge']);
    openssl_sign($challenge_bytes, $signature, $private_pem, OPENSSL_ALGO_SHA256);

    $request = Request::create('/api/security/device/verify', 'POST', [], [], [], [], json_encode([
      'device_id' => 'test-004',
      'challenge' => $reg_data['challenge'],
      'signature' => base64_encode($signature),
    ]));
    $verify_data = json_decode(
      $this->getController()->deviceVerify($request)->getContent(),
      TRUE,
    );

    // Now refresh using the refresh token.
    $request = Request::create('/api/security/refresh', 'POST', [], [], [], [], json_encode([
      'refresh_token' => $verify_data['refresh_token'],
    ]));
    $response = $this->getController()->refresh($request);
    $this->assertEquals(200, $response->getStatusCode());

    $data = json_decode($response->getContent(), TRUE);
    $this->assertEquals('refreshed', $data['status']);
    $this->assertNotEmpty($data['access_token']);
    $this->assertNotEmpty($data['refresh_token']);
    // Rotation must issue a different refresh token.
    $this->assertNotEquals($verify_data['refresh_token'], $data['refresh_token']);
  }

  /**
   * Tests that /refresh throttles at the configured refresh_rate_limit.
   *
   * @covers ::refresh
   */
  public function testRefreshRespectsConfiguredRateLimit(): void {
    $this->config('bebbo_api_security.settings')
      ->set('refresh_rate_limit', 1)
      ->save();

    $refresh_token = $this->provisionDevice('test-006');

    // First call is within the limit of 1.
    $response = $this->refreshWith($refresh_token);
    $this->assertEquals(200, $response->getStatusCode());
    $rotated = json_decode($response->getContent(), TRUE)['refresh_token'];

    // Second call exceeds it, even though the rotated token is valid.
    $response = $this->refreshWith($rotated);
    $this->assertEquals(429, $response->getStatusCode());
    $this->assertEquals(
      'rate_limited',
      json_decode($response->getContent(), TRUE)['error'],
    );

    // Raising the configured value moves the trip point: the same device is
    // allowed through again without its flood record being cleared.
    $this->config('bebbo_api_security.settings')
      ->set('refresh_rate_limit', 5)
      ->save();
    $response = $this->refreshWith($rotated);
    $this->assertEquals(200, $response->getStatusCode());
  }

  /**
   * Tests that an unknown refresh token is throttled by client IP.
   *
   * @covers ::refresh
   */
  public function testRefreshThrottlesUnknownTokensByIp(): void {
    $this->config('bebbo_api_security.settings')
      ->set('refresh_rate_limit', 1)
      ->save();

    // Unknown tokens resolve to no device, so the IP fallback must apply.
    $this->assertEquals(401, $this->refreshWith('deadbeef')->getStatusCode());
    $this->assertEquals(429, $this->refreshWith('cafebabe')->getStatusCode());
  }

  /**
   * Run the sideloaded flow for a device and return its refresh token.
   *
   * @param string $device_id
   *   Device identifier to register and verify.
   *
   * @return string
   *   The issued refresh token.
   */
  private function provisionDevice(string $device_id): string {
    $key = openssl_pkey_new([
      'curve_name' => 'prime256v1',
      'private_key_type' => OPENSSL_KEYTYPE_EC,
    ]);
    openssl_pkey_export($key, $private_pem);
    $details = openssl_pkey_get_details($key);

    $request = Request::create('/api/security/device/register', 'POST', [], [], [], [], json_encode([
      'device_id' => $device_id,
      'public_key' => $details['key'],
    ]));
    $reg_data = json_decode(
      $this->getController()->deviceRegister($request)->getContent(),
      TRUE,
    );

    openssl_sign(hex2bin($reg_data['challenge']), $signature, $private_pem, OPENSSL_ALGO_SHA256);

    $request = Request::create('/api/security/device/verify', 'POST', [], [], [], [], json_encode([
      'device_id' => $device_id,
      'challenge' => $reg_data['challenge'],
      'signature' => base64_encode($signature),
    ]));
    $verify_data = json_decode(
      $this->getController()->deviceVerify($request)->getContent(),
      TRUE,
    );

    return $verify_data['refresh_token'];
  }

  /**
   * Call the refresh endpoint with the given token.
   *
   * @param string $refresh_token
   *   Token to present.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   The controller response.
   */
  private function refreshWith(string $refresh_token): JsonResponse {
    $request = Request::create('/api/security/refresh', 'POST', [], [], [], [], json_encode([
      'refresh_token' => $refresh_token,
    ]));
    return $this->getController()->refresh($request);
  }

  /**
   * Tests that the register endpoint rejects invalid JSON.
   *
   * @covers ::register
   */
  public function testRegisterRejectsInvalidJson(): void {
    $request = Request::create('/api/security/register', 'POST', [], [], [], [], 'not json');
    $response = $this->getController()->register($request);
    $this->assertEquals(400, $response->getStatusCode());
  }

  /**
   * Tests that the register endpoint rejects an unsupported platform.
   *
   * @covers ::register
   */
  public function testRegisterRejectsInvalidPlatform(): void {
    $request = Request::create('/api/security/register', 'POST', [], [], [], [], json_encode([
      'platform' => 'windows',
      'device_id' => 'test-005',
    ]));
    $response = $this->getController()->register($request);
    $this->assertEquals(400, $response->getStatusCode());
  }

}
