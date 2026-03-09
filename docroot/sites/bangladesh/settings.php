<?php

/**
 * @file
 * Main settings file.
 */

$acquia_settings_file_name = 'babuni-settings.inc';

// Always include the default/site-common stack first, once.
$default_settings = $app_root . '/sites/default/settings.php';
if (is_readable($default_settings)) {
  require_once $default_settings;
}

// DDev db name override.
if (getenv('IS_DDEV_PROJECT') == 'true') {
  $databases['default']['default']['database'] = 'bangladesh_db';
}

/**
 * Include site specific splits.
 */
if (file_exists($app_root . '/' . $site_path . '/site.splits.php')) {
  include $app_root . '/' . $site_path . '/site.splits.php';
}
