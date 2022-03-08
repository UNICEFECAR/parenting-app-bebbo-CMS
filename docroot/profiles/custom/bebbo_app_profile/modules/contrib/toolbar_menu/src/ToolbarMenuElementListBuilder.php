<?php

namespace Drupal\toolbar_menu;

use Drupal\Core\Config\Entity\DraggableListBuilder;
use Drupal\Core\Entity\EntityInterface;

/**
 * Provides a listing of Toolbar menu element entities.
 */
class ToolbarMenuElementListBuilder extends DraggableListBuilder {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'toolbar_menu_element_list';
  }

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header['label'] = $this->t('Label');
    $header['menu'] = $this->t('Menu');
    $header['rewrite_label'] = $this->t('Use menu label');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    /* @var \Drupal\toolbar_menu\Entity\ToolbarMenuElement $entity */
    $row['label'] = $entity->label();
    $row['menu']['#markup'] = $entity->loadMenu()->label();
    $row['rewrite_label']['#markup'] = $entity->rewriteLabel() ? $this->t('Yes') : $this->t('No');
    return $row + parent::buildRow($entity);
  }

}
