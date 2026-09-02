<?php

namespace Drupal\bebbo_custom_general\Commands;

use Drupal\bebbo_custom_general\Service\EditorialMenuManager;
use Drush\Commands\DrushCommands;

/**
 * Drush commands for the canonical editorial menu.
 */
class EditorialMenuCommands extends DrushCommands {

  /**
   * The editorial menu manager.
   *
   * @var \Drupal\bebbo_custom_general\Service\EditorialMenuManager
   */
  protected EditorialMenuManager $menuManager;

  /**
   * Constructs an EditorialMenuCommands object.
   *
   * @param \Drupal\bebbo_custom_general\Service\EditorialMenuManager $menu_manager
   *   The editorial menu manager.
   */
  public function __construct(EditorialMenuManager $menu_manager) {
    parent::__construct();
    $this->menuManager = $menu_manager;
  }

  /**
   * Exports this site's editorial menu into the canonical config object.
   *
   * Run on the bebbo site only, then commit the exported config file. Unlike
   * menu_export, this keeps every value of multi-value fields, so a link
   * visible to several roles stays visible to all of them.
   *
   * @command bebbo:menu-export
   * @aliases bme
   * @usage drush bebbo:menu-export
   *   Write the current menu into bebbo_custom_general.editorial_menu.
   */
  public function export(): void {
    $count = $this->menuManager->export();
    $this->logger()->success(dt('Exported @count editorial menu link(s). Run drush cex to write the config file.', [
      '@count' => $count,
    ]));
  }

  /**
   * Applies the canonical editorial menu to this site.
   *
   * Runs per site on every deploy. Idempotent: a second run reports no
   * changes.
   *
   * @param array $options
   *   Command options (dry-run).
   *
   * @command bebbo:menu-sync
   * @aliases bms
   * @option dry-run Report what would change without writing anything.
   * @usage drush bebbo:menu-sync --dry-run
   *   Show the changes the sync would make.
   * @usage drush bebbo:menu-sync
   *   Apply the canonical editorial menu.
   */
  public function sync(array $options = ['dry-run' => FALSE]): void {
    if (!$this->menuManager->getCanon()) {
      $this->logger()->warning(dt('No canonical editorial menu found in @config; nothing to sync.', [
        '@config' => EditorialMenuManager::CONFIG_NAME,
      ]));
      return;
    }

    $dry_run = (bool) $options['dry-run'];
    $report = $this->menuManager->sync($dry_run);

    foreach (['deleted', 'created', 'updated'] as $action) {
      foreach ($report[$action] as $line) {
        $this->logger()->notice(dt('@action: @line', [
          '@action' => ucfirst($action),
          '@line' => $line,
        ]));
      }
    }

    $this->logger()->success(dt('@prefix@created created, @updated updated, @deleted deleted, @unchanged unchanged.', [
      '@prefix' => $dry_run ? 'Dry run — ' : '',
      '@created' => count($report['created']),
      '@updated' => count($report['updated']),
      '@deleted' => count($report['deleted']),
      '@unchanged' => count($report['unchanged']),
    ]));
  }

}
