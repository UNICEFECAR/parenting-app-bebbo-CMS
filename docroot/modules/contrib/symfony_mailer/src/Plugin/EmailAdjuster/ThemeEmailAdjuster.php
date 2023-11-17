<?php

namespace Drupal\symfony_mailer\Plugin\EmailAdjuster;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Extension\ThemeHandlerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Theme\ThemeManagerInterface;
use Drupal\symfony_mailer\EmailInterface;
use Drupal\symfony_mailer\Processor\EmailAdjusterBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines the Theme Email Adjuster.
 *
 * @EmailAdjuster(
 *   id = "email_theme",
 *   label = @Translation("Theme"),
 *   description = @Translation("Sets the email theme."),
 *   weight = 0,
 * )
 */
class ThemeEmailAdjuster extends EmailAdjusterBase implements ContainerFactoryPluginInterface {

  /**
   * The configuration factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The theme manager.
   *
   * @var \Drupal\Core\Theme\ThemeManagerInterface
   */
  protected $themeManager;


  /**
   * The theme handler.
   *
   * @var \Drupal\Core\Extension\ThemeHandlerInterface
   */
  protected $themeHandler;

  /**
   * Constructs a new ThemeEmailAdjuster object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin ID for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The configuration factory.
   * @param \Drupal\Core\Theme\ThemeManagerInterface $theme_manager
   *   The theme manager.
   * @param \Drupal\Core\Extension\ThemeHandlerInterface $theme_handler
   *   The theme handler.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, ConfigFactoryInterface $config_factory, ThemeManagerInterface $theme_manager, ThemeHandlerInterface $theme_handler) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->configFactory = $config_factory;
    $this->themeManager = $theme_manager;
    $this->themeHandler = $theme_handler;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('config.factory'),
      $container->get('theme.manager'),
      $container->get('theme_handler'),
      $container->get('library.discovery')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build(EmailInterface $email) {
    $theme_name = $this->getEmailTheme();
    $email->setTheme($theme_name);
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $form['theme'] = [
      '#type' => 'select',
      '#title' => $this->t('Theme'),
      '#description' => $this->t('Select the theme that will be used to render emails which are configured for this. This can be either the default theme, the active theme with fallback to default theme (if the active theme is the admin theme) or any enabled theme.'),
      '#options' => $this->getThemes(),
      '#required' => TRUE,
      '#default_value' => $this->configuration['theme'] ?? NULL,
    ];

    return $form;
  }

  /**
   * Returns a list of theme options.
   *
   * @return string[]
   *   The theme options.
   */
  protected function getThemes() {
    $options = [
      '_default' => $this->t('Default'),
      '_active_fallback' => $this->t('Active with fallback'),
    ];

    foreach ($this->themeHandler->listInfo() as $name => $theme) {
      if ($theme->status) {
        $options[$name] = $theme->info['name'];
      }
    }

    return $options;
  }

  /**
   * Returns the name of the theme to render the email.
   */
  protected function getEmailTheme() {
    $theme = $this->configuration['theme'];
    $theme_config = $this->configFactory->get('system.theme');

    switch ($theme) {
      case '_default':
        $theme = $theme_config->get('default');
        break;

      case '_active_fallback':
        $theme = $this->themeManager->getActiveTheme()->getName();
        if ($theme == $theme_config->get('admin')) {
          $theme = $theme_config->get('default');
        }
        break;
    }

    return $theme;
  }

  /**
   * {@inheritdoc}
   */
  public function getSummary() {
    return $this->getThemes()[$this->configuration['theme']];
  }

}
