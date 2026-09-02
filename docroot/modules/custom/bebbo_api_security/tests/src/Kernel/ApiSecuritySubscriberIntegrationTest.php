<?php

declare(strict_types=1);

namespace Drupal\Tests\bebbo_api_security\Kernel;

use Drupal\bebbo_api_security\EventSubscriber\ApiSecuritySubscriber;
use Drupal\key\KeyInterface;
use Drupal\key\KeyRepositoryInterface;
use Drupal\KernelTests\KernelTestBase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/**
 * Kernel integration tests for ApiSecuritySubscriber.
 *
 * Tests all three enforcement modes (disabled, grace_period, enforced),
 * the dev bypass feature, and protection of both /v2/api/ and
 * /api/check-update/ route prefixes — using a real JWT issued by JwtService.
 *
 * @coversDefaultClass \Drupal\bebbo_api_security\EventSubscriber\ApiSecuritySubscriber
 * @group bebbo_api_security
 */
class ApiSecuritySubscriberIntegrationTest extends KernelTestBase {

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
   * A valid JWT created in setUp for use in tests.
   *
   * @var string
   */
  private string $validJwt;

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

    // Generate a throw-away RSA key pair so JwtService can sign tokens.
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

    /** @var \Drupal\bebbo_api_security\Service\JwtService $jwtService */
    $jwtService = $this->container->get('bebbo_api_security.jwt_service');
    $this->validJwt = $jwtService->createToken(
      'integration-test-device',
      'sideloaded',
      'challenge_response'
    );
  }

  /**
   * Build a RequestEvent for the given path, method, IP, and optional bearer.
   *
   * @param string $path
   *   The request path.
   * @param string $ip
   *   The client IP address.
   * @param string|null $bearerToken
   *   Optional JWT to attach as Authorization: Bearer.
   *
   * @return \Symfony\Component\HttpKernel\Event\RequestEvent
   *   The constructed event.
   */
  private function buildEvent(string $path, string $ip = '127.0.0.1', ?string $bearerToken = NULL): RequestEvent {
    $server = ['REMOTE_ADDR' => $ip];
    $request = Request::create($path, 'GET', [], [], [], $server);
    if ($bearerToken !== NULL) {
      $request->headers->set('Authorization', "Bearer {$bearerToken}");
    }
    $kernel = $this->createMock(HttpKernelInterface::class);
    return new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST);
  }

  /**
   * Get the subscriber under test from the service container.
   *
   * @return \Drupal\bebbo_api_security\EventSubscriber\ApiSecuritySubscriber
   *   The event subscriber service.
   */
  private function getSubscriber(): ApiSecuritySubscriber {
    return $this->container->get('bebbo_api_security.request_subscriber');
  }

  /**
   * Tests that disabled mode passes all requests without a response.
   *
   * @covers ::onRequest
   */
  public function testDisabledModeAllowsAll(): void {
    // Default install config has enforcement_mode = disabled.
    $event = $this->buildEvent('/v2/api/articles/en');
    $this->getSubscriber()->onRequest($event);
    $this->assertNull($event->getResponse());
  }

  /**
   * Tests that enforced mode blocks requests with no token.
   *
   * @covers ::onRequest
   */
  public function testEnforcedBlocksNoToken(): void {
    $this->config('bebbo_api_security.settings')
      ->set('enforcement_mode', 'enforced')
      ->save();

    $event = $this->buildEvent('/v2/api/articles/en');
    $this->getSubscriber()->onRequest($event);

    $response = $event->getResponse();
    $this->assertNotNull($response);
    $this->assertEquals(401, $response->getStatusCode());
  }

  /**
   * Tests that enforced mode allows a valid JWT and sets bebbo_device_id.
   *
   * @covers ::onRequest
   */
  public function testEnforcedAllowsValidJwt(): void {
    $this->config('bebbo_api_security.settings')
      ->set('enforcement_mode', 'enforced')
      ->save();

    $event = $this->buildEvent('/v2/api/articles/en', '127.0.0.1', $this->validJwt);
    $this->getSubscriber()->onRequest($event);

    $this->assertNull($event->getResponse());
    $this->assertEquals(
      'integration-test-device',
      $event->getRequest()->attributes->get('bebbo_device_id')
    );
  }

  /**
   * Tests that enforced mode blocks a tampered JWT.
   *
   * @covers ::onRequest
   */
  public function testEnforcedBlocksTamperedJwt(): void {
    $this->config('bebbo_api_security.settings')
      ->set('enforcement_mode', 'enforced')
      ->save();

    $tampered = $this->validJwt . 'tampered';
    $event = $this->buildEvent('/v2/api/articles/en', '127.0.0.1', $tampered);
    $this->getSubscriber()->onRequest($event);

    $response = $event->getResponse();
    $this->assertNotNull($response);
    $this->assertEquals(401, $response->getStatusCode());
  }

  /**
   * Tests that grace_period mode allows requests with no token.
   *
   * @covers ::onRequest
   */
  public function testGracePeriodAllowsNoToken(): void {
    $this->config('bebbo_api_security.settings')
      ->set('enforcement_mode', 'grace_period')
      ->save();

    $event = $this->buildEvent('/v2/api/articles/en');
    $this->getSubscriber()->onRequest($event);

    $this->assertNull($event->getResponse());
  }

  /**
   * Tests that grace_period mode sets bebbo_device_id when a valid JWT is sent.
   *
   * @covers ::onRequest
   */
  public function testGracePeriodTracksValidJwt(): void {
    $this->config('bebbo_api_security.settings')
      ->set('enforcement_mode', 'grace_period')
      ->save();

    $event = $this->buildEvent('/v2/api/articles/en', '127.0.0.1', $this->validJwt);
    $this->getSubscriber()->onRequest($event);

    $this->assertNull($event->getResponse());
    $this->assertEquals(
      'integration-test-device',
      $event->getRequest()->attributes->get('bebbo_device_id')
    );
  }

  /**
   * Tests grace_period passes invalid JWT through without setting device ID.
   *
   * @covers ::onRequest
   */
  public function testGracePeriodAllowsInvalidJwt(): void {
    $this->config('bebbo_api_security.settings')
      ->set('enforcement_mode', 'grace_period')
      ->save();

    $event = $this->buildEvent('/v2/api/articles/en', '127.0.0.1', 'not.a.valid.jwt');
    $this->getSubscriber()->onRequest($event);

    $this->assertNull($event->getResponse());
    $this->assertNull($event->getRequest()->attributes->get('bebbo_device_id'));
  }

  /**
   * Tests that dev bypass allows a matching IP with no token in enforced mode.
   *
   * @covers ::onRequest
   */
  public function testDevBypassInEnforcedMode(): void {
    $this->config('bebbo_api_security.settings')
      ->set('enforcement_mode', 'enforced')
      ->set('dev_bypass_enabled', TRUE)
      ->set('dev_bypass_ips', '10.0.0.1')
      ->save();

    $event = $this->buildEvent('/v2/api/articles/en', '10.0.0.1');
    $this->getSubscriber()->onRequest($event);

    $this->assertNull($event->getResponse());
  }

  /**
   * Tests that dev bypass does not apply to non-matching IPs.
   *
   * @covers ::onRequest
   */
  public function testDevBypassDoesNotApplyToOtherIps(): void {
    $this->config('bebbo_api_security.settings')
      ->set('enforcement_mode', 'enforced')
      ->set('dev_bypass_enabled', TRUE)
      ->set('dev_bypass_ips', '10.0.0.1')
      ->save();

    $event = $this->buildEvent('/v2/api/articles/en', '192.168.1.1');
    $this->getSubscriber()->onRequest($event);

    $response = $event->getResponse();
    $this->assertNotNull($response);
    $this->assertEquals(401, $response->getStatusCode());
  }

  /**
   * Tests that /api/check-update/ routes are protected in enforced mode.
   *
   * @covers ::onRequest
   */
  public function testCheckUpdateProtected(): void {
    $this->config('bebbo_api_security.settings')
      ->set('enforcement_mode', 'enforced')
      ->save();

    $event = $this->buildEvent('/api/check-update/bangladesh');
    $this->getSubscriber()->onRequest($event);

    $response = $event->getResponse();
    $this->assertNotNull($response);
    $this->assertEquals(401, $response->getStatusCode());
  }

  /**
   * Tests that /api/check-update/ routes are allowed with a valid JWT.
   *
   * @covers ::onRequest
   */
  public function testCheckUpdateAllowedWithJwt(): void {
    $this->config('bebbo_api_security.settings')
      ->set('enforcement_mode', 'enforced')
      ->save();

    $event = $this->buildEvent('/api/check-update/bangladesh', '127.0.0.1', $this->validJwt);
    $this->getSubscriber()->onRequest($event);

    $this->assertNull($event->getResponse());
    $this->assertEquals(
      'integration-test-device',
      $event->getRequest()->attributes->get('bebbo_device_id')
    );
  }

}
