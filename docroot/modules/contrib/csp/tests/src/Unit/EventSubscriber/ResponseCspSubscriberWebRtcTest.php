<?php

namespace Drupal\Tests\csp\Unit\EventSubscriber;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Render\HtmlResponse;
use Drupal\csp\EventSubscriber\ResponseCspSubscriber;
use Drupal\csp\LibraryPolicyBuilder;
use Drupal\csp\Nonce;
use Drupal\csp\ReportingHandlerPluginManager;
use Drupal\Tests\UnitTestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/**
 * Test formatting of WebRTC directive output.
 *
 * @coversDefaultClass \Drupal\csp\EventSubscriber\ResponseCspSubscriber
 * @group csp
 */
class ResponseCspSubscriberWebRtcTest extends UnitTestCase {

  /**
   * Mock HTTP Response.
   *
   * @var \Drupal\Core\Render\HtmlResponse|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $response;

  /**
   * Mock Response Event.
   *
   * @var \Symfony\Component\HttpKernel\Event\ResponseEvent|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $event;

  /**
   * The Library Policy service.
   *
   * @var \Drupal\csp\LibraryPolicyBuilder|\PHPUnit\Framework\MockObject\MockObject
   */
  private $libraryPolicy;

  /**
   * The Reporting Handler Plugin Manager service.
   *
   * @var \Drupal\csp\ReportingHandlerPluginManager|\PHPUnit\Framework\MockObject\MockObject
   */
  private $reportingHandlerPluginManager;

  /**
   * The Event Dispatcher Service.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  private $eventDispatcher;

  /**
   * The Nonce service.
   *
   * @var \PHPUnit\Framework\MockObject\MockObject|\Drupal\csp\Nonce
   */
  private $nonce;

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();

    $this->response = $this->createMock(HtmlResponse::class);
    $this->response->headers = $this->createMock(ResponseHeaderBag::class);
    $responseCacheableMetadata = $this->createMock(CacheableMetadata::class);
    $this->response->method('getCacheableMetadata')
      ->willReturn($responseCacheableMetadata);

    $this->event = new ResponseEvent(
      $this->createMock(HttpKernelInterface::class),
      $this->createMock(Request::class),
      HttpKernelInterface::MAIN_REQUEST,
      $this->response
    );

    $this->libraryPolicy = $this->createMock(LibraryPolicyBuilder::class);

    $this->reportingHandlerPluginManager = $this->createMock(ReportingHandlerPluginManager::class);

    $this->eventDispatcher = $this->createMock(EventDispatcher::class);

    $this->nonce = $this->createMock(Nonce::class);
  }

  /**
   * Check that webrtc directive is formatted correctly.
   *
   * @covers ::onKernelResponse
   */
  public function testEmptyWebRtc() {
    /** @var \Drupal\Core\Config\ConfigFactoryInterface|\PHPUnit\Framework\MockObject\MockObject $configFactory */
    $configFactory = $this->getConfigFactoryStub([
      'csp.settings' => [
        'report-only' => [
          'enable' => TRUE,
          'directives' => [
            'webrtc' => '',
          ],
        ],
        'enforce' => [
          'enable' => FALSE,
        ],
      ],
    ]);

    $this->libraryPolicy->expects($this->any())
      ->method('getSources')
      ->willReturn([]);

    $subscriber = new ResponseCspSubscriber(
      $configFactory,
      $this->eventDispatcher,
      $this->nonce,
      $this->libraryPolicy,
      $this->reportingHandlerPluginManager
    );

    $this->response->headers->expects($this->never())
      ->method('set');

    $subscriber->onKernelResponse($this->event);
  }

  /**
   * Data provider for WebRTC config values.
   *
   * @return array[]
   *   Configuration values.
   */
  public static function webRtcConfigProvider() {
    return [
      'allow' => ['allow'],
      'block' => ['block'],
    ];
  }

  /**
   * Check that webrtc directive is formatted correctly.
   *
   * @covers ::onKernelResponse
   * @dataProvider webRtcConfigProvider
   */
  public function testWebRtc($value) {
    /** @var \Drupal\Core\Config\ConfigFactoryInterface|\PHPUnit\Framework\MockObject\MockObject $configFactory */
    $configFactory = $this->getConfigFactoryStub([
      'csp.settings' => [
        'report-only' => [
          'enable' => TRUE,
          'directives' => [
            'webrtc' => $value,
          ],
        ],
        'enforce' => [
          'enable' => FALSE,
        ],
      ],
    ]);

    $this->libraryPolicy->expects($this->any())
      ->method('getSources')
      ->willReturn([]);

    $subscriber = new ResponseCspSubscriber(
      $configFactory,
      $this->eventDispatcher,
      $this->nonce,
      $this->libraryPolicy,
      $this->reportingHandlerPluginManager,
    );

    $this->response->headers->expects($this->once())
      ->method('set')
      ->with(
        $this->equalTo('Content-Security-Policy-Report-Only'),
        $this->equalTo("webrtc '$value'")
      );

    $subscriber->onKernelResponse($this->event);
  }

}
