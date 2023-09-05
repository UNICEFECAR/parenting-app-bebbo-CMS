<?php

namespace Drupal\shield\Plugin\migrate\process;

use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\Row;

/**
 * Maps D7 shield settings to D9.
 *
 * @MigrateProcessPlugin(
 *   id = "shield_paths"
 * )
 */
class ShieldPaths extends ProcessPluginBase {

  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    $all_paths = '';
    foreach (explode("\r\n", $value) as $path) {
      // Adds a leading slash to all the paths.
      $all_paths = $all_paths . '/' . $path . "\n";
    }
    return rtrim($all_paths);
  }

}
