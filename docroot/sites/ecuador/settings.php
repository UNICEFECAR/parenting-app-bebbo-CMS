<?php

/**
 * @file
 * Main settings file.
 */

$acquia_settings_file_name = 'prod_pbecuador-settings.inc';

// Always include the default/site-common stack first, once.
$default_settings = $app_root . '/sites/default/settings.php';
if (is_readable($default_settings)) {
  require_once $default_settings;
}

$settings["config_sync_directory"] = '../config_ecuador/default';
// DDev db name override.
if (getenv('IS_DDEV_PROJECT') == 'true') {
  $databases['default']['default']['database'] = 'ecuador_db';
}
