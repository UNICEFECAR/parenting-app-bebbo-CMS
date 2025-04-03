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
 * @coversDefaultClass \Drupal\csp\EventSubscriber\ResponseCspSubscriber
 * @group csp
 */
class ResponseCspSubscriberNonceTest extends UnitTestCase {

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
   * @var \PHPUnit\Framework\MockObject\MockObject|\Symfony\Component\EventDispatcher\EventDispatcherInterface
   */
  private $eventDispatcher;

  /**
   * The Library Dependency Resolver service.
   *
   * @var \PHPUnit\Framework\MockObject\MockObject|\Drupal\Core\Asset\LibraryDependencyResolverInterface
   */
  private $libraryDependencyResolver;

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
    $this->nonce
      ->method('hasValue')
      ->willReturn(TRUE);
    $this->nonce
      ->method('getSource')
      ->willReturn("'nonce-abcde'");
    $this->nonce
      ->method('getValue')
      ->willReturn("abcde");

  }

  /**
   * Check that nonce is added to drupalSettings.
   *
   * @covers ::applyDrupalSettingsNonce
   */
  public function testNonceDrupalSettings() {

    /** @var \Drupal\Core\Config\ConfigFactoryInterface|\PHPUnit\Framework\MockObject\MockObject $configFactory */
    $configFactory = $this->getConfigFactoryStub([
      'csp.settings' => [
        'report-only' => [
          'enable' => TRUE,
          'directives' => [],
        ],
        'enforce' => [
          'enable' => TRUE,
        ],
      ],
    ]);

    $subscriber = new ResponseCspSubscriber(
      $configFactory,
      $this->eventDispatcher,
      $this->nonce,
      $this->libraryPolicy,
      $this->reportingHandlerPluginManager,
    );

    $this->response->expects($this->once())
      ->method('addAttachments')
      ->with($this->callback(function (array $attachments) {
        return !empty($attachments['drupalSettings']['csp']['nonce'])
          && $attachments['drupalSettings']['csp']['nonce'] == $this->nonce->getValue();
      }));

    $subscriber->applyDrupalSettingsNonce($this->event);
  }

}
