<?php

/**
 * @file
 * Main settings file.
 */

$common_dir = $app_root . '/sites/common_settings';

if (is_readable($common_dir . '/common.settings.php')) {
  require_once $common_dir . '/common.settings.php';
}

// DDEV (local) – only if actually running under DDEV.
if (getenv('IS_DDEV_PROJECT') === 'true' && is_readable(__DIR__ . '/settings.ddev.php')) {
  require_once __DIR__ . '/settings.ddev.php';
}

/**
 * Keep post.settings.php LAST so it can override previous values.
 */
if (is_readable($common_dir . '/post.settings.php')) {
  require_once $common_dir . '/post.settings.php';
}
