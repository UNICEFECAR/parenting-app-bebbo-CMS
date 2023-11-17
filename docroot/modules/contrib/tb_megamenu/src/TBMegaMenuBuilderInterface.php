<?php

namespace Drupal\tb_megamenu;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Menu\MenuLinkTreeElement;

/**
 * Provides an interface defining a TB MegaMenu builder.
 */
interface TBMegaMenuBuilderInterface {

  /**
   * Get the configuration of blocks.
   *
   * @param string $menu_name
   *   Menu Machine name.
   * @param string $theme
   *   Theme machine name.
   *
   * @return array
   *   The block config array
   */
  public function getBlockConfig(string $menu_name, string $theme): array;

  /**
   * Get menus that belongs TB mega menu.
   *
   * @param string $menu_name
   *   The menu machine name.
   * @param string $theme
   *   The theme machine name.
   *
   * @return \Drupal\tb_megamenu\MegaMenuConfigInterface|null
   *   The configuration entity for this menu or NULL if not found.
   */
  public function getMenus(string $menu_name, string $theme): ?MegaMenuConfigInterface;

  /**
   * Find a menu item.
   *
   * @param string $menu_name
   *   Menu machine name.
   * @param string $plugin_id
   *   The menu item plugin id.
   *
   * @return \Drupal\Core\Menu\MenuLinkTreeElement
   *   The menu item element.
   */
  public function getMenuItem(string $menu_name, string $plugin_id): MenuLinkTreeElement;

  /**
   * Search by menu item.
   *
   * @param array $tree
   *   The menu tree.
   * @param string $plugin_id
   *   The item plugin id.
   *
   * @return \Drupal\Core\Menu\MenuLinkTreeElement|null
   *   The menu link element.
   */
  public function findMenuItem(array $tree, string $plugin_id): MenuLinkTreeElement|null;

  /**
   * Load blocks by block_id.
   *
   * @param string $block_id
   *   The block id.
   *
   * @return \Drupal\Core\Entity\EntityInterface|null
   *   The block entity.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function loadEntityBlock(string $block_id): ?EntityInterface;

  /**
   * Get configuration of menu.
   *
   * @param string $menu_name
   *   The menu machine name.
   * @param string $theme
   *   The theme machine name.
   *
   * @return object|array
   *   The menu configuration info.
   */
  public function getMenuConfig(string $menu_name, string $theme): object|array;

  /**
   * Create the default attributes for the configuration of block.
   *
   * @param array $block_config
   *   The block config array to fill with default values.
   */
  public function editBlockConfig(array &$block_config);

  /**
   * Set the default values to configuration in Sub TB Megamenu if it's empty.
   *
   * @param array $submenu_config
   *   The array to fill with default values.
   * @param int $level
   *   The level value.
   */
  public function editSubMenuConfig(array &$submenu_config, int $level);

  /**
   * Set the default values to configuration in TB Megamenu item if it's empty.
   *
   * @param array $item_config
   *   The array to fill with default values.
   * @param int $level
   *   The level value.
   */
  public function editItemConfig(array &$item_config, int $level);

  /**
   * Set the default values to configuration in columns if it's empty.
   *
   * @param array $col_config
   *   The array to fill with default values.
   */
  public function editColumnConfig(array &$col_config);

  /**
   * Create block which using tb_megamenu.
   *
   * @param string $menu_name
   *   The menu machine name.
   * @param string $theme
   *   The theme machine name.
   *
   * @return array
   *   The render array.
   */
  public function renderBlock(string $menu_name, string $theme): array;

  /**
   * Get Id of column.
   *
   * @param int $number_columns
   *   The number of columns.
   *
   * @return string
   *   The column id.
   */
  public function getIdColumn(int $number_columns): string;

  /**
   * Get all blocks in system without blocks which belong to TB Mega Menu.
   *
   * In array, each element includes key which is plugin_id and value which is
   * label of block.
   *
   * @staticvar array $_blocks_array
   *
   * @return \Drupal\Core\Entity\EntityTypeInterface[]
   *   An array of block entities or an empty array if none found.
   */
  public function getAllBlocks(string $theme): array;

  /**
   * Create options for animation.
   *
   * @param array $block_config
   *   The block configuration.
   *
   * @return array
   *   The default block configuration.
   */
  public function createAnimationOptions(array $block_config): array;

  /**
   * Builds the page trail for marking active items.
   *
   * @param \Drupal\Core\Menu\MenuLinkTreeElement[] $menu_items
   *   The menu items to use.
   */
  public function buildPageTrail(array $menu_items);

  /**
   * Add item config values to menu config array.
   *
   * @param array $menu_items
   *   The menu tree for this config.
   * @param array $menu_config
   *   The menu configuration.
   * @param string $section
   *   The menu section.
   */
  public function syncConfigAll(array $menu_items, array &$menu_config, string $section);

  /**
   * Populate the item_config values.
   *
   * @param array $items
   *   Menu items.
   * @param array $item_config
   *   The item config array to populate.
   * @param string $section
   *   The menu section.
   */
  public function syncConfig(array $items, array &$item_config, string $section);

  /**
   * Add menu item content to a column.
   *
   * @param array $items
   *   All items in the current menu.
   * @param array $item_config
   *   The current configuration for all items.
   */
  public function addColContent(array $items, array &$item_config);

  /**
   * Sync a core menu item with the TB config.
   *
   * @param array $hash
   *   An array of hashes for all menu items based on their positions.
   * @param array $tb_item
   *   The individual menu item.
   * @param int|string $row_delta
   *   The delta for the current row.
   * @param int|string $col_delta
   *   The delta for the current column.
   * @param int|string $item_delta
   *   The delta for the current item.
   * @param array $items
   *   All items in the current menu.
   * @param array $item_config
   *   The current configuration for all items.
   */
  public function syncMenuItem(array &$hash, array $tb_item, int|string $row_delta, int|string $col_delta, int|string $item_delta, array $items, array &$item_config);

  /**
   * Sync a core block with the TB config.
   *
   * @param array $tb_item
   *   The individual menu item.
   * @param int|string $row_delta
   *   The delta for the current row.
   * @param int|string $col_delta
   *   The delta for the current column.
   * @param int|string $item_delta
   *   The delta for the current item.
   * @param string $section
   *   The portion of the configuration to sync.
   * @param array $item_config
   *   The current configuration for all items.
   */
  public function syncBlock(array $tb_item, int|string $row_delta, int|string $col_delta, int|string $item_delta, string $section, array &$item_config);

  /**
   * Remove a column from the TB config.
   *
   * @param array $tb_item
   *   The individual menu item.
   * @param int|string $row_delta
   *   The delta for the current row.
   * @param int|string $col_delta
   *   The delta for the current column.
   * @param int|string $item_delta
   *   The delta for the current item.
   * @param array $item_config
   *   The current configuration for all items.
   */
  public function removeColumn(array $tb_item, int|string $row_delta, int|string $col_delta, int|string $item_delta, array &$item_config);

  /**
   * Insert an enabled link into the TB config.
   *
   * @param array $items
   *   All items in the current menu.
   * @param array $hash
   *   An array of hashes for all menu items based on their positions.
   * @param array $item_config
   *   The current configuration for all items.
   */
  public function insertEnabledLinks(array $items, array $hash, array &$item_config);

  /**
   * Sync order of menu items between menu and tb_megamenus.
   *
   * @param array $menu_config
   *   The menu configuration.
   */
  public function syncOrderMenus(array &$menu_config);

  /**
   * Sort menu items by weight.
   *
   * @param array $item_sorted
   *   The unsorted weights of all menu items.
   *
   * @return array
   *   All weights sorted lowest to highest.
   */
  public function sortByWeight(array $item_sorted): array;

  /**
   * Test if a block has content or not.
   *
   * @param string $block_id
   *   The block id.
   * @param string $section
   *   The menu section.
   *
   * @return bool
   *   True if empty.
   */
  public function isBlockContentEmpty(string $block_id, string $section): bool;

  /**
   * Insert a menu item into the item config array.
   *
   * @param array $item_config
   *   The item config array.
   * @param int|string $row
   *   The row to insert at.
   * @param int|string $col
   *   The column to insert at.
   * @param object $item
   *   The menu item to insert.
   */
  public function insertTbMenuItem(array &$item_config, int|string $row, int|string $col, object $item);

}
