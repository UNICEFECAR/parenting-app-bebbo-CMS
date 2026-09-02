<?php

declare(strict_types=1);

namespace Drupal\Tests\bebbo_api_security\Unit;

use Drupal\bebbo_api_security\EventSubscriber\ApiSecuritySubscriber;
use Drupal\bebbo_api_security\Service\JwtService;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * @coversDefaultClass \Drupal\bebbo_api_security\EventSubscriber\ApiSecuritySubscriber
 * @group bebbo_api_security
 */
class ApiSecuritySubscriberTest extends TestCase {

  /**
   * The subscriber under test.
   *
   * @var \Drupal\bebbo_api_security\EventSubscriber\ApiSecuritySubscriber
   */
  private ApiSecuritySubscriber $subscriber;

  /**
   * Mock JWT service.
   *
   * @var \Drupal\bebbo_api_security\Service\JwtService|\PHPUnit\Framework\MockObject\MockObject
   */
  private $jwtService;

  /**
   * Mock immutable config.
   *
   * @var \Drupal\Core\Config\ImmutableConfig|\PHPUnit\Framework\MockObject\MockObject
   */
  private $config;

  /**
   * Mock logger.
   *
   * @var \Psr\Log\LoggerInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  private $logger;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->jwtService = $this->createMock(JwtService::class);
    $this->config = $this->createMock(ImmutableConfig::class);
    $this->logger = $this->createMock(LoggerInterface::class);

    $configFactory = $this->createMock(ConfigFactoryInterface::class);
    $configFactory->method('get')
      ->with('bebbo_api_security.settings')
      ->willReturn($this->config);

    $this->subscriber = new ApiSecuritySubscriber(
      $this->jwtService,
      $configFactory,
      $this->logger,
    );
  }

  /**
   * Build a request event for the given path.
   *
   * Optionally sets an Authorization header and server variables.
   */
  private function buildEvent(string $path, string $authorization = '', array $server = []): RequestEvent {
    $request = Request::create($path, 'GET', [], [], [], $server);
    if ($authorization !== '') {
      $request->headers->set('Authorization', $authorization);
    }
    $kernel = $this->createMock(HttpKernelInterface::class);
    return new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST);
  }

  /**
   * Call the private isProtectedPath() via reflection.
   */
  private function callIsProtectedPath(string $path): bool {
    $method = new \ReflectionMethod($this->subscriber, 'isProtectedPath');
    $method->setAccessible(TRUE);
    return $method->invoke($this->subscriber, $path);
  }

  /**
   * Data provider: paths that must be matched as protected.
   *
   * @return array<string, array{string}>
   *   Keyed by path string.
   */
  public static function protectedPathsProvider(): array {
    return [
      '/v2/api/articles/en' => ['/v2/api/articles/en'],
      '/v2/api/milestones/en/123' => ['/v2/api/milestones/en/123'],
      '/v2/api/activities/en/1/2' => ['/v2/api/activities/en/1/2'],
      '/v2/api/vaccinations/en' => ['/v2/api/vaccinations/en'],
      '/v2/api/faqs/en/42' => ['/v2/api/faqs/en/42'],
      '/v2/api/course/en' => ['/v2/api/course/en'],
      '/v2/api/quiz/en' => ['/v2/api/quiz/en'],
      '/v2/api/guide/en' => ['/v2/api/guide/en'],
      '/v2/api/taxonomies/en/tags' => ['/v2/api/taxonomies/en/tags'],
      '/v2/api/vocabularies/en' => ['/v2/api/vocabularies/en'],
      '/v2/api/country-groups/en' => ['/v2/api/country-groups/en'],
      '/v2/api/weekly-overview/en' => ['/v2/api/weekly-overview/en'],
      '/v2/api/child-development-data/en' => ['/v2/api/child-development-data/en'],
      '/v2/api/child-growth-data/en' => ['/v2/api/child-growth-data/en'],
      '/v2/api/health-checkup-data/en' => ['/v2/api/health-checkup-data/en'],
      '/v2/api/standard_deviation/en' => ['/v2/api/standard_deviation/en'],
      '/v2/api/daily-homescreen-messages/en' => ['/v2/api/daily-homescreen-messages/en'],
      '/v2/api/basic-pages/en' => ['/v2/api/basic-pages/en'],
      '/v2/api/surveys/en' => ['/v2/api/surveys/en'],
      '/v2/api/video-articles/en/1' => ['/v2/api/video-articles/en/1'],
      '/v2/api/archive/en/1' => ['/v2/api/archive/en/1'],
      '/api/check-update/bangladesh' => ['/api/check-update/bangladesh'],
    ];
  }

  /**
   * Data provider: paths that must NOT be matched as protected.
   *
   * @return array<string, array{string}>
   *   Keyed by path string.
   */
  public static function unprotectedPathsProvider(): array {
    return [
      '/api/security/register' => ['/api/security/register'],
      '/api/security/device/register' => ['/api/security/device/register'],
      '/api/security/device/verify' => ['/api/security/device/verify'],
      '/api/security/refresh' => ['/api/security/refresh'],
      '/api/security/revoke' => ['/api/security/revoke'],
      '/admin/config/parent-buddy/api-security' => ['/admin/config/parent-buddy/api-security'],
      '/' => ['/'],
      '/node/1' => ['/node/1'],
      '/api/articles/en' => ['/api/articles/en'],
    ];
  }

  /**
   * @covers ::onRequest
   * @dataProvider protectedPathsProvider
   */
  public function testProtectedPathsAreMatched(string $path): void {
    $this->assertTrue($this->callIsProtectedPath($path), "$path should be protected");
  }

  /**
   * @covers ::onRequest
   * @dataProvider unprotectedPathsProvider
   */
  public function testUnprotectedPathsAreSkipped(string $path): void {
    $this->assertFalse($this->callIsProtectedPath($path), "$path should not be protected");
  }

  /**
   * @covers ::onRequest
   */
  public function testDisabledModeAllowsEverything(): void {
    $this->config->method('get')->willReturnMap([
      ['enforcement_mode', 'disabled'],
      ['dev_bypass_enabled', FALSE],
    ]);

    $event = $this->buildEvent('/v2/api/articles/en');
    $this->subscriber->onRequest($event);

    $this->assertNull($event->getResponse());
  }

  /**
   * @covers ::onRequest
   */
  public function testGracePeriodAllowsWithoutToken(): void {
    $this->config->method('get')->willReturnMap([
      ['enforcement_mode', 'grace_period'],
      ['dev_bypass_enabled', FALSE],
      ['dev_bypass_ips', ''],
    ]);

    $event = $this->buildEvent('/v2/api/articles/en');
    $this->subscriber->onRequest($event);

    $this->assertNull($event->getResponse());
  }

  /**
   * @covers ::onRequest
   */
  public function testGracePeriodSetsDeviceIdWithValidToken(): void {
    $this->config->method('get')->willReturnMap([
      ['enforcement_mode', 'grace_period'],
      ['dev_bypass_enabled', FALSE],
      ['dev_bypass_ips', ''],
    ]);
    $this->jwtService->method('validateToken')
      ->willReturn(['sub' => 'device-123']);

    $event = $this->buildEvent('/v2/api/articles/en', 'Bearer valid-jwt');
    $this->subscriber->onRequest($event);

    $this->assertNull($event->getResponse());
    $this->assertSame(
      'device-123',
      $event->getRequest()->attributes->get('bebbo_device_id')
    );
  }

  /**
   * @covers ::onRequest
   */
  public function testGracePeriodLogsInvalidToken(): void {
    $this->config->method('get')->willReturnMap([
      ['enforcement_mode', 'grace_period'],
      ['dev_bypass_enabled', FALSE],
      ['dev_bypass_ips', ''],
    ]);
    $this->jwtService->method('validateToken')->willReturn(NULL);
    $this->logger->expects($this->once())->method('warning');

    $event = $this->buildEvent('/v2/api/articles/en', 'Bearer bad-jwt');
    $this->subscriber->onRequest($event);

    $this->assertNull($event->getResponse());
  }

  /**
   * @covers ::onRequest
   */
  public function testEnforcedModeBlocksWithoutToken(): void {
    $this->config->method('get')->willReturnMap([
      ['enforcement_mode', 'enforced'],
      ['dev_bypass_enabled', FALSE],
      ['dev_bypass_ips', ''],
    ]);

    $event = $this->buildEvent('/v2/api/articles/en');
    $this->subscriber->onRequest($event);

    $response = $event->getResponse();
    $this->assertNotNull($response);
    $this->assertSame(401, $response->getStatusCode());

    $data = json_decode($response->getContent(), TRUE);
    $this->assertSame('missing_token', $data['error']);
    $this->assertSame('Bearer realm="Bebbo API"', $response->headers->get('WWW-Authenticate'));
  }

  /**
   * @covers ::onRequest
   */
  public function testEnforcedModeBlocksInvalidToken(): void {
    $this->config->method('get')->willReturnMap([
      ['enforcement_mode', 'enforced'],
      ['dev_bypass_enabled', FALSE],
      ['dev_bypass_ips', ''],
    ]);
    $this->jwtService->method('validateToken')->willReturn(NULL);

    $event = $this->buildEvent('/v2/api/articles/en', 'Bearer bad-jwt');
    $this->subscriber->onRequest($event);

    $response = $event->getResponse();
    $this->assertNotNull($response);
    $this->assertSame(401, $response->getStatusCode());

    $data = json_decode($response->getContent(), TRUE);
    $this->assertSame('invalid_token', $data['error']);
  }

  /**
   * @covers ::onRequest
   */
  public function testEnforcedModeAllowsValidToken(): void {
    $this->config->method('get')->willReturnMap([
      ['enforcement_mode', 'enforced'],
      ['dev_bypass_enabled', FALSE],
      ['dev_bypass_ips', ''],
    ]);
    $this->jwtService->method('validateToken')
      ->willReturn(['sub' => 'device-abc']);

    $event = $this->buildEvent('/v2/api/articles/en', 'Bearer good-jwt');
    $this->subscriber->onRequest($event);

    $this->assertNull($event->getResponse());
    $this->assertSame(
      'device-abc',
      $event->getRequest()->attributes->get('bebbo_device_id')
    );
  }

  /**
   * @covers ::onRequest
   */
  public function testDevBypassSkipsValidation(): void {
    $this->config->method('get')->willReturnMap([
      ['enforcement_mode', 'enforced'],
      ['dev_bypass_enabled', TRUE],
      ['dev_bypass_ips', "127.0.0.1\n"],
    ]);

    $event = $this->buildEvent(
      '/v2/api/articles/en',
      '',
      ['REMOTE_ADDR' => '127.0.0.1']
    );
    $this->subscriber->onRequest($event);

    $this->assertNull($event->getResponse());
  }

  /**
   * @covers ::onRequest
   */
  public function testDevBypassDisabledDoesNotSkip(): void {
    $this->config->method('get')->willReturnMap([
      ['enforcement_mode', 'enforced'],
      ['dev_bypass_enabled', FALSE],
      ['dev_bypass_ips', "127.0.0.1\n"],
    ]);

    $event = $this->buildEvent(
      '/v2/api/articles/en',
      '',
      ['REMOTE_ADDR' => '127.0.0.1']
    );
    $this->subscriber->onRequest($event);

    $response = $event->getResponse();
    $this->assertNotNull($response);
    $this->assertSame(401, $response->getStatusCode());
  }

  /**
   * @covers ::onRequest
   */
  public function testNonProtectedPathIgnoredInEnforcedMode(): void {
    // Config should never be consulted for unprotected paths.
    $this->config->expects($this->never())->method('get');

    $event = $this->buildEvent('/node/1');
    $this->subscriber->onRequest($event);

    $this->assertNull($event->getResponse());
  }

  /**
   * @covers ::onRequest
   */
  public function testSecurityEndpointExcludedInEnforcedMode(): void {
    // Config should never be consulted for excluded security paths.
    $this->config->expects($this->never())->method('get');

    $event = $this->buildEvent('/api/security/register');
    $this->subscriber->onRequest($event);

    $this->assertNull($event->getResponse());
  }

  /**
   * @covers ::getSubscribedEvents
   */
  public function testSubscriberPriority(): void {
    $events = ApiSecuritySubscriber::getSubscribedEvents();

    $this->assertArrayHasKey(KernelEvents::REQUEST, $events);
    $this->assertSame(['onRequest', 300], $events[KernelEvents::REQUEST]);
  }

}
