<?php

declare(strict_types=1);

namespace Drupal\Tests\acquia_connector\Kernel\Client;

use Drupal\acquia_connector\ConnectorException;
use Drupal\Tests\acquia_connector\Kernel\AcquiaConnectorTestBase;

/**
 * @coversDefaultClass \Drupal\acquia_connector\Client\ClientFactory
 * @group acquia_connector
 */
final class ClientFactoryTest extends AcquiaConnectorTestBase {

  /**
   * Tests the config for the created client.
   */
  public function testClientConfig(): void {
    $this->populateOauthSettings();
    $client = $this->container->get('acquia_connector.client.factory')->getCloudApiClient();
    $config = $client->getConfig();
    self::assertEquals('https://cloud.acquia.com', (string) $config['base_uri']);
    self::assertStringContainsString('AcquiaConnector/', $config['headers']['User-Agent']);
    self::assertEquals('application/json, version=2', $config['headers']['Accept']);
  }

  /**
   * Tests the refresh token and retry middleware.
   */
  public function testRefreshRetryMiddleware(): void {
    $this->populateOauthSettings([
      'access_token' => 'ACCESS_TOKEN_RETRY_MIDDLEWARE',
      'refresh_token' => 'REFRESH_TOKEN',
    ]);
    $client = $this->container->get('acquia_connector.client.factory')->getCloudApiClient();
    $response = $client->get('/test-retry-middleware');
    self::assertEquals(200, $response->getStatusCode());
  }

  /**
   * Tests exception if we don't have a token set.
   */
  public function testConnectorException(): void {
    $this->expectException(ConnectorException::class);
    $this->expectExceptionMessage("Missing access token.");
    $this->container->get('acquia_connector.client.factory')->getCloudApiClient();
  }

}
