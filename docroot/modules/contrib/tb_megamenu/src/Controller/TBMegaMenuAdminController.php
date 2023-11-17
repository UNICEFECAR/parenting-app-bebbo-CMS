<?php

namespace Drupal\tb_megamenu\Controller;

use Drupal\Core\Access\CsrfTokenGenerator;
use Drupal\Core\Config\Entity\ConfigEntityInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Menu\MenuTreeParameters;
use Drupal\Core\Url;
use Drupal\tb_megamenu\Entity\MegaMenuConfig;
use Drupal\tb_megamenu\TBMegaMenuBuilderInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Menu\MenuLinkTreeInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\Component\Serialization\Json;

/**
 * Handler for configuring and saving MegaMenu settings.
 */
class TBMegaMenuAdminController extends ControllerBase {

  /**
   * The menu tree service.
   *
   * @var \Drupal\Core\Menu\MenuLinkTreeInterface
   */
  protected MenuLinkTreeInterface $menuTree;

  /**
   * The renderer service.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected RendererInterface $renderer;

  /**
   * The menu builder service.
   *
   * @var \Drupal\tb_megamenu\TBMegaMenuBuilderInterface
   */
  private TBMegaMenuBuilderInterface $menuBuilder;

  /**
   * The CSRF Token Generator.
   *
   * @var \Drupal\Core\Access\CsrfTokenGenerator
   */
  protected CsrfTokenGenerator $csrfTokenGenerator;

  /**
   * Constructs a TBMegaMenuAdminController object.
   *
   * @param \Drupal\Core\Menu\MenuLinkTreeInterface $menu_tree
   *   The Menu Link Tree service.
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The renderer service.
   * @param \Drupal\tb_megamenu\TBMegaMenuBuilderInterface $menu_builder
   *   The menu builder service.
   * @param \Drupal\Core\Access\CsrfTokenGenerator $csrfTokenGenerator
   *   The CSRF Token Generator service.
   */
  public function __construct(MenuLinkTreeInterface $menu_tree, RendererInterface $renderer, TBMegaMenuBuilderInterface $menu_builder, CsrfTokenGenerator $csrfTokenGenerator) {
    $this->menuTree = $menu_tree;
    $this->renderer = $renderer;
    $this->menuBuilder = $menu_builder;
    $this->csrfTokenGenerator = $csrfTokenGenerator;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): TBMegaMenuAdminController|static {
    return new static(
      $container->get('menu.link_tree'),
      $container->get('renderer'),
      $container->get('tb_megamenu.menu_builder'),
      $container->get('csrf_token'),
    );
  }

  /**
   * Ajax callback for admin screen.
   *
   * Handles:  Save, Reset, and add block requests.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   A string response with either a success/error message or just data.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   * @throws \Exception
   */
  public function saveConfiguration(Request $request): Response {
    $data = NULL;
    $action = '';
    $result = 'Invalid TB Megamenu Ajax request!';

    // All ajax calls should use json data now.
    if ($request->getContentType() == 'json') {
      $data = Json::decode($request->getContent());
      $action = $data['action'];
    }
    // Assemble the appropriate Ajax response for the current action.
    switch ($action) {
      case 'load':
        $result = self::loadMenuConfig($data);
        break;

      case 'save':
        $result = self::saveMenuConfig($data);
        break;

      case 'load_block':
        $result = self::loadMenuBlock($data);
        break;

      default:
        break;
    }

    // Return the response message and status code.
    $response = new Response($result['message']);
    $response->setStatusCode($result['code']);
    return $response;
  }

  /**
   * Loads a menu configuration.
   *
   * @param array $data
   *   A decoded JSON object used to load the configuration.
   *
   * @return array
   *   The message and status code indicating the result of the load attempt.
   *
   * @throws \Exception
   */
  public function loadMenuConfig(array $data): array {
    $menu_name = self::getMenuName($data);
    $theme = self::getTheme($data);
    $code = 200;

    // Attempt to load the menu config.
    if ($menu_name && $theme) {
      $renderable_array = $this->menuBuilder->renderBlock($menu_name, $theme);
      $result = $this->renderer
        ->render($renderable_array)
        ->__toString();
    }
    // Display an error if the config can't be loaded.
    else {
      $result = self::saveError('load_config');
      $code = 500;
    }

    return [
      'message' => $result,
      'code' => $code,
    ];
  }

  /**
   * Saves a menu configuration.
   *
   * @param array $data
   *   A decoded JSON object used to save the configuration.
   *
   * @return array
   *   The message and status code indicating the result of the save attempt.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function saveMenuConfig(array $data): array {
    $menu_config = self::getMenuConfig($data);
    $block_config = self::getBlockConfig($data);
    $menu_name = self::getMenuName($data);
    $theme = self::getTheme($data);
    $code = 200;

    // Ensure the config can be loaded before proceeding.
    $config = MegaMenuConfig::loadMenu($menu_name, $theme);
    if ($config === NULL) {
      return [
        'message' => self::saveError('load_menu'),
        'code' => 500,
      ];
    }

    if ($menu_config && $menu_name && $block_config && $theme) {
      // This is parameter to load menu_tree with the enabled links.
      $menu_tree_parameters = (new MenuTreeParameters)->onlyEnabledLinks();
      // Load menu items with condition.
      $menu_items = $this->menuTree->load($menu_name, $menu_tree_parameters);
      // Sync mega menu before store.
      $this->menuBuilder->syncConfigAll($menu_items, $menu_config, 'backend');
      $this->menuBuilder->syncOrderMenus($menu_config);
      $config->setBlockConfig($block_config);
      $config->setMenuConfig($menu_config);
      // Save the config and return a success message.
      $saved_config = $config->save();
      if ($saved_config == 1 || $saved_config == 2) {
        $result = $this->t("Saved config sucessfully!");
      }
      else {
        $result = self::saveError('unknown');
        $code = 500;
      }
    }
    // Display an error when required values are missing.
    else {
      $result = self::saveError('missing_info', $menu_name, $theme, $block_config, $menu_config);
      $code = 500;
    }

    return [
      'message' => $result,
      'code' => $code,
    ];
  }

  /**
   * Displays and logs an error when config can't be saved.
   *
   * @param string $event
   *   The event that triggered the error.
   * @param string|null $menu_name
   *   The machine name for the current menu.
   * @param string|null $theme
   *   The machine name for the current theme.
   * @param array|null $block_config
   *   The configuration for the current block.
   * @param array|null $menu_config
   *   The configuration for the current menu.
   *
   * @return string
   *   An error message displayed to the user.
   */
  public function saveError(string $event, string $menu_name = NULL, string $theme = NULL, array $block_config = NULL, array $menu_config = NULL): string {
    $msg = $this->t("TB MegaMenu error:");

    switch ($event) {
      case 'load_menu':
        $msg .= ' ' . $this->t("could not load the requested menu.");
        break;

      case 'load_config':
        $msg .= ' ' . $this->t("could not (re)load the requested menu configuration.");
        break;

      case 'load_block':
        $msg .= ' ' . $this->t("could not load the requested menu block.");
        break;

      case 'missing_info':
        $problem = ($menu_name ? '' : "menu_name ") . ($theme ? '' : "theme_name ") .
        ($block_config ? '' : "block_config ") . ($menu_config ? '' : "menu_config");
        $msg .= ' ' . $this->t(
          "Post was missing the following information: @problem",
          ['@problem' => $problem]);
        break;

      default:
        $msg .= ' ' . $this->t("an unknown error occurred.");
    }

    return $msg;
  }

  /**
   * Loads a menu block.
   *
   * @param array $data
   *   A decoded JSON object used to load the block.
   *
   * @return array
   *   The message and status code indicating the result of the load attempt.
   *
   * @throws \Exception
   */
  public function loadMenuBlock(array $data): array {
    $block_id = $data['block_id'] ?? NULL;
    $id = $data['id'] ?? NULL;
    $showblocktitle = $data['showblocktitle'] ?? NULL;
    $code = 200;

    // Attempt to render the specified block.
    if ($block_id && $id) {
      $render = [
        '#theme' => 'tb_megamenu_block',
        '#block_id' => $block_id,
        '#section' => 'backend',
        '#showblocktitle' => $showblocktitle,
      ];
      $content = $this->renderer
        ->render($render)
        ->__toString();
      $result = Json::encode(['content' => $content, 'id' => $id]);
    }
    // Display an error if the block can't be loaded.
    else {
      $result = self::saveError('load_block');
      $code = 500;
    }

    return [
      'message' => $result,
      'code' => $code,
    ];
  }

  /**
   * Get the machine name of a menu.
   *
   * @param array $data
   *   A decoded JSON object used to load the configuration.
   *
   * @return mixed
   *   A string or null.
   */
  public function getMenuName(array $data): mixed {
    return $data['menu_name'] ?? NULL;
  }

  /**
   * Get the machine name of a theme.
   *
   * @param array $data
   *   A decoded JSON object used to load the configuration.
   *
   * @return mixed
   *   A string or null.
   */
  public function getTheme(array $data): mixed {
    return $data['theme'] ?? NULL;
  }

  /**
   * Get an existing menu configuration.
   *
   * @param array $data
   *   A decoded JSON object used to load the configuration.
   *
   * @return mixed
   *   An array or null.
   */
  public function getMenuConfig(array $data): mixed {
    return $data['menu_config'] ?? NULL;
  }

  /**
   * Get an existing block configuration.
   *
   * @param array $data
   *   A decoded JSON object used to load the configuration.
   *
   * @return mixed
   *   An array or null.
   */
  public function getBlockConfig(array $data): mixed {
    return $data['block_config'] ?? NULL;
  }

  /**
   * This is a menu page. To edit Mega Menu.
   *
   * @param \Drupal\Core\Config\Entity\ConfigEntityInterface $tb_megamenu
   *   The config entity interface.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request service.
   *
   * @return array
   *   Returns an array of mega menu config.
   */
  public function configMegaMenu(ConfigEntityInterface $tb_megamenu, Request $request): array {
    // Add a custom library.
    $page['#attached']['library'][] = 'tb_megamenu/form.configure-megamenu';
    $menu_name = !empty($tb_megamenu->menu) ? $tb_megamenu->menu : '';
    $url = Url::fromRoute('tb_megamenu.admin.save', ['tb_megamenu' => $menu_name]);
    $csrf_token = $this->csrfTokenGenerator->get($url->getInternalPath());
    $url->setOptions(['absolute' => TRUE, 'query' => ['token' => $csrf_token]]);
    $abs_url_config = $url->toString();
    $page['#attached']['drupalSettings']['TBMegaMenu']['saveConfigURL'] = $abs_url_config;

    if (!empty($tb_megamenu->menu) && !empty($tb_megamenu->theme)) {
      $page['tb_megamenu'] = [
        '#theme' => 'tb_megamenu_backend',
        '#menu_name' => $tb_megamenu->menu,
        '#block_theme' => $tb_megamenu->theme,
      ];
    }

    return $page;
  }

}
