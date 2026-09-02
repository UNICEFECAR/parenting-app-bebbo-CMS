<?php

declare(strict_types=1);

namespace Drupal\bebbo_api_security\Form;

use Drupal\bebbo_api_security\Service\DeviceRegistryService;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Admin configuration form for API security settings.
 */
class ApiSecuritySettingsForm extends ConfigFormBase {

  /**
   * The device registry service.
   *
   * @var \Drupal\bebbo_api_security\Service\DeviceRegistryService
   */
  protected DeviceRegistryService $registry;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    $instance = parent::create($container);
    $instance->registry = $container->get('bebbo_api_security.device_registry');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames(): array {
    return ['bebbo_api_security.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'bebbo_api_security_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $config = $this->config('bebbo_api_security.settings');

    // Enforcement Mode.
    $form['enforcement'] = [
      '#type' => 'details',
      '#title' => $this->t('Enforcement Mode'),
      '#open' => TRUE,
    ];
    $form['enforcement']['enforcement_mode'] = [
      '#type' => 'select',
      '#title' => $this->t('Mode'),
      '#options' => [
        'disabled' => $this->t('Disabled — no JWT checking'),
        'grace_period' => $this->t('Grace period — log but allow all requests'),
        'enforced' => $this->t('Enforced — reject requests without valid JWT'),
      ],
      '#default_value' => $config->get('enforcement_mode'),
    ];
    $form['enforcement']['dev_bypass_enabled'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable developer bypass'),
      '#description' => $this->t('Allow specific IPs to skip JWT validation.'),
      '#default_value' => $config->get('dev_bypass_enabled'),
    ];
    $form['enforcement']['dev_bypass_ips'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Bypass IP addresses'),
      '#description' => $this->t('One IP per line. These IPs skip JWT validation.'),
      '#default_value' => $config->get('dev_bypass_ips'),
      '#states' => [
        'visible' => [
          ':input[name="dev_bypass_enabled"]' => ['checked' => TRUE],
        ],
      ],
    ];
    $form['enforcement']['debug_logging'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable debug logging'),
      '#description' => $this->t('Log detailed diagnostics for App Attest, Play Integrity, and JWT operations. Disable in production.'),
      '#default_value' => $config->get('debug_logging'),
    ];

    // Google Play Integrity.
    $form['google'] = [
      '#type' => 'details',
      '#title' => $this->t('Google Play Integrity'),
    ];
    $form['google']['google_package_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Package name'),
      '#description' => $this->t('Android app package name (e.g., org.unicef.bebbo).'),
      '#default_value' => $config->get('google_package_name'),
    ];
    $form['google']['google_project_number'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Project number'),
      '#description' => $this->t('Google Cloud project number.'),
      '#default_value' => $config->get('google_project_number'),
    ];
    $form['google']['google_verdict_freshness_seconds'] = [
      '#type' => 'number',
      '#title' => $this->t('Verdict freshness (seconds)'),
      '#description' => $this->t('Maximum age of an integrity verdict.'),
      '#default_value' => $config->get('google_verdict_freshness_seconds'),
    ];
    $form['google']['google_api_timeout'] = [
      '#type' => 'number',
      '#title' => $this->t('API timeout (seconds)'),
      '#description' => $this->t('HTTP timeout for Google API requests.'),
      '#default_value' => $config->get('google_api_timeout') ?: 10,
    ];
    $form['google']['google_allow_unrecognized_version'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Accept UNRECOGNIZED_VERSION app verdict'),
      '#description' => $this->t('Dev/testing only — allows sideloaded debug builds (adb installs) that Google has not published. Every acceptance is logged as a warning. <strong>Never enable in production.</strong>'),
      '#default_value' => $config->get('google_allow_unrecognized_version'),
    ];

    // Apple App Attest.
    $form['apple'] = [
      '#type' => 'details',
      '#title' => $this->t('Apple App Attest'),
    ];
    $form['apple']['apple_team_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Team ID'),
      '#description' => $this->t('10-character alphanumeric Apple Team ID.'),
      '#default_value' => $config->get('apple_team_id'),
      '#maxlength' => 10,
    ];
    $form['apple']['apple_bundle_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Bundle ID'),
      '#description' => $this->t('iOS app bundle identifier.'),
      '#default_value' => $config->get('apple_bundle_id'),
    ];
    $form['apple']['apple_production_mode'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Production mode'),
      '#description' => $this->t('Uncheck for development/sandbox testing.'),
      '#default_value' => $config->get('apple_production_mode'),
    ];
    $form['apple']['apple_root_ca_pem'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Apple App Attestation Root CA (PEM)'),
      '#description' => $this->t('PEM-encoded root CA certificate. Download from https://www.apple.com/certificateauthority/Apple_App_Attestation_Root_CA.pem'),
      '#default_value' => $config->get('apple_root_ca_pem'),
      '#rows' => 14,
    ];

    // Token Lifetimes.
    $form['tokens'] = [
      '#type' => 'details',
      '#title' => $this->t('Token Lifetimes'),
    ];
    $form['tokens']['jwt_expiry_seconds'] = [
      '#type' => 'number',
      '#title' => $this->t('JWT expiry (seconds)'),
      '#default_value' => $config->get('jwt_expiry_seconds'),
    ];
    $form['tokens']['refresh_expiry_seconds'] = [
      '#type' => 'number',
      '#title' => $this->t('Refresh token expiry (seconds)'),
      '#default_value' => $config->get('refresh_expiry_seconds'),
    ];
    $form['tokens']['refresh_rotation_enabled'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable refresh token rotation'),
      '#description' => $this->t('Issue new refresh token on each use. Recommended for security.'),
      '#default_value' => $config->get('refresh_rotation_enabled'),
    ];

    // Rate Limiting.
    $form['rate_limiting'] = [
      '#type' => 'details',
      '#title' => $this->t('Rate Limiting'),
    ];
    $form['rate_limiting']['register_rate_limit'] = [
      '#type' => 'number',
      '#title' => $this->t('Registration limit (per device per hour)'),
      '#default_value' => $config->get('register_rate_limit'),
    ];
    $form['rate_limiting']['device_register_ip_rate_limit'] = [
      '#type' => 'number',
      '#title' => $this->t('Device registration limit (per IP per hour)'),
      '#default_value' => $config->get('device_register_ip_rate_limit') ?: 5,
    ];
    $form['rate_limiting']['verify_rate_limit'] = [
      '#type' => 'number',
      '#title' => $this->t('Verification limit (per device per hour)'),
      '#default_value' => $config->get('verify_rate_limit') ?: 10,
    ];
    $form['rate_limiting']['refresh_rate_limit'] = [
      '#type' => 'number',
      '#title' => $this->t('Refresh limit (per device per hour)'),
      '#default_value' => $config->get('refresh_rate_limit'),
    ];

    // Operations.
    $form['operations'] = [
      '#type' => 'details',
      '#title' => $this->t('Operations'),
    ];
    $form['operations']['challenge_expiry_seconds'] = [
      '#type' => 'number',
      '#title' => $this->t('Challenge expiry (seconds)'),
      '#description' => $this->t('How long a sideloaded device has to respond to a challenge.'),
      '#default_value' => $config->get('challenge_expiry_seconds') ?: 120,
    ];
    $form['operations']['revoked_token_retention_days'] = [
      '#type' => 'number',
      '#title' => $this->t('Revoked token retention (days)'),
      '#description' => $this->t('Number of days to keep revoked refresh tokens before purging.'),
      '#default_value' => $config->get('revoked_token_retention_days') ?: 7,
    ];
    $form['operations']['security_log_max_entries'] = [
      '#type' => 'number',
      '#title' => $this->t('Maximum security log entries'),
      '#description' => $this->t('Keep only the most recent N entries in the security log.'),
      '#default_value' => $config->get('security_log_max_entries') ?: 10000,
    ];

    // API Protection.
    $form['api_protection'] = [
      '#type' => 'details',
      '#title' => $this->t('API Protection'),
    ];
    $form['api_protection']['protected_api_patterns'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Protected API route patterns'),
      '#description' => $this->t('One pattern per line. Routes matching these require JWT validation. Example: /v2/api/'),
      '#default_value' => $config->get('protected_api_patterns'),
      '#rows' => 4,
    ];
    $form['api_protection']['excluded_api_patterns'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Excluded API route patterns'),
      '#description' => $this->t('One pattern per line. These routes skip JWT even if they match a protected pattern. Example: /api/security/'),
      '#default_value' => $config->get('excluded_api_patterns'),
      '#rows' => 4,
    ];

    // Data Management.
    $form['data_management'] = [
      '#type' => 'details',
      '#title' => $this->t('Data Management'),
    ];
    $form['data_management']['purge_expired'] = [
      '#type' => 'submit',
      '#value' => $this->t('Purge Expired Data'),
      '#description' => $this->t('Remove expired challenges, revoked tokens, and trim security log per retention settings above.'),
      '#submit' => ['::purgeExpiredSubmit'],
      '#limit_validation_errors' => [],
      '#attributes' => ['class' => ['button']],
    ];
    $form['data_management']['purge_description'] = [
      '#markup' => '<p>' . $this->t('Remove expired challenges, revoked/expired tokens, and trim the security log per retention settings.') . '</p>',
    ];
    $form['data_management']['truncate_all'] = [
      '#type' => 'submit',
      '#value' => $this->t('Truncate All Security Tables'),
      '#submit' => ['::truncateAllSubmit'],
      '#limit_validation_errors' => [],
      '#attributes' => [
        'class' => ['button', 'button--danger'],
        'onclick' => 'return confirm("This will permanently delete ALL data from devices, challenges, tokens, and security log tables. Auto-increment resets to 1. This cannot be undone. Continue?")',
      ],
    ];
    $form['data_management']['truncate_description'] = [
      '#markup' => '<p>' . $this->t('Delete ALL data from devices, challenges, tokens, and security log. Auto-increment resets to 1. <strong>Cannot be undone.</strong>') . '</p>',
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    $team_id = $form_state->getValue('apple_team_id');
    if (!empty($team_id) && !preg_match('/^[A-Z0-9]{10}$/', $team_id)) {
      $form_state->setErrorByName('apple_team_id', $this->t('Apple Team ID must be 10 alphanumeric characters.'));
    }

    $bypass_ips = $form_state->getValue('dev_bypass_ips');
    if (!empty($bypass_ips)) {
      foreach (array_filter(array_map('trim', explode("\n", $bypass_ips))) as $ip) {
        if (!filter_var($ip, FILTER_VALIDATE_IP)) {
          $form_state->setErrorByName('dev_bypass_ips', $this->t('@ip is not a valid IP address.', ['@ip' => $ip]));
          break;
        }
      }
    }

    $pem = trim($form_state->getValue('apple_root_ca_pem') ?? '');
    if (!empty($pem) && !str_contains($pem, '-----BEGIN CERTIFICATE-----')) {
      $form_state->setErrorByName('apple_root_ca_pem', $this->t('Invalid PEM: must contain -----BEGIN CERTIFICATE----- header.'));
    }

    $numeric_ranges = [
      'google_verdict_freshness_seconds' => [60, 3600],
      'google_api_timeout' => [5, 30],
      'jwt_expiry_seconds' => [300, 86400],
      'refresh_expiry_seconds' => [86400, 7776000],
      'register_rate_limit' => [1, 100],
      'device_register_ip_rate_limit' => [1, 50],
      'verify_rate_limit' => [1, 100],
      'refresh_rate_limit' => [1, 200],
      'challenge_expiry_seconds' => [30, 600],
      'revoked_token_retention_days' => [1, 90],
      'security_log_max_entries' => [1000, 100000],
    ];
    foreach ($numeric_ranges as $field => [$min, $max]) {
      $val = $form_state->getValue($field);
      if ($val !== '' && $val !== NULL && (string) $val !== '0' && !empty($val) && ((int) $val < $min || (int) $val > $max)) {
        $form_state->setErrorByName($field, $this->t('Value must be between @min and @max.', [
          '@min' => $min,
          '@max' => $max,
        ]));
      }
    }

    $this->validatePatternField($form_state, 'protected_api_patterns');
    $this->validatePatternField($form_state, 'excluded_api_patterns');
  }

  /**
   * Validate that each line in a textarea field is a valid regex pattern.
   */
  private function validatePatternField(FormStateInterface $form_state, string $field): void {
    $raw = trim($form_state->getValue($field) ?? '');
    if (empty($raw)) {
      return;
    }
    foreach (array_filter(array_map('trim', explode("\n", $raw))) as $pattern) {
      if (@preg_match('#^' . $pattern . '#', '') === FALSE) {
        $form_state->setErrorByName($field, $this->t('Invalid regex pattern: @pattern', ['@pattern' => $pattern]));
        break;
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    parent::submitForm($form, $form_state);

    $this->config('bebbo_api_security.settings')
      ->set('enforcement_mode', $form_state->getValue('enforcement_mode'))
      ->set('dev_bypass_enabled', (bool) $form_state->getValue('dev_bypass_enabled'))
      ->set('dev_bypass_ips', $form_state->getValue('dev_bypass_ips'))
      ->set('debug_logging', (bool) $form_state->getValue('debug_logging'))
      ->set('google_package_name', $form_state->getValue('google_package_name'))
      ->set('google_project_number', $form_state->getValue('google_project_number'))
      ->set('google_verdict_freshness_seconds', (int) $form_state->getValue('google_verdict_freshness_seconds'))
      ->set('google_api_timeout', (int) $form_state->getValue('google_api_timeout'))
      ->set('google_allow_unrecognized_version', (bool) $form_state->getValue('google_allow_unrecognized_version'))
      ->set('apple_team_id', $form_state->getValue('apple_team_id'))
      ->set('apple_bundle_id', $form_state->getValue('apple_bundle_id'))
      ->set('apple_production_mode', (bool) $form_state->getValue('apple_production_mode'))
      ->set('apple_root_ca_pem', trim($form_state->getValue('apple_root_ca_pem') ?? ''))
      ->set('jwt_expiry_seconds', (int) $form_state->getValue('jwt_expiry_seconds'))
      ->set('refresh_expiry_seconds', (int) $form_state->getValue('refresh_expiry_seconds'))
      ->set('refresh_rotation_enabled', (bool) $form_state->getValue('refresh_rotation_enabled'))
      ->set('register_rate_limit', (int) $form_state->getValue('register_rate_limit'))
      ->set('device_register_ip_rate_limit', (int) $form_state->getValue('device_register_ip_rate_limit'))
      ->set('verify_rate_limit', (int) $form_state->getValue('verify_rate_limit'))
      ->set('refresh_rate_limit', (int) $form_state->getValue('refresh_rate_limit'))
      ->set('challenge_expiry_seconds', (int) $form_state->getValue('challenge_expiry_seconds'))
      ->set('revoked_token_retention_days', (int) $form_state->getValue('revoked_token_retention_days'))
      ->set('security_log_max_entries', (int) $form_state->getValue('security_log_max_entries'))
      ->set('protected_api_patterns', trim($form_state->getValue('protected_api_patterns') ?? ''))
      ->set('excluded_api_patterns', trim($form_state->getValue('excluded_api_patterns') ?? ''))
      ->save();
  }

  /**
   * Submit handler for the "Purge Expired Data" button.
   */
  public function purgeExpiredSubmit(array &$form, FormStateInterface $form_state): void {
    $stats = $this->registry->purgeExpired();
    $this->messenger()->addStatus($this->t('Purged: @challenges expired challenges, @revoked revoked tokens, @expired expired tokens, @logs old log entries.', [
      '@challenges' => $stats['challenges'],
      '@revoked' => $stats['tokens_revoked'],
      '@expired' => $stats['tokens_expired'],
      '@logs' => $stats['logs'],
    ]));
  }

  /**
   * Submit handler for the "Truncate All Security Tables" button.
   */
  public function truncateAllSubmit(array &$form, FormStateInterface $form_state): void {
    $stats = $this->registry->truncateAll();
    $this->messenger()->addWarning($this->t('Truncated all security tables: @devices devices, @challenges challenges, @tokens refresh tokens, @logs log entries removed.', [
      '@devices' => $stats['devices'],
      '@challenges' => $stats['challenges'],
      '@tokens' => $stats['tokens'],
      '@logs' => $stats['logs'],
    ]));
  }

}
