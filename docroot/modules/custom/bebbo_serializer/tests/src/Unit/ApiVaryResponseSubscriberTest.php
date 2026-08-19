<?php

namespace Drupal\Tests\bebbo_serializer\Unit;

use Drupal\bebbo_serializer\EventSubscriber\ApiVaryResponseSubscriber;
use Drupal\Tests\UnitTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/**
 * Tests that Cookie is dropped from Vary on the API paths only.
 *
 * @group bebbo_serializer
 *
 * @coversDefaultClass \Drupal\bebbo_serializer\EventSubscriber\ApiVaryResponseSubscriber
 */
class ApiVaryResponseSubscriberTest extends UnitTestCase {

  /**
   * Runs the subscriber over one path and returns the resulting Vary header.
   *
   * @param string $path
   *   The request path.
   * @param string|null $vary
   *   The Vary header the response carries, or NULL for none.
   * @param int $requestType
   *   Main or sub request.
   *
   * @return string|null
   *   The Vary header after the subscriber ran.
   */
  private function vary(string $path, ?string $vary, int $requestType = HttpKernelInterface::MAIN_REQUEST): ?string {
    $response = new Response();
    if ($vary !== NULL) {
      $response->headers->set('Vary', $vary);
    }
    $event = new ResponseEvent(
      $this->createMock(HttpKernelInterface::class),
      Request::create($path),
      $requestType,
      $response,
    );
    (new ApiVaryResponseSubscriber())->onResponse($event);
    return $event->getResponse()->headers->get('Vary');
  }

  /**
   * The API paths lose Cookie and keep everything else.
   *
   * @covers ::onResponse
   */
  public function testApiPathsDropCookie(): void {
    $this->assertNull($this->vary('/api/articles/en', 'Cookie'));
    $this->assertNull($this->vary('/v2/api/articles/en', 'Cookie'));
    $this->assertNull($this->vary('/v1/api/articles/en', 'Cookie'));
    $this->assertSame('Accept-Encoding', $this->vary('/api/articles/en', 'Cookie, Accept-Encoding'));
    $this->assertSame('Accept-Encoding', $this->vary('/api/faqs/en', 'cookie, Accept-Encoding'));
  }

  /**
   * HTML and admin paths are left alone.
   *
   * @covers ::onResponse
   */
  public function testNonApiPathsKeepCookie(): void {
    $this->assertSame('Cookie', $this->vary('/node/1', 'Cookie'));
    $this->assertSame('Cookie', $this->vary('/admin/content', 'Cookie'));
    $this->assertSame('Cookie', $this->vary('/apiary/thing', 'Cookie'));
    $this->assertSame('Cookie, Accept-Encoding', $this->vary('/', 'Cookie, Accept-Encoding'));
  }

  /**
   * A response with no Vary, or none to remove, is untouched.
   *
   * @covers ::onResponse
   */
  public function testNothingToRemove(): void {
    $this->assertNull($this->vary('/api/articles/en', NULL));
    $this->assertSame('Accept-Encoding', $this->vary('/api/articles/en', 'Accept-Encoding'));
  }

  /**
   * Sub-requests are ignored.
   *
   * @covers ::onResponse
   */
  public function testSubRequestIgnored(): void {
    $this->assertSame('Cookie', $this->vary('/api/articles/en', 'Cookie', HttpKernelInterface::SUB_REQUEST));
  }

}
