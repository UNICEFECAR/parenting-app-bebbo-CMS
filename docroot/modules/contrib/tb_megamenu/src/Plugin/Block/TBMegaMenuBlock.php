<?php

namespace Drupal\tb_megamenu\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Theme\ThemeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\tb_megamenu\TBMegaMenuBuilderInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides blocks which belong to TB Mega Menu.
 *
 * @Block(
 *   id = "tb_megamenu_menu_block",
 *   admin_label = @Translation("TB Mega Menu"),
 *   category = @Translation("TB Mega Menu"),
 *   deriver = "Drupal\tb_megamenu\Plugin\Derivative\TBMegaMenuBlock",
 * )
 */
class TBMegaMenuBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * Current theme name.
   *
   * @var string
   */
  protected string $themeName;

  /**
   * The theme manager service.
   *
   * @var \Drupal\Core\Theme\ThemeManagerInterface
   */
  protected ThemeManagerInterface $themeManager;

  /**
   * The menu builder service.
   *
   * @var \Drupal\tb_megamenu\TBMegaMenuBuilderInterface
   */
  protected TBMegaMenuBuilderInterface $menuBuilder;

  /**
   * Constructs a TBMegaMenuBlock.
   *
   * @param array $configuration
   *   Configuration array.
   * @param string $plugin_id
   *   The plugin id.
   * @param mixed $plugin_definition
   *   The plugin definition.
   * @param \Drupal\Core\Theme\ThemeManagerInterface $theme_manager
   *   The theme manager service.
   * @param \Drupal\tb_megamenu\TBMegaMenuBuilderInterface $menu_builder
   *   The menu builder service.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, ThemeManagerInterface $theme_manager, TBMegaMenuBuilderInterface $menu_builder) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->themeManager = $theme_manager;
    $this->menuBuilder = $menu_builder;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): TBMegaMenuBlock|ContainerFactoryPluginInterface|static {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('theme.manager'),
      $container->get('tb_megamenu.menu_builder')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build(): array {
    $menu_name = $this->getDerivativeId();
    $theme_name = $this->getThemeName();
    $menu = $this->menuBuilder->getMenus($menu_name, $theme_name);
    if ($menu === NULL) {
      return [];
    }
    return [
      '#theme' => 'tb_megamenu',
      '#menu_name' => $menu_name,
      '#block_theme' => $theme_name,
      '#attached' => ['library' => ['tb_megamenu/base', 'tb_megamenu/styles']],
      '#post_render' => ['\Drupal\tb_megamenu\Controller\TBMegaMenuController::tbMegamenuAttachNumberColumns'],
    ];
  }

  /**
   * Default cache is disabled.
   *
   * @param array $form
   *   The form definition.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state information.
   *
   * @return array
   *   The configuration render array
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $rebuild_form = parent::buildConfigurationForm($form, $form_state);
    $rebuild_form['cache']['max_age']['#default_value'] = 0;
    return $rebuild_form;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTags(): array {
    // Rebuild block when menu or config changes.
    $configName = "{$this->getDerivativeId()}__{$this->getThemeName()}";
    $cacheTags = parent::getCacheTags();
    $cacheTags[] = 'config:system.menu.' . $this->getDerivativeId();
    $cacheTags[] = 'config:tb_megamenu.menu_config.' . $configName;
    return $cacheTags;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheContexts(): array {
    // ::build() uses MenuLinkTreeInterface::getCurrentRouteMenuTreeParameters()
    // to generate menu tree parameters, and those take the active menu trail
    // into account. Therefore, we must vary the rendered menu by the active
    // trail of the rendered menu.
    // Additional cache contexts, e.g. those that determine link text or
    // accessibility of a menu, will be bubbled automatically.
    $menu_name = $this->getDerivativeId();
    return Cache::mergeContexts(parent::getCacheContexts(), ['route.menu_active_trails:' . $menu_name]);
  }

  /**
   * Get the current Theme Name.
   *
   * @return string
   *   The current theme name.
   */
  public function getThemeName(): string {
    if (!isset($this->themeName)) {
      $this->themeName = $this->themeManager->getActiveTheme()->getName();
    }
    return $this->themeName;
  }

}
