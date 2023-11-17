<?php

namespace Drupal\tb_megamenu;

use Drupal\Core\Config\Entity\ConfigEntityInterface;

/**
 * Provides an interface defining a megamenu config entity.
 */
interface MegaMenuConfigInterface extends ConfigEntityInterface {

  /**
   * Sets the menu property and the first part of the id is it is not set.
   *
   * @param string $menuName
   *   The menu machine name.
   */
  public function setMenu(string $menuName);

  /**
   * Sets the theme property and the second part of the id if it is not set.
   *
   * @param string $themeName
   *   The theme machine name.
   */
  public function setTheme(string $themeName);

  /**
   * Gets the json decoded block configuration.
   *
   * @return object|array
   *   A class with properties for the block configuration settings.
   */
  public function getBlockConfig(): object|array;

  /**
   * Converts the block config  to json and sets the blockConfig property.
   *
   * @param object|array $blockConfig
   *   The block configuration array / stdClass.
   */
  public function setBlockConfig(object|array $blockConfig);

  /**
   * Gets the json decoded menu configuration.
   *
   * @return array
   *   A class with properties for the menu configuration settings.
   */
  public function getMenuConfig(): array;

  /**
   * Converts the menu config properties to json and sets the menu property.
   *
   * @param object|array $menuConfig
   *   The menu configuration array / stdClass.
   */
  public function setMenuConfig(object|array $menuConfig);

  /**
   * Loads the configuration info for the specified menu and theme.
   *
   * @param string $menu
   *   The menu machine name.
   * @param string $theme
   *   The theme machine name.
   *
   * @return MegaMenuConfigInterface|null
   *   Returns the config object or NULL if not found.
   */
  public static function loadMenu(string $menu, string $theme): mixed;

}
