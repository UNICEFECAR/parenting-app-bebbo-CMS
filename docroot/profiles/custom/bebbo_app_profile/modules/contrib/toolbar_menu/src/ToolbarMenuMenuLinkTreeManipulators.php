<?php

namespace Drupal\toolbar_menu;

/**
 * Provides a couple of menu link tree manipulators.
 *
 * This class provides menu link tree manipulators to:
 * - add icons on each toolbar menu entries.
 */
class ToolbarMenuMenuLinkTreeManipulators {

  protected $toolbarMenuManager;

  /**
   * Construct a new ToolbarMenuMenuLinkTreeManipulators.
   *
   * @param \Drupal\toolbar_menu\ToolbarMenuManager $toolbar_menu_manager
   *   The toolbar_menu manager.
   */
  public function __construct(ToolbarMenuManager $toolbar_menu_manager) {
    $this->toolbarMenuManager = $toolbar_menu_manager;
  }

  /**
   * Add icons on each toolbar menu items.
   *
   * @param \Drupal\Core\Menu\MenuLinkTreeElement[] $tree
   *   The menu link tree to manipulate.
   *
   * @return \Drupal\Core\Menu\MenuLinkTreeElement[]
   *   The manipulated menu link tree.
   */
  public function addIcons(array $tree) {
    foreach ($tree as $element) {
      $element->options['attributes']['class'][] = 'toolbar-icon';
      $element->options['attributes']['class'][] = 'toolbar-icon-link-toolbar-menu';
      $element->options['attributes']['class'][] = 'toolbar-icon-link-toolbar-menu-' . $this->toolbarMenuManager->cleanId($element->link->getPluginId());
      $element->options['attributes']['title'] = $element->link->getTitle();
    }
    return $tree;
  }

}
