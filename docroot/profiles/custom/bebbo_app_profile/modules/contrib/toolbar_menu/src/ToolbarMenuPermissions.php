<?php

namespace Drupal\toolbar_menu;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides dynamic permissions of the toolbar_menu module.
 */
class ToolbarMenuPermissions implements ContainerInjectionInterface {

  /**
   * The toolbar menu manager.
   *
   * @var \Drupal\toolbar_menu\ToolbarMenuManager
   */
  protected $toolbarMenuManager;

  /**
   * Constructs a new ToolbarMenuPermissions instance.
   *
   * @param \Drupal\toolbar_menu\ToolbarMenuManager $toolbar_menu
   *   The toolbar menu manager.
   */
  public function __construct(ToolbarMenuManager $toolbar_menu) {
    $this->toolbarMenuManager = $toolbar_menu;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('toolbar_menu.manager')
    );
  }

  /**
   * Returns an array of toolbar_menu permissions.
   *
   * @return array
   *   The permission list.
   */
  public function permissions() {
    return $this->toolbarMenuManager->getPermissionList();
  }

}
