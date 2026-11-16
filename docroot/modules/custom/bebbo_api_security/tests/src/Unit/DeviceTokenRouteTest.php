<?php

namespace Drupal\Tests\bebbo_api_security\Unit;

use Drupal\bebbo_api_security\Routing\DeviceTokenRouteSubscriber;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Path\PathValidatorInterface;
use Drupal\Core\Routing\RouteProviderInterface;
use Drupal\Core\Session\PermissionCheckerInterface;
use Drupal\Core\Url;
use Drupal\simple_oauth\Authentication\Provider\SimpleOauthAuthenticationProvider;
use Drupal\simple_oauth\PageCache\SimpleOauthRequestPolicyInterface;
use Drupal\simple_oauth\Server\ResourceServerFactoryInterface;
use Drupal\Tests\UnitTestCase;
use Symfony\Bridge\PsrHttpMessage\HttpFoundationFactoryInterface;
use Symfony\Bridge\PsrHttpMessage\HttpMessageFactoryInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

/**
 * Verifies that Simple OAuth leaves custom device bearer tokens alone.
 *
 * @group bebbo_api_security
 */
class DeviceTokenRouteTest extends UnitTestCase {

  /**
   * OAuth remains active elsewhere, including on excluded OAuth routes.
   */
  public function testDeviceRoutesOptOutOfUserOauth(): void {
    $config = $this->createMock(ImmutableConfig::class);
    $config->method('get')->willReturnMap([
      ['protected_api_patterns', '/v2/api/'],
      ['excluded_api_patterns', "/api/security/\n/v2/api/user/"],
    ]);
    $factory = $this->createMock(ConfigFactoryInterface::class);
    $factory->method('get')->willReturn($config);
    $collection = new RouteCollection();
    foreach ([
      'device' => '/v2/api/articles/{language}',
      'revoke' => '/api/security/revoke',
      'oauth' => '/oauth/token',
      'excluded' => '/v2/api/user/profile',
      'jsonapi' => '/jsonapi/node/article',
    ] as $name => $path) {
      $collection->add($name, new Route($path));
    }
    $subscriber = new DeviceTokenRouteSubscriber($factory);
    (new \ReflectionMethod($subscriber, 'alterRoutes'))->invoke($subscriber, $collection);
    $this->assertTrue($collection->get('device')->getOption('_oauth_skip_auth'));
    $this->assertTrue($collection->get('revoke')->getOption('_oauth_skip_auth'));
    foreach (['oauth', 'jsonapi', 'excluded'] as $name) {
      $this->assertNull($collection->get($name)->getOption('_oauth_skip_auth'));
    }

    // Exercise the installed Simple OAuth provider's real applies() method.
    $policy = $this->createMock(SimpleOauthRequestPolicyInterface::class);
    $policy->method('isOauth2Request')->willReturn(TRUE);
    $validator = $this->createMock(PathValidatorInterface::class);
    $validator->method('getUrlIfValidWithoutAccessCheck')->willReturn(Url::fromRoute('device'));
    $routes = $this->createMock(RouteProviderInterface::class);
    $routes->method('getRouteByName')->willReturn($collection->get('device'));
    $provider = new SimpleOauthAuthenticationProvider(
      $this->createMock(ResourceServerFactoryInterface::class),
      $this->createMock(EntityTypeManagerInterface::class), $policy,
      $this->createMock(HttpMessageFactoryInterface::class),
      $this->createMock(HttpFoundationFactoryInterface::class), $validator, $routes,
      new RequestStack(), $this->createMock(PermissionCheckerInterface::class),
    );
    $request = Request::create('/v2/api/articles/en');
    $request->headers->set('Authorization', 'Bearer device-token');
    $this->assertFalse($provider->applies($request));
  }

}
