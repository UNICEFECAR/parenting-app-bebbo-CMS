<?php

namespace Drupal\Tests\acquia_connector\Kernel\Migrate\d7;

use Drupal\Tests\migrate_drupal\Kernel\d7\MigrateDrupal7TestBase;

/**
 * Migrates various configuration objects owned by the acquia connector module.
 *
 * @group acquia_connector
 */
class MigrateAcquiaConnectorConfigurationTest extends MigrateDrupal7TestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['acquia_connector'];

  protected $expectedConfig = [
    'acquia_connector.settings' => [
      'debug' => FALSE,
      'cron_interval' => 30,
      'cron_interval_override' => 0,
      'hide_signup_messages' => 0,
    ],
  ];

  /**
   * Expected State Variable.
   *
   * Note, Acquia uses state for site-specific subscription data. However, the
   * spi data is dynamically generated and doesn't need migration:
   *   'def_vars',
   *   'def_waived_vars',
   *   'def_timestamp',
   *   'new_optional_data'.
   *
   * @var array[]
   */
  protected $expectedState = [
    'acquia_subscription_data' => [
      'timestamp' => 1234567890,
      'active' => '1',
      'href' => 'https://insight.acquia.com/node/uuid/1b2c3456-a123-456d-a789-e1234567895d/dashboard',
      'uuid' => '1b2c3456-a123-456d-a789-e1234567895d',
      'subscription_name' => 'Test',
      'expiration_date' => [
        'value' => '2042-12-30T00:00:00',
      ],
      'product' => [
        'view' => 'Acquia Network',
      ],
      'derived_key_salt' => '1234e56789979a1d8ae123cd321a12c7',
      'update_service' => '1',
      'search_service_enabled' => 1,
      'update' => [],
      'heartbeat_data' => [
        'acquia_lift' => [
          'status' => FALSE,
          'decision' => [
            'public_key' => '',
            'private_key' => '',
          ],
          'profile' => [
            'account_name' => '',
            'hostname' => '',
            'public_key' => '',
            'secret_key' => '',
            'js_path' => '',
          ],
        ],
        'search_service_enabled' => 1,
        'search_cores' => [
          0 => [
            'balancer' => 'useast1-c1.acquia-search.com',
            'core_id' => 'TEST-123456',
          ],
          1 => [
            'balancer' => 'useast1-c1.acquia-search.com',
            'core_id' => 'TEST-123456.prod.v2',
          ],
          2 => [
            'balancer' => 'useast1-c1.acquia-search.com',
            'core_id' => 'TEST-123456.test.v2',
          ],
          3 => [
            'balancer' => 'useast1-c1.acquia-search.com',
            'core_id' => 'TEST-123456.dev.v2',
          ],
          4 => [
            'balancer' => 'useast1-c26.acquia-search.com',
            'core_id' => 'TEST-123456.prod.default',
          ],
          5 => [
            'balancer' => 'useast1-c26.acquia-search.com',
            'core_id' => 'TEST-123456.test.default',
          ],
          6 => [
            'balancer' => 'useast1-c26.acquia-search.com',
            'core_id' => 'TEST-123456.dev.default',
          ],
        ],
        'search_service_colony' => 'useast1-c1.acquia-search.com',
      ],
    ],
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $path = \Drupal::service('extension.list.module')->getPath('acquia_connector');
    $this->loadFixture(implode(DIRECTORY_SEPARATOR, [
      DRUPAL_ROOT,
      $path,
      'tests',
      'fixtures',
      'drupal7.php',
    ]));

    $migrations = [
      'd7_acquia_connector_settings',
      'd7_acquia_connector_subscription_data',
    ];
    $this->executeMigrations($migrations);
  }

  /**
   * Tests that all expected configuration gets migrated.
   */
  public function testConfigurationMigration() {
    // Test Config.
    foreach ($this->expectedConfig as $config_id => $values) {
      $actual = \Drupal::config($config_id)->get();
      $this->assertSame($values, $actual);
    }
    // Test State.
    foreach ($this->expectedState as $state_id => $values) {
      $actual = \Drupal::state()->get($state_id);
      $this->assertSame($values, $actual);
    }
  }

}
