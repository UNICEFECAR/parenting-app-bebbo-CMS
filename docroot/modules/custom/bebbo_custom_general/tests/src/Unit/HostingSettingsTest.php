<?php

namespace Drupal\Tests\bebbo_custom_general\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Tests secret salt configuration without a running Drupal site.
 *
 * @group bebbo_custom_general
 */
class HostingSettingsTest extends TestCase {

  /**
   * Platform salts are preserved; an absent secret fails closed.
   */
  public function testHashSalt(): void {
    $prior = getenv('BEBBO_HASH_SALT');
    $group = getenv('AH_SITE_GROUP');
    $environment = getenv('AH_SITE_ENVIRONMENT');
    putenv('BEBBO_HASH_SALT');
    putenv('AH_SITE_GROUP');
    putenv('AH_SITE_ENVIRONMENT');
    try {
      $load = static function (array $settings): array {
        // Variables are read by the included settings file.
        // phpcs:ignore DrupalPractice.CodeAnalysis.VariableAnalysis.UnusedVariable
        $app_root = '/isolated/docroot';
        // phpcs:ignore DrupalPractice.CodeAnalysis.VariableAnalysis.UnusedVariable
        $site_path = 'sites/default';
        require dirname(__DIR__, 6) . '/sites/common_settings/post.settings.php';
        return $settings;
      };
      $this->assertSame('platform-secret', $load(['hash_salt' => 'platform-secret'])['hash_salt']);
      putenv('BEBBO_HASH_SALT=environment-secret');
      $this->assertSame(hash_hmac('sha256', 'sites/default', 'environment-secret'), $load([])['hash_salt']);
      putenv('BEBBO_HASH_SALT');
      $this->expectException(\RuntimeException::class);
      $load([]);
    }
    finally {
      foreach (['BEBBO_HASH_SALT' => $prior, 'AH_SITE_GROUP' => $group, 'AH_SITE_ENVIRONMENT' => $environment] as $key => $value) {
        putenv($value === FALSE ? $key : $key . '=' . $value);
      }
    }
  }

}
