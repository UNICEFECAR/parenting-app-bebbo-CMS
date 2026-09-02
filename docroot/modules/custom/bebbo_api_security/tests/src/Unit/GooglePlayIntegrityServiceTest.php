<?php

declare(strict_types=1);

namespace Drupal\Tests\bebbo_api_security\Unit;

use Drupal\bebbo_api_security\Service\GooglePlayIntegrityService;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\key\KeyRepositoryInterface;
use GuzzleHttp\ClientInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * @coversDefaultClass \Drupal\bebbo_api_security\Service\GooglePlayIntegrityService
 * @group bebbo_api_security
 */
class GooglePlayIntegrityServiceTest extends TestCase {

  /**
   * The service under test.
   *
   * @var \Drupal\bebbo_api_security\Service\GooglePlayIntegrityService
   */
  private GooglePlayIntegrityService $service;

  /**
   * Mock config object for bebbo_api_security.settings.
   *
   * @var \Drupal\Core\Config\ImmutableConfig|\PHPUnit\Framework\MockObject\MockObject
   */
  private $mockConfig;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $httpClient = $this->createMock(ClientInterface::class);
    $keyRepository = $this->createMock(KeyRepositoryInterface::class);
    $configFactory = $this->createMock(ConfigFactoryInterface::class);
    $logger = $this->createMock(LoggerInterface::class);

    $this->mockConfig = $this->createMock(ImmutableConfig::class);
    $this->mockConfig->method('get')->willReturnMap([
      ['google_package_name', 'org.unicef.bebbo'],
      ['google_project_number', '123456789'],
      ['google_verdict_freshness_seconds', 600],
    ]);
    $configFactory->method('get')
      ->with('bebbo_api_security.settings')
      ->willReturn($this->mockConfig);

    $this->service = new GooglePlayIntegrityService(
      $httpClient,
      $keyRepository,
      $configFactory,
      $logger,
    );
  }

  /**
   * @covers ::verifyVerdicts
   */
  public function testVerifyVerdictsAcceptsValidPayload(): void {
    $device_id = 'test-device';
    $request_hash = rtrim(strtr(base64_encode(hash('sha256', $device_id, TRUE)), '+/', '-_'), '=');

    $payload = [
      'tokenPayloadExternal' => [
        'requestDetails' => [
          'requestPackageName' => 'org.unicef.bebbo',
          'timestampMillis' => (string) (time() * 1000),
          'requestHash' => $request_hash,
        ],
        'deviceIntegrity' => [
          'deviceRecognitionVerdict' => ['MEETS_DEVICE_INTEGRITY'],
        ],
        'appIntegrity' => [
          'appRecognitionVerdict' => 'PLAY_RECOGNIZED',
        ],
      ],
    ];

    $method = new \ReflectionMethod($this->service, 'verifyVerdicts');
    $method->setAccessible(TRUE);

    // Should not throw.
    $method->invoke($this->service, $payload, $device_id, $request_hash);
    $this->assertTrue(TRUE);
  }

  /**
   * @covers ::verifyVerdicts
   */
  public function testVerifyVerdictsRejectsWrongPackage(): void {
    $device_id = 'test-device';
    $request_hash = rtrim(strtr(base64_encode(hash('sha256', $device_id, TRUE)), '+/', '-_'), '=');

    $payload = [
      'tokenPayloadExternal' => [
        'requestDetails' => [
          'requestPackageName' => 'com.evil.app',
          'timestampMillis' => (string) (time() * 1000),
          'requestHash' => $request_hash,
        ],
        'deviceIntegrity' => [
          'deviceRecognitionVerdict' => ['MEETS_DEVICE_INTEGRITY'],
        ],
        'appIntegrity' => [
          'appRecognitionVerdict' => 'PLAY_RECOGNIZED',
        ],
      ],
    ];

    $method = new \ReflectionMethod($this->service, 'verifyVerdicts');
    $method->setAccessible(TRUE);

    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('Package name mismatch');
    $method->invoke($this->service, $payload, $device_id, $request_hash);
  }

  /**
   * @covers ::verifyVerdicts
   */
  public function testVerifyVerdictsRejectsStaleToken(): void {
    $device_id = 'test-device';
    $request_hash = rtrim(strtr(base64_encode(hash('sha256', $device_id, TRUE)), '+/', '-_'), '=');

    $payload = [
      'tokenPayloadExternal' => [
        'requestDetails' => [
          'requestPackageName' => 'org.unicef.bebbo',
          'timestampMillis' => (string) ((time() - 700) * 1000),
          'requestHash' => $request_hash,
        ],
        'deviceIntegrity' => [
          'deviceRecognitionVerdict' => ['MEETS_DEVICE_INTEGRITY'],
        ],
        'appIntegrity' => [
          'appRecognitionVerdict' => 'PLAY_RECOGNIZED',
        ],
      ],
    ];

    $method = new \ReflectionMethod($this->service, 'verifyVerdicts');
    $method->setAccessible(TRUE);

    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('too old');
    $method->invoke($this->service, $payload, $device_id, $request_hash);
  }

  /**
   * @covers ::verifyVerdicts
   */
  public function testVerifyVerdictsRejectsRootedDevice(): void {
    $device_id = 'test-device';
    $request_hash = rtrim(strtr(base64_encode(hash('sha256', $device_id, TRUE)), '+/', '-_'), '=');

    $payload = [
      'tokenPayloadExternal' => [
        'requestDetails' => [
          'requestPackageName' => 'org.unicef.bebbo',
          'timestampMillis' => (string) (time() * 1000),
          'requestHash' => $request_hash,
        ],
        'deviceIntegrity' => [
          'deviceRecognitionVerdict' => [],
        ],
        'appIntegrity' => [
          'appRecognitionVerdict' => 'PLAY_RECOGNIZED',
        ],
      ],
    ];

    $method = new \ReflectionMethod($this->service, 'verifyVerdicts');
    $method->setAccessible(TRUE);

    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('does not meet integrity requirements');
    $method->invoke($this->service, $payload, $device_id, $request_hash);
  }

  /**
   * @covers ::verifyVerdicts
   */
  public function testVerifyVerdictsRejectsUnrecognizedApp(): void {
    $device_id = 'test-device';
    $request_hash = rtrim(strtr(base64_encode(hash('sha256', $device_id, TRUE)), '+/', '-_'), '=');

    $payload = [
      'tokenPayloadExternal' => [
        'requestDetails' => [
          'requestPackageName' => 'org.unicef.bebbo',
          'timestampMillis' => (string) (time() * 1000),
          'requestHash' => $request_hash,
        ],
        'deviceIntegrity' => [
          'deviceRecognitionVerdict' => ['MEETS_DEVICE_INTEGRITY'],
        ],
        'appIntegrity' => [
          'appRecognitionVerdict' => 'UNEVALUATED',
        ],
      ],
    ];

    $method = new \ReflectionMethod($this->service, 'verifyVerdicts');
    $method->setAccessible(TRUE);

    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('not recognized by Play Store');
    $method->invoke($this->service, $payload, $device_id, $request_hash);
  }

  /**
   * Build a payload that passes every check except app recognition.
   */
  private function buildUnrecognizedVersionPayload(string $nonce): array {
    return [
      'tokenPayloadExternal' => [
        'requestDetails' => [
          'requestPackageName' => 'org.unicef.bebbo',
          'timestampMillis' => (string) (time() * 1000),
          'nonce' => $nonce,
        ],
        'deviceIntegrity' => [
          'deviceRecognitionVerdict' => ['MEETS_DEVICE_INTEGRITY'],
        ],
        'appIntegrity' => [
          'appRecognitionVerdict' => 'UNRECOGNIZED_VERSION',
        ],
      ],
    ];
  }

  /**
   * @covers ::verifyVerdicts
   */
  public function testVerifyVerdictsRejectsUnrecognizedVersionByDefault(): void {
    $nonce = bin2hex(random_bytes(32));
    $payload = $this->buildUnrecognizedVersionPayload($nonce);

    $method = new \ReflectionMethod($this->service, 'verifyVerdicts');
    $method->setAccessible(TRUE);

    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('not recognized by Play Store');
    $method->invoke($this->service, $payload, 'test-device', $nonce);
  }

  /**
   * @covers ::verifyVerdicts
   */
  public function testVerifyVerdictsAcceptsUnrecognizedVersionWhenToggled(): void {
    $config = $this->createMock(ImmutableConfig::class);
    $config->method('get')->willReturnMap([
      ['google_package_name', 'org.unicef.bebbo'],
      ['google_verdict_freshness_seconds', 600],
      ['google_allow_unrecognized_version', TRUE],
    ]);
    $configFactory = $this->createMock(ConfigFactoryInterface::class);
    $configFactory->method('get')
      ->with('bebbo_api_security.settings')
      ->willReturn($config);

    $logger = $this->createMock(LoggerInterface::class);
    $logger->expects($this->once())->method('warning');

    $service = new GooglePlayIntegrityService(
      $this->createMock(ClientInterface::class),
      $this->createMock(KeyRepositoryInterface::class),
      $configFactory,
      $logger,
    );

    $nonce = bin2hex(random_bytes(32));
    $payload = $this->buildUnrecognizedVersionPayload($nonce);

    $method = new \ReflectionMethod($service, 'verifyVerdicts');
    $method->setAccessible(TRUE);

    // Should not throw.
    $method->invoke($service, $payload, 'test-device', $nonce);
    $this->assertTrue(TRUE);
  }

  /**
   * @covers ::verifyVerdicts
   */
  public function testVerifyVerdictsRejectsWrongRequestHash(): void {
    $device_id = 'test-device';
    $request_hash = rtrim(strtr(base64_encode(hash('sha256', $device_id, TRUE)), '+/', '-_'), '=');

    $payload = [
      'tokenPayloadExternal' => [
        'requestDetails' => [
          'requestPackageName' => 'org.unicef.bebbo',
          'timestampMillis' => (string) (time() * 1000),
          'requestHash' => 'AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA',
        ],
        'deviceIntegrity' => [
          'deviceRecognitionVerdict' => ['MEETS_DEVICE_INTEGRITY'],
        ],
        'appIntegrity' => [
          'appRecognitionVerdict' => 'PLAY_RECOGNIZED',
        ],
      ],
    ];

    $method = new \ReflectionMethod($this->service, 'verifyVerdicts');
    $method->setAccessible(TRUE);

    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('requestHash mismatch');
    $method->invoke($this->service, $payload, $device_id, $request_hash);
  }

  /**
   * @covers ::verifyVerdicts
   */
  public function testVerifyVerdictsAcceptsNonceBasedHash(): void {
    $nonce = 'challenge-3372ac375291-test';
    $request_hash = rtrim(strtr(base64_encode(hash('sha256', $nonce, TRUE)), '+/', '-_'), '=');

    $payload = [
      'tokenPayloadExternal' => [
        'requestDetails' => [
          'requestPackageName' => 'org.unicef.bebbo',
          'timestampMillis' => (string) (time() * 1000),
          'requestHash' => $request_hash,
        ],
        'deviceIntegrity' => [
          'deviceRecognitionVerdict' => ['MEETS_DEVICE_INTEGRITY'],
        ],
        'appIntegrity' => [
          'appRecognitionVerdict' => 'PLAY_RECOGNIZED',
        ],
      ],
    ];

    $method = new \ReflectionMethod($this->service, 'verifyVerdicts');
    $method->setAccessible(TRUE);

    $method->invoke($this->service, $payload, 'some-device-id', $request_hash);
    $this->assertTrue(TRUE);
  }

  /**
   * @covers ::verifyVerdicts
   */
  public function testVerifyVerdictsRejectsMissingRequestHash(): void {
    $device_id = 'test-device';
    $request_hash = rtrim(strtr(base64_encode(hash('sha256', $device_id, TRUE)), '+/', '-_'), '=');

    $payload = [
      'tokenPayloadExternal' => [
        'requestDetails' => [
          'requestPackageName' => 'org.unicef.bebbo',
          'timestampMillis' => (string) (time() * 1000),
        ],
        'deviceIntegrity' => [
          'deviceRecognitionVerdict' => ['MEETS_DEVICE_INTEGRITY'],
        ],
        'appIntegrity' => [
          'appRecognitionVerdict' => 'PLAY_RECOGNIZED',
        ],
      ],
    ];

    $method = new \ReflectionMethod($this->service, 'verifyVerdicts');
    $method->setAccessible(TRUE);

    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('requestHash mismatch');
    $method->invoke($this->service, $payload, $device_id, $request_hash);
  }

}
