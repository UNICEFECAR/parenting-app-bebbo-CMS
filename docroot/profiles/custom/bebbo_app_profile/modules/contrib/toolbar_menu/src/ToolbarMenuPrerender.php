<?php

namespace Drupal\toolbar_menu;

use Drupal\Core\Menu\MenuTreeParameters;
use Drupal\Core\Security\TrustedCallbackInterface;

/**
 * Defines a service for toolbar menu prerender elements.
 */
class ToolbarMenuPrerender implements TrustedCallbackInterface {

  /**
   * {@inheritdoc}
   */
  public static function trustedCallbacks() {
    return [
      'prerenderToolbarTray',
    ];
  }

  /**
   * Pre-render the toolbar_menu tray element.
   *
   * @param array $element
   *   The tray element to pre-render.
   *
   * @return array
   *   The pre-rendered tray element.
   */
  public static function prerenderToolbarTray(array $element) {
    /** @var \Drupal\toolbar\Menu\ToolbarMenuLinkTree $menu_tree */
    $menu_tree = \Drupal::service('toolbar.menu_tree');

    $parameters = new MenuTreeParameters();
    $parameters->excludeRoot()->onlyEnabledLinks();

    $tree = $menu_tree->load($element['#id'], $parameters);

    $manipulators = [
      ['callable' => 'menu.default_tree_manipulators:checkAccess'],
      ['callable' => 'menu.default_tree_manipulators:generateIndexAndSort'],
      ['callable' => 'toolbar_menu.menu_link_tree_manipulators:addIcons'],
    ];
    $tree = $menu_tree->transform($tree, $manipulators);

    $element['toolbar_menu_' . $element['#id']] = $menu_tree->build($tree);

    return $element;
  }

}
