<?php

namespace Drupal\toolbar_menu;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\toolbar_menu\Entity\ToolbarMenuElement;

/**
 * Implement a setting form for toolbar_menu module.
 */
class ToolbarMenuManager {

  use StringTranslationTrait;

  /**
   * The current account.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $account;

  /**
   * The toolbar menu elements entities.
   *
   * @var \Drupal\toolbar_menu\Entity\ToolbarMenuElement[]
   */
  protected $toolbarMenuElements;

  /**
   * Construct a new ToolbarMenu.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity manager.
   * @param \Drupal\Core\Session\AccountProxyInterface $account
   *   The account service.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, AccountProxyInterface $account) {
    $this->account = $account->getAccount();
    $this->toolbarMenuElements = $entity_type_manager->getStorage('toolbar_menu_element')->loadMultiple();
  }

  /**
   * Get toolbar menu elements.
   *
   * @return \Drupal\toolbar_menu\Entity\ToolbarMenuElement[]
   *   An array containing toolbar menu elements.
   */
  public function getToolbarMenuElements() {
    $elements = [];
    foreach ($this->toolbarMenuElements as $key => $element) {
      if ($this->account->hasPermission($this->getPermissionName($element))) {
        $elements[$key] = $element;
      }
    };
    return $elements;
  }

  /**
   * Helper to clean an ID.
   *
   * @param string $id
   *   The ID to clean.
   *
   * @return string
   *   The cleaned ID.
   */
  public function cleanId($id) {
    return preg_replace('/[^\p{L}\p{N}]/u', '-', $id);
  }

  /**
   * Get the permission list.
   *
   * @return array
   *   The permission list.
   */
  public function getPermissionList() {
    $permissions = [];
    foreach ($this->toolbarMenuElements as $element) {
      $permissions[$this->getPermissionName($element)] = [
        'title' => $this->t('View <em>@label</em> element in the toolbar', ['@label' => $element->label()]),
      ];
    }
    return $permissions;
  }

  /**
   * Get the formatted permission name.
   *
   * @param \Drupal\toolbar_menu\Entity\ToolbarMenuElement $element
   *   The name of the menu.
   *
   * @return string
   *   The name of the permission.
   */
  protected function getPermissionName(ToolbarMenuElement $element) {
    return 'view ' . $element->id() . ' in toolbar';
  }

}
