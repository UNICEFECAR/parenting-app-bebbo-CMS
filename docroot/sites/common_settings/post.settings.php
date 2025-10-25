<?php
// phpcs:ignoreFile

/**
 * @file
 * Includes config post.settings.php (runs last).
 */

ini_set('memory_limit', '-1');
// Helper to read env consistently on Acquia/CLI.
$ah_group = getenv('AH_SITE_GROUP') ?: ($_ENV['AH_SITE_GROUP'] ?? null);
$ah_env   = getenv('AH_SITE_ENVIRONMENT') ?: ($_ENV['AH_SITE_ENVIRONMENT'] ?? null);

if ($ah_group && $ah_env) {
  if (!isset($acquia_settings_file_name)) {
    $acquia_settings_file_name = $_ENV['AH_SITE_GROUP'] . '-settings.inc';
  }
  global $conf, $databases;
  // Do not autoconnect to database
  $conf['acquia_hosting_settings_autoconnect'] = FALSE;
  // 1) Acquia platform include: /var/www/site-php/<group>/<env>-settings.inc
  $acquia_inc = "/var/www/site-php/{$ah_group}/" . $acquia_settings_file_name;
  if (is_readable($acquia_inc)) {
    require_once $acquia_inc;
  }

  // 2) Transaction isolation – SESSION level on all targets (default/replica/slave).
  if (!empty($databases['default']['default'])) {
    $databases['default']['default']['init_commands'] = [
      'transaction_isolation' => 'SET SESSION transaction_isolation="READ-COMMITTED"'
    ];
  }

  // Connect to database
  if (function_exists('acquia_hosting_db_choose_active')){
    acquia_hosting_db_choose_active();
  }

  // 3) Memcache include (only once, if you use the Acquia memcache helper).
  if (!empty($common_dir)) {
    $memcache_file = $common_dir . '/cloud-memcache-d8+.php';
    if (is_readable($memcache_file)) {
      require_once $memcache_file;
    }
  }

  // 4) Private files & temp path.
  if (!empty($site_path)) {
    $settings['file_private_path'] = "/mnt/files/{$ah_group}.{$ah_env}/{$site_path}/files-private";
  }
  $settings['file_temp_path'] = "/mnt/tmp/{$ah_group}.{$ah_env}";

  // 5) Environment-specific API keys (optional).
  $api_path = "/mnt/gfs/{$ah_group}.{$ah_env}/nobackup/bebbo_app_apikeys.php";
  if (is_readable($api_path)) {
    require_once $api_path;
  }
}

// Hash salt, config sync, and app config (non-Acquia specific).
$settings['hash_salt'] = hash('sha256', $app_root . '/' . $site_path);
$settings['config_sync_directory'] = '../config/default';

$config['smtp.settings']['smtp_username'] = getenv('smtp_username') ?: '';
$config['smtp.settings']['smtp_password'] = getenv('smtp_password') ?: '';

$config['tmgmt.translator.microsoft']['settings']['api_key'] = getenv('MS_TRANSLATE_KEY') ?: '';
// $config['tmgmt.translator.google']['settings']['api_key'] = getenv('GOOGLE_TRANSLATE_KEY') ?: '';
// $config['tmgmt.translator.deepl_free']['settings']['auth_key'] = getenv('DEEPL_AUTH_KEY_FREE') ?: '';
// $config['tmgmt.translator.deepl_pro']['settings']['auth_key'] = getenv('DEEPL_AUTH_KEY_PRO') ?: '';
