<?php

namespace Drupal\shield\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Render\Markup;
use Drupal\key\Plugin\KeyPluginManager;
use Drupal\shield\ShieldMiddleware;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configure site information settings for this site.
 */
class ShieldSettingsForm extends ConfigFormBase {

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * The key type manager, if exists.
   *
   * @var \Drupal\key\Plugin\KeyPluginManager|null
   */
  protected $keyTypeManager;

  /**
   * ShieldSettingsForm constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $moduleHandler
   *   The module handler.
   * @param \Drupal\key\Plugin\KeyPluginManager|null $keyTypeManager
   *   The key plugin manager.
   */
  public function __construct(ConfigFactoryInterface $config_factory, ModuleHandlerInterface $moduleHandler, KeyPluginManager $keyTypeManager = NULL) {
    parent::__construct($config_factory);
    $this->moduleHandler = $moduleHandler;
    $this->keyTypeManager = $keyTypeManager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('module_handler'),
      $container->get('plugin.manager.key.key_type', ContainerInterface::NULL_ON_INVALID_REFERENCE)
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['shield.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'shield_admin_settings';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $shield_config = $this->config('shield.settings');

    // Submitted form values should be nested.
    $form['#tree'] = TRUE;

    $form['description'] = [
      '#type' => 'item',
      '#title' => $this->t('Shield settings'),
      '#description' => $this->t('Set up credentials for an authenticated user. You can also decide whether you want to print out the credentials or not.'),
    ];

    $form['general'] = [
      '#id' => 'general',
      '#type' => 'details',
      '#title' => $this->t('General'),
      '#open' => TRUE,
    ];

    $form['general']['shield_enable'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable Shield'),
      '#description' => $this->t('Enable/Disable shield functionality. All other settings are ignored if this is not checked.'),
      '#default_value' => $shield_config->get('shield_enable'),
    ];

    $form['general']['shield_print'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Authentication message'),
      '#description' => $this->t("The message to print in the authentication request popup. You can use [user] and [pass] to print the user and the password respectively. You can leave it empty, if you don't want to print out any special message to the users."),
      '#default_value' => $shield_config->get('print'),
    ];

    $form['general']['debug_header'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable debug header'),
      '#description' => $this->t('Add a X-Shield-Status header in response to indicate if shield is disabled, skipped, enabled to ease debug.'),
      '#default_value' => $shield_config->get('debug_header'),
    ];

    $form['credentials'] = [
      '#id' => 'credentials',
      '#type' => 'details',
      '#title' => $this->t('Credentials'),
      '#open' => TRUE,
    ];

    $credential_provider = $shield_config->get('credential_provider');
    if ($form_state->hasValue(['credentials', 'credential_provider'])) {
      $credential_provider = $form_state->getValue([
        'credentials',
        'credential_provider',
      ]);
    }

    $form['credentials']['credential_provider'] = [
      '#type' => 'select',
      '#title' => $this->t('Credential provider'),
      '#options' => [
        'shield' => 'Shield',
      ],
      '#default_value' => $credential_provider,
      '#ajax' => [
        'callback' => [$this, 'ajaxCallback'],
        'wrapper' => 'credentials_configuration',
        'method' => 'replace',
        'effect' => 'fade',
      ],
    ];

    $form['credentials']['providers'] = [
      '#type' => 'item',
      '#id' => 'credentials_configuration',
    ];

    if ($this->keyTypeManager) {
      $form['credentials']['credential_provider']['#options']['key'] = $this->t('Key Module');

      if ($this->keyTypeManager->hasDefinition('user_password')) {
        $form['credentials']['credential_provider']['#options']['multikey'] = $this->t('Key Module (user/password)');
      }
    }

    if ($credential_provider == 'shield') {
      $form['credentials']['providers']['shield']['user'] = [
        '#type' => 'textfield',
        '#title' => $this->t('User'),
        '#default_value' => $shield_config->get('credentials.shield.user'),
      ];
      $form['credentials']['providers']['shield']['pass'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Password'),
        '#default_value' => $shield_config->get('credentials.shield.pass'),
      ];
    }
    elseif ($credential_provider == 'key') {
      $form['credentials']['providers']['key']['user'] = [
        '#type' => 'textfield',
        '#title' => $this->t('User'),
        '#default_value' => $shield_config->get('credentials.key.user'),
        '#required' => TRUE,
      ];
      $form['credentials']['providers']['key']['pass_key'] = [
        '#type' => 'key_select',
        '#title' => $this->t('Password'),
        '#default_value' => $shield_config->get('credentials.key.pass_key'),
        '#empty_option' => $this->t('- Please select -'),
        '#key_filters' => ['type' => 'authentication'],
        '#required' => TRUE,
      ];
    }
    elseif ($credential_provider == 'multikey') {
      $form['credentials']['providers']['multikey']['user_pass_key'] = [
        '#type' => 'key_select',
        '#title' => $this->t('User/password'),
        '#default_value' => $shield_config->get('credentials.multikey.user_pass_key'),
        '#empty_option' => $this->t('- Please select -'),
        '#key_filters' => ['type' => 'user_password'],
        '#required' => TRUE,
      ];
    }

    $form['exceptions'] = [
      '#id' => 'exceptions',
      '#type' => 'details',
      '#title' => $this->t('Exceptions'),
      '#open' => TRUE,
    ];

    $form['exceptions']['shield_allow_cli'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Allow command line access'),
      '#description' => $this->t('When the site is accessed from command line (e.g. from Drush, cron), the shield should not work.'),
      '#default_value' => $shield_config->get('allow_cli'),
    ];

    $form['exceptions']['allowlist'] = [
      '#type' => 'textarea',
      '#title' => $this->t('IP Allowlist'),
      '#description' => $this->t("Enter list of IP's for which shield should not be shown, one per line. You can use Network ranges in the format 'IP/Range'.<br><em>Warning: IP allowlists interfere with reverse proxy caching! @strong_style_tag Do not use allowlist if reverse proxy caching is in use!</strong></em>", [
        '@strong_style_tag' => Markup::create("<strong style='color:red'>"),
      ]),
      '#default_value' => $shield_config->get('allowlist'),
      '#placeholder' => $this->t("Example:\n192.168.0.1/24\n127.0.0.1\n2001:0db8:0000:0000:0000:8a2e:0370:7334\n2001:db8::8a2e:370:7334"),
    ];

    $form['exceptions']['http_method_allowlist'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('HTTP method allowlist'),
      '#description' => $this->t('Select the HTTP methods for which shield should not be shown.'),
      '#options' => [
        'get' => $this->t('GET'),
        'post' => $this->t('POST'),
        'delete' => $this->t('DELETE'),
        'head' => $this->t('HEAD'),
        'put' => $this->t('PUT'),
        'connect' => $this->t('CONNECT'),
        'options' => $this->t('OPTIONS'),
        'trace' => $this->t('TRACE'),
        'patch' => $this->t('PATCH'),
      ],
      '#default_value' => $shield_config->get('http_method_allowlist') ?? [],
    ];

    $form['exceptions']['shield_domains'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Allowlist Domains'),
      '#description' => $this->t('Enter list of domain host names for which shield should not be shown, one per line.'),
      '#default_value' => $shield_config->get('domains'),
      '#placeholder' => $this->t("Example:\nexample.com\ndomain.in"),
    ];

    $form['exceptions']['paths'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Paths'),
      '#description' => $this->t('According to the Shield path method selected above, these paths will be either excluded from, or included in Shield protection. Leave this blank and select "exclude" to protect all paths. Include a leading slash.'),
    ];
    $form['exceptions']['paths']['shield_method'] = [
      '#type' => 'radios',
      '#title' => $this->t('Path Method'),
      '#default_value' => $shield_config->get('method'),
      '#options' => [
        ShieldMiddleware::EXCLUDE_METHOD => $this->t('Exclude'),
        ShieldMiddleware::INCLUDE_METHOD => $this->t('Include'),
      ],
    ];
    $form['exceptions']['paths']['shield_paths'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Paths'),
      '#default_value' => $shield_config->get('paths'),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $shield_config = $this->config('shield.settings');
    $credential_provider = $form_state->getValue([
      'credentials',
      'credential_provider',
    ]);
    $http_method_allowlist = array_filter($form_state->getValue([
      'exceptions',
      'http_method_allowlist',
    ]));

    $shield_config
      ->set('shield_enable', $form_state->getValue([
        'general',
        'shield_enable',
      ]))
      ->set('print', $form_state->getValue([
        'general',
        'shield_print',
      ]))
      ->set('debug_header', $form_state->getValue([
        'general',
        'debug_header',
      ]))
      ->set('allow_cli', $form_state->getValue([
        'exceptions',
        'shield_allow_cli',
      ]))
      ->set('allowlist', $form_state->getValue([
        'exceptions',
        'allowlist',
      ]))
      ->set('http_method_allowlist', $http_method_allowlist)
      ->set('domains', $form_state->getValue([
        'exceptions',
        'shield_domains',
      ]))
      ->set('method', $form_state->getValue([
        'exceptions',
        'paths',
        'shield_method',
      ]))
      ->set('paths', $form_state->getValue([
        'exceptions',
        'paths',
        'shield_paths',
      ]))
      ->set('credential_provider', $credential_provider);
    $credentials = $form_state->getValue([
      'credentials',
      'providers',
      $credential_provider,
    ]);
    $shield_config->set('credentials', [$credential_provider => $credentials]);
    $shield_config->save();

    parent::submitForm($form, $form_state);
  }

  /**
   * Ajax callback for the credential dependent configuration options.
   *
   * @return array
   *   The form element containing the configuration options.
   */
  public static function ajaxCallback($form, FormStateInterface $form_state) {
    return $form['credentials']['providers'];
  }

}
