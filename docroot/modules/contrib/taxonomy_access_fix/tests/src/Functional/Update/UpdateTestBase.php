<?php

namespace Drupal\Tests\taxonomy_access_fix\Functional\Update;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\Core\Url;
use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\RequirementsPageTrait;

/**
 * Provides an abstract base class to test Taxonomy Access Fix update hooks.
 */
abstract class UpdateTestBase extends BrowserTestBase {

  use RequirementsPageTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['taxonomy_access_fix'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * The user used to run the update.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $user;

  /**
   * The update URL.
   *
   * @var \Drupal\Core\Url
   */
  protected $updateUrl;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    require_once $this->root . '/core/includes/update.inc';
    $this->user = $this->drupalCreateUser([
      'administer software updates',
      'access site in maintenance mode',
    ]);
    $this->updateUrl = Url::fromRoute('system.db_update');
  }

  /**
   * Tests that update hooks are run properly.
   */
  abstract public function testUpdateHooks(): void;

  /**
   * Applies all pending update hooks and runs basic schema assertions.
   *
   * @param int $previous_schema
   *   Schema version to reset to before the update.
   * @param array $raw_messages
   *   Array of raw messages to assert on update selection screen keyed by
   *   schema version (i.e. update hook number). Usually the comment used for
   *   the update hook. Some characters may be HTML encoded.
   *
   * @phpstan-param non-empty-array<int, string> $raw_messages
   */
  protected function runUpdates(int $previous_schema, array $raw_messages): void {
    // Set schema to previous schema.
    $update_hook_registry = $this->container
      ->get('update.update_hook_registry');
    $update_hook_registry->setInstalledVersion('taxonomy_access_fix', $previous_schema);
    $this->assertSame($update_hook_registry->getInstalledVersion('taxonomy_access_fix'), $previous_schema, new FormattableMarkup('Schema of taxonomy_access_fix is @schema', [
      '@schema' => $previous_schema,
    ]));

    // Login and call update.php. Go to available updates step.
    $this->drupalLogin($this->user);
    $this->drupalGet($this->updateUrl, ['external' => TRUE]);
    $this->updateRequirementsProblem();
    $this->clickLink('Continue');

    // Assert target updates are available.
    $this->assertSession()->responseContains('taxonomy_access_fix module');
    foreach ($raw_messages as $target_schema => $raw_message) {
      $this->assertSession()->responseContains((string) $target_schema . ' - ');
      $this->assertSession()->responseContains($raw_message);
    }

    // Run the update hooks.
    $this->clickLink('Apply pending updates');
    $this->checkForMetaRefresh();

    // Assert schema has changed as expected.
    ksort($raw_messages);
    $expected_schema = array_key_last($raw_messages);
    $this->assertSame($update_hook_registry->getInstalledVersion('taxonomy_access_fix'), $expected_schema, new FormattableMarkup('Schema of taxonomy_access_fix is @schema', [
      '@schema' => $expected_schema,
    ]));
  }

}
