<?php

namespace Drupal\Tests\csp\Unit\Controller;

use Drupal\csp\Controller\ReportUri;
use Drupal\Tests\UnitTestCase;
use Prophecy\Argument;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Test the sitelog reporting handler.
 *
 * @coversDefaultClass \Drupal\csp\Controller\ReportUri
 * @group csp
 */
class ReportUriTest extends UnitTestCase {

  /**
   * Valid JSON should get sent to the logger.
   */
  public function testLog() {
    $requestStack = $this->prophesize(RequestStack::class);
    $logger = $this->prophesize(LoggerInterface::class);
    $config = $this->getConfigFactoryStub([
      'csp.settings' => [
        'enforce' => [
          'reporting' => [
            'plugin' => 'sitelog',
          ],
        ],
        'report-only' => [
          'reporting' => [
            'plugin' => 'sitelog',
          ],
        ],
      ],
    ]);

    $controller = new ReportUri(
      $requestStack->reveal(),
      $logger->reveal(),
      $config
    );

    $mockRequest = $this->prophesize(Request::class);
    $mockRequest->getContent()->willReturn('{"key":"value"}');
    $requestStack->getCurrentRequest()->willReturn($mockRequest->reveal());

    $logger
      ->info(Argument::type('string'), Argument::type('array'))
      ->shouldBeCalled();

    $response = $controller->log('enforce');
    $this->assertEquals(202, $response->getStatusCode());

    $response = $controller->log('reportOnly');
    $this->assertEquals(202, $response->getStatusCode());
  }

  /**
   * Reports should only be logged if policy uses sitelog handler.
   */
  public function testDisabled() {
    $requestStack = $this->prophesize(RequestStack::class);
    $logger = $this->prophesize(LoggerInterface::class);
    $config = $this->getConfigFactoryStub([
      'csp.settings' => [
        'enforce' => [
          'reporting' => [
            'plugin' => 'uri',
          ],
        ],
        'report-only' => [
          'reporting' => [
            'plugin' => 'none',
          ],
        ],
      ],
    ]);

    $controller = new ReportUri(
      $requestStack->reveal(),
      $logger->reveal(),
      $config
    );

    $logger
      ->info(Argument::type('string'), Argument::type('array'))
      ->shouldNotBeCalled();

    $response = $controller->log('enforce');
    $this->assertEquals(404, $response->getStatusCode());

    $response = $controller->log('reportOnly');
    $this->assertEquals(404, $response->getStatusCode());
  }

  /**
   * Only valid report type keys should be allowed.
   */
  public function testInvalidType() {
    $requestStack = $this->prophesize(RequestStack::class);
    $logger = $this->prophesize(LoggerInterface::class);
    $config = $this->getConfigFactoryStub([
      'csp.settings' => [
        'enforce' => [
          'reporting' => [
            'plugin' => 'sitelog',
          ],
        ],
        'report-only' => [
          'reporting' => [
            'plugin' => 'sitelog',
          ],
        ],
      ],
    ]);

    $controller = new ReportUri(
      $requestStack->reveal(),
      $logger->reveal(),
      $config
    );

    $logger
      ->info(Argument::type('string'), Argument::type('array'))
      ->shouldNotBeCalled();

    $response = $controller->log('enforced');
    $this->assertEquals(404, $response->getStatusCode());

    $response = $controller->log('report-only');
    $this->assertEquals(404, $response->getStatusCode());
  }

  /**
   * Invalid JSON should get an error response.
   */
  public function testInvalidJson() {
    $requestStack = $this->prophesize(RequestStack::class);
    $logger = $this->prophesize(LoggerInterface::class);
    $config = $this->getConfigFactoryStub([
      'csp.settings' => [
        'enforce' => [
          'reporting' => [
            'plugin' => 'sitelog',
          ],
        ],
      ],
    ]);

    $controller = new ReportUri(
      $requestStack->reveal(),
      $logger->reveal(),
      $config
    );

    $mockRequest = $this->prophesize(Request::class);
    $mockRequest->getContent()->willReturn('{"key" => "value"}');
    $requestStack->getCurrentRequest()->willReturn($mockRequest->reveal());

    $logger
      ->info(Argument::type('string'), Argument::type('array'))
      ->shouldNotBeCalled();

    $response = $controller->log('enforce');
    $this->assertEquals(400, $response->getStatusCode());
  }

}
