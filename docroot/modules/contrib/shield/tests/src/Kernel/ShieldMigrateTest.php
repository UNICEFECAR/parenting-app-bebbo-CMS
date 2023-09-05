<?php

namespace Drupal\Tests\shield\Kernel;

use Drupal\Tests\migrate_drupal\Kernel\d7\MigrateDrupal7TestBase;

/**
 * Tests shield config migration.
 *
 * @group shield
 */
class ShieldMigrateTest extends MigrateDrupal7TestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'path_alias',
    'shield',
  ];

  /**
   * {@inheritdoc}
   */
  protected function getFixtureFilePath() {
    return implode(DIRECTORY_SEPARATOR, [
      \Drupal::service('extension.list.module')->getPath('shield'),
      'tests',
      'fixtures',
      'drupal7.php',
    ]);
  }

  /**
   * Asserts that shield configuration is migrated.
   */
  public function testShieldMigration() {
    $expected_config = [
      'shield_enable' => TRUE,
      'credentials' => [
        'shield' => [
          'user' => 'abc',
          'pass' => '1234',
        ],
      ],
      'allow_cli' => FALSE,
      'print' => 'Authenticate!',
      'method' => 0,
      'paths' => '/node/3
/blog/*',
      'allowlist' => '192.168.0.75',
    ];

    $this->executeMigration('shield_settings');
    $config = $this->config('shield.settings')->getRawData();
    $this->assertSame($expected_config, $config);
  }

}
