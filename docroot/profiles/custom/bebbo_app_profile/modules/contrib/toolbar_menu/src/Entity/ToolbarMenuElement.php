<?php

namespace Drupal\toolbar_menu\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\system\Entity\Menu;

/**
 * Defines the Toolbar element entity.
 *
 * @ConfigEntityType(
 *   id = "toolbar_menu_element",
 *   label = @Translation("Toolbar menu element"),
 *   handlers = {
 *     "list_builder" = "Drupal\toolbar_menu\ToolbarMenuElementListBuilder",
 *     "form" = {
 *       "default" = "Drupal\toolbar_menu\Form\ToolbarMenuElementEditForm",
 *       "delete" = "Drupal\Core\Entity\EntityDeleteForm"
 *     },
 *     "route_provider" = {
 *       "html" = "Drupal\Core\Entity\Routing\DefaultHtmlRouteProvider",
 *     },
 *   },
 *   config_prefix = "toolbar_menu_element",
 *   admin_permission = "administer toolbar menu",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label",
 *     "uuid" = "uuid",
 *     "weight" = "weight",
 *     "menu" = "menu",
 *     "rewrite_label" = "rewrite_label",
 *   },
 *   config_export = {
 *     "id",
 *     "label",
 *     "weight",
 *     "menu",
 *     "rewrite_label",
 *   },
 *   links = {
 *     "collection" = "/admin/config/user-interface/toolbar-menu/elements",
 *     "edit-form" = "/admin/config/user-interface/toolbar-menu/elements/{toolbar_menu_element}",
 *     "delete-form" = "/admin/config/user-interface/toolbar-menu/elements/{toolbar_menu_element}/delete"
 *   }
 * )
 */
class ToolbarMenuElement extends ConfigEntityBase {

  /**
   * {@inheritdoc}
   *
   * Enforce 'toolbar_menu' cache tag invalidation
   * to add this new toolbar_menu entry in the toolbar.
   */
  public function postSave(EntityStorageInterface $storage, $update = TRUE) {
    \Drupal::service('cache_tags.invalidator')->invalidateTags(['toolbar_menu']);
  }

  /**
   * Get the menu 'menu' property.
   *
   * @return mixed|null
   *   The menu property.
   */
  public function menu() {
    return $this->get('menu');
  }

  /**
   * Get the full loaded menu entity.
   *
   * @return \Drupal\system\Entity\Menu|null|static
   *   Loaded menu.
   */
  public function loadMenu() {
    if ($this->menu()) {
      return Menu::Load($this->menu());
    }
    return NULL;
  }

  /**
   * Get the 'rewrite_label' property.
   *
   * @return mixed|null
   *   The rewrite_label property
   */
  public function rewriteLabel() {
    return $this->get('rewrite_label');
  }

  /**
   * Get the 'weight' property.
   *
   * @return mixed|null
   *   The weight property
   */
  public function weight() {
    return $this->get('weight');
  }

  /**
   * Get the displayed label.
   *
   * If rewrite_label property is set to TRUE,
   * the label of this entity was returned.
   *
   * @return mixed|null|string
   *   Display label
   */
  public function getDisplayLabel() {
    if ($this->rewriteLabel() && $this->menu()) {
      return $this->loadMenu()->label();
    }
    return $this->label();
  }

}
