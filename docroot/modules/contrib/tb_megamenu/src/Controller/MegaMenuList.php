<?php

namespace Drupal\tb_megamenu\Controller;

use Drupal\Core\Config\Entity\ConfigEntityListBuilder;
use Drupal\Core\Entity\EntityInterface;
use Drupal\system\Entity\Menu;

/**
 * Provides a listing of MegaMenuConfig entities.
 */
class MegaMenuList extends ConfigEntityListBuilder {

  /**
   * {@inheritdoc}
   */
  public function buildHeader(): array {
    $header['menu'] = $this->t('Menu Name');
    $header['label'] = $this->t('Menu Title');
    $header['theme'] = $this->t('Theme Name');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity): array {
    $row = [];
    if (isset($entity->menu) && isset($entity->theme)) {
      $menu = $entity->menu;
      $theme = $entity->theme;

      /** @var \Drupal\system\Entity\Menu $menu_info */
      $menu_info = Menu::load($menu);

      $row['menu'] = $menu;
      $row['label'] = $menu_info !== NULL ? $menu_info->label() : "MISSING MENU! Was it deleted?";
      $row['theme'] = $theme;
    }
    return $row + parent::buildRow($entity);
  }

}
