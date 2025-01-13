<?php

namespace Drupal\structure_sync\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Menu\MenuLinkTreeInterface;
use Drupal\Core\Menu\MenuTreeParameters;
use Drupal\menu_link_content\Entity\MenuLinkContent;
use Drupal\structure_sync\StructureSyncHelper;
use Drush\Drush;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Controller for syncing menu links.
 */
class MenuLinksController extends ControllerBase {

  /**
   * An editable structure_sync.data configuration object.
   *
   * @var \Drupal\Core\Config\Config
   */
  private $config;

  /**
   * The menu tree service.
   *
   * @var \Drupal\Core\Menu\MenuLinkTreeInterface
   */
  protected $menuTree;

  /**
   * Constructor for menu links controller.
   *
   * @param \Drupal\Core\Menu\MenuLinkTreeInterface $menuTree
   *   The menu tree service.
   */
  public function __construct(MenuLinkTreeInterface $menuTree) {
    $this->config = $this->getEditableConfig();
    $this->entityTypeManager();
    $this->menuTree = $menuTree;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new self(
      $container->get('menu.link_tree'),
    );
  }

  /**
   * Gets the editable version of the config.
   */
  private function getEditableConfig() {
    $this->config('structure_sync.data');

    return $this->configFactory->getEditable('structure_sync.data');
  }

  /**
   * Function to export menu links.
   */
  public function exportMenuLinks(array $form = NULL, FormStateInterface $form_state = NULL) {
    StructureSyncHelper::logMessage('Menu links export started');

    if (is_object($form_state) && $form_state->hasValue('export_menu_list')) {
      $menuList = $form_state->getValue('export_menu_list');
      $menuList = array_filter($menuList, 'is_string');
    }

    $this->config->clear('menus')->save();

    if (isset($menuList)) {
      $menuLinks = [];

      foreach ($menuList as $menuName) {
        // Retrieve all menu links.
        $unsortedMenuLinks = $this->entityTypeManager
          ->getStorage('menu_link_content')
          ->loadByProperties(['menu_name' => $menuName]);
        // Sort them by their position in the menu tree.
        $menuLinks = array_merge($this->sortMenuLinks($unsortedMenuLinks, $menuName), $menuLinks);
      }
    }
    else {
      $menuLinks = $this->entityTypeManager()->getStorage('menu_link_content')
        ->loadMultiple();
    }

    $customMenuLinks = [];
    foreach ($menuLinks as $menuLink) {
      $customMenuLinks[] = [
        'menu_name' => $menuLink->menu_name->value,
        'title' => $menuLink->title->value,
        'parent' => $menuLink->parent->value,
        'uri' => $menuLink->link->uri,
        'link_title' => $menuLink->link->title,
        'description' => $menuLink->description->value,
        'enabled' => $menuLink->enabled->value,
        'expanded' => $menuLink->expanded->value,
        'weight' => $menuLink->weight->value,
        'langcode' => $menuLink->langcode->value,
        'uuid' => $menuLink->uuid(),
        'options' => $menuLink->link->options,
      ];

      StructureSyncHelper::logMessage('Exported "' . $menuLink->title->value . '" of menu "' . $menuLink->menu_name->value . '"');
    }

    $this->config->set('menus', $customMenuLinks)->save();

    $this->messenger()->addStatus($this->t('The menu links have been successfully exported.'));
    StructureSyncHelper::logMessage('Menu links exported');
  }

  /**
   * Function to sort menu links by weight.
   *
   * When retrieving menu links using EntityTypeManager, they will be in order
   * of creation (sorted by id). However if menu links have been rearranged,
   * you can have a smaller id as a child of a greater id.
   * Sorting the menu links array will ensure that descendent links are created
   * after their parents.
   */
  public function sortMenuLinks(array $unsortedMenuLinks, $menuName) {
    // Retrieve the full menu tree of $menuName.
    $tree = $this->menuTree->load($menuName, new MenuTreeParameters());
    // Flatten it to have an array to use as sorting reference.
    $flatMenuTree = $this->menuTreeFlatten($tree);

    $menuLinksSorted = [];
    // Use $flatMenuTree as the order reference to sort menu links.
    foreach ($flatMenuTree as $uuid) {
      foreach ($unsortedMenuLinks as $menuLink) {
        if ($menuLink->uuid() === $uuid) {
          $menuLinksSorted[] = $menuLink;
        }
      }
    }

    return $menuLinksSorted;
  }

  /**
   * Helper function to recursively get all UUID of menu links from a menu.
   */
  public function menuTreeFlatten(array $tree) {
    $flatTree = [];
    foreach ($tree as $menuLinkContentId => $menuLinkTree) {
      $flatTree[] = str_replace('menu_link_content:', '', $menuLinkContentId);
      if ($menuLinkTree->hasChildren) {
        $flatTree = array_merge($flatTree, $this->menuTreeFlatten($menuLinkTree->subtree));
      }
    }

    return $flatTree;
  }

  /**
   * Function to import menu links.
   *
   * When this function is used without the designated form, you should assign
   * an array with a key value pair for form with key 'style' and value 'full',
   * 'safe' or 'force' to apply that import style.
   */
  public function importMenuLinks(array $form, FormStateInterface $form_state = NULL) {
    StructureSyncHelper::logMessage('Menu links import started');

    // Check if the there is a selection made in a form for what menus need to
    // be imported.
    if (is_object($form_state) && $form_state->hasValue('import_menu_list')) {
      $menusSelected = $form_state->getValue('import_menu_list');
      $menusSelected = array_filter($menusSelected, 'is_string');
    }
    if (array_key_exists('style', $form)) {
      $style = $form['style'];
    }
    else {
      StructureSyncHelper::logMessage('No style defined on menu links import', 'error');
      return;
    }

    StructureSyncHelper::logMessage('Using "' . $style . '" style for menu links import');

    // Get menu links from config.
    $menusConfig = $this->config->get('menus');

    // Importing with no config should print out a log message.
    if (empty($menusConfig)) {
      $message = $this->t("No menu exported: Nothing to import.\nMenus need to be exported first before they can be imported.");
      StructureSyncHelper::logMessage($message);
      return;
    }

    // Drush import: Process all menu items stored in config.
    if (array_key_exists('drush', $form) && $form['drush'] === TRUE) {
      $context = [];
      $context['drush'] = TRUE;

      switch ($style) {
        case 'full':
          self::deleteDeletedMenuLinks($menusConfig, $context);
          self::importMenuLinksFull($menusConfig, $context);
          self::menuLinksImportFinishedCallback(NULL, NULL, NULL);
          break;

        case 'safe':
          self::importMenuLinksSafe($menusConfig, $context);
          self::menuLinksImportFinishedCallback(NULL, NULL, NULL);
          break;

        case 'force':
          self::deleteMenuLinks($context);
          self::importMenuLinksForce($menusConfig, $context);
          self::menuLinksImportFinishedCallback(NULL, NULL, NULL);
          break;
      }

      return;
    }

    // Form import: Check if any menu was selected for import.
    if (empty($menusSelected)) {
      $message = $this->t('No menu selected for import: Nothing to import.');
      StructureSyncHelper::logMessage($message);
      $this->messenger()->addWarning($message);
      return;
    }
    // Filter menus saved in config with the ones selected for the import.
    $menus = array_filter($menusConfig, function ($menu_link_item) use ($menusSelected) {
      return in_array($menu_link_item['menu_name'], $menusSelected);
    });

    // Import the selected menu links with the chosen style of import.
    switch ($style) {
      case 'full':
        $batch = [
          'title' => $this->t('Importing menu links...'),
          'operations' => [
            [
              '\Drupal\structure_sync\Controller\MenuLinksController::deleteDeletedMenuLinks',
              [$menus],
            ],
            [
              '\Drupal\structure_sync\Controller\MenuLinksController::importMenuLinksFull',
              [$menus],
            ],
          ],
          'finished' => '\Drupal\structure_sync\Controller\MenuLinksController::menuLinksImportFinishedCallback',
        ];
        batch_set($batch);
        break;

      case 'safe':
        $batch = [
          'title' => $this->t('Importing menu links...'),
          'operations' => [
            [
              '\Drupal\structure_sync\Controller\MenuLinksController::importMenuLinksSafe',
              [$menus],
            ],
          ],
          'finished' => '\Drupal\structure_sync\Controller\MenuLinksController::menuLinksImportFinishedCallback',
        ];
        batch_set($batch);
        break;

      case 'force':
        $batch = [
          'title' => $this->t('Importing menu links...'),
          'operations' => [
            [
              '\Drupal\structure_sync\Controller\MenuLinksController::deleteMenuLinks',
              [],
            ],
            [
              '\Drupal\structure_sync\Controller\MenuLinksController::importMenuLinksForce',
              [$menus],
            ],
          ],
          'finished' => '\Drupal\structure_sync\Controller\MenuLinksController::menuLinksImportFinishedCallback',
        ];
        batch_set($batch);
        break;

      default:
        StructureSyncHelper::logMessage('Style not recognized', 'error');
        break;
    }
  }

  /**
   * Function to delete the menu links that should be removed in this import.
   */
  public static function deleteDeletedMenuLinks($menus, &$context) {
    $uuidsInConfig = [];
    foreach ($menus as $menuLink) {
      $uuidsInConfig[] = $menuLink['uuid'];
    }

    if (!empty($uuidsInConfig)) {
      $query = StructureSyncHelper::getEntityQuery('menu_link_content');
      $query->condition('uuid', $uuidsInConfig, 'NOT IN');
      $ids = $query->execute();
      $controller = StructureSyncHelper::getEntityManager()
        ->getStorage('menu_link_content');
      $entities = $controller->loadMultiple($ids);
      $controller->delete($entities);
    }

    if (array_key_exists('drush', $context) && $context['drush'] === TRUE) {
      Drush::logger()->notice('Deleted menu links that were not in config');
    }
    StructureSyncHelper::logMessage('Deleted menu links that were not in config');
  }

  /**
   * Function to fully import the menu links.
   *
   * Basically a safe import with update actions for already existing menu
   * links.
   */
  public static function importMenuLinksFull($menus, &$context) {
    $uuidsInConfig = [];
    foreach ($menus as $menuLink) {
      $uuidsInConfig[] = $menuLink['uuid'];
    }
    $entities = [];
    if (!empty($uuidsInConfig)) {
      $query = StructureSyncHelper::getEntityQuery('menu_link_content');
      $query->condition('uuid', $uuidsInConfig, 'IN');
      $ids = $query->execute();
      $controller = StructureSyncHelper::getEntityManager()
        ->getStorage('menu_link_content');
      $entities = $controller->loadMultiple($ids);
    }

    $parents = array_column($menus, 'parent');
    foreach ($parents as &$parent) {
      if (!is_null($parent)) {
        if (($pos = strpos($parent, ":")) !== FALSE) {
          $parent = substr($parent, $pos + 1);
        }
        else {
          $parent = NULL;
        }
      }
    }

    $idsDone = [];
    $idsLeft = [];
    $firstRun = TRUE;
    $context['sandbox']['max'] = count($menus);
    $context['sandbox']['progress'] = 0;
    while ($firstRun || count($idsLeft) > 0) {
      foreach ($menus as $menuLink) {
        $query = StructureSyncHelper::getEntityQuery('menu_link_content');
        $query->condition('uuid', $menuLink['uuid']);
        $ids = $query->execute();

        $currentParent = $menuLink['parent'];
        if (!is_null($currentParent)) {
          if (($pos = strpos($currentParent, ":")) !== FALSE) {
            $currentParent = substr($currentParent, $pos + 1);
          }
        }

        if (!in_array($menuLink['uuid'], $idsDone)
          && ($menuLink['parent'] === NULL
            || !in_array($menuLink['parent'], $parents)
            || in_array($currentParent, $idsDone))
        ) {
          if (count($ids) <= 0) {
            MenuLinkContent::create([
              'title' => $menuLink['title'],
              'link' => [
                'uri' => $menuLink['uri'],
                'title' => $menuLink['link_title'],
                'options' => $menuLink['options'],
              ],
              'menu_name' => $menuLink['menu_name'],
              'expanded' => in_array($menuLink['expanded'], ['1', TRUE], TRUE),
              'enabled' => in_array($menuLink['enabled'], ['1', TRUE], TRUE),
              'parent' => $menuLink['parent'],
              'description' => $menuLink['description'],
              'weight' => $menuLink['weight'],
              'langcode' => $menuLink['langcode'],
              'uuid' => $menuLink['uuid'],
            ])->save();
          }
          else {
            foreach ($entities as $entity) {
              if ($menuLink['uuid'] === $entity->uuid()) {
                $customMenuLink = MenuLinkContent::load($entity->id());
                if (!empty($customMenuLink)) {
                  $customMenuLink
                    ->set('title', $menuLink['title'])
                    ->set('link', [
                      'uri' => $menuLink['uri'],
                      'title' => $menuLink['link_title'],
                      'options' => $menuLink['options'],
                    ])
                    ->set('expanded', in_array($menuLink['expanded'], ['1', TRUE], TRUE))
                    ->set('enabled', in_array($menuLink['enabled'], ['1', TRUE], TRUE))
                    ->set('parent', $menuLink['parent'])
                    ->set('description', $menuLink['description'])
                    ->set('weight', $menuLink['weight'])
                    ->save();
                }

                break;
              }
            }
          }

          $idsDone[] = $menuLink['uuid'];

          if (in_array($menuLink['uuid'], $idsLeft)) {
            unset($idsLeft[$menuLink['uuid']]);
          }

          StructureSyncHelper::logMessage('Imported "' . $menuLink['title'] . '" into ' . $menuLink['menu_name']);

          $context['sandbox']['progress']++;
          if ($context['sandbox']['progress'] != $context['sandbox']['max']) {
            $context['finished'] = $context['sandbox']['progress'] / $context['sandbox']['max'];
          }
        }
        else {
          $idsLeft[$menuLink['uuid']] = $menuLink['uuid'];
        }
      }

      $firstRun = FALSE;
    }

    $context['finished'] = 1;
  }

  /**
   * Function to import menu links safe (only adding what isn't already there).
   */
  public static function importMenuLinksSafe($menus, &$context) {
    $menusFiltered = $menus;

    $entities = StructureSyncHelper::getEntityManager()
      ->getStorage('menu_link_content')
      ->loadMultiple();

    foreach ($entities as $entity) {
      for ($i = 0; $i < count($menus); $i++) {
        if ($entity->uuid() === $menus[$i]['uuid']) {
          unset($menusFiltered[$i]);
        }
      }
    }

    foreach ($menusFiltered as $menuLink) {
      MenuLinkContent::create([
        'title' => $menuLink['title'],
        'link' => [
          'uri' => $menuLink['uri'],
          'title' => $menuLink['link_title'],
          'options' => $menuLink['options'],
        ],
        'menu_name' => $menuLink['menu_name'],
        'expanded' => in_array($menuLink['expanded'], ['1', TRUE], TRUE),
        'enabled' => in_array($menuLink['enabled'], ['1', TRUE], TRUE),
        'parent' => $menuLink['parent'],
        'description' => $menuLink['description'],
        'weight' => $menuLink['weight'],
        'langcode' => $menuLink['langcode'],
        'uuid' => $menuLink['uuid'],
      ])->save();

      StructureSyncHelper::logMessage('Imported "' . $menuLink['title'] . '" into "' . $menuLink['menu_name'] . '" menu');
    }
  }

  /**
   * Function to delete all menu links.
   */
  public static function deleteMenuLinks(&$context) {
    $entities = StructureSyncHelper::getEntityManager()
      ->getStorage('menu_link_content')
      ->loadMultiple();
    StructureSyncHelper::getEntityManager()
      ->getStorage('menu_link_content')
      ->delete($entities);

    if (array_key_exists('drush', $context) && $context['drush'] === TRUE) {
      Drush::logger()->notice('Deleted all (content) menu links');
    }
    StructureSyncHelper::logMessage('Deleted all (content) menu links');
  }

  /**
   * Function to import (create) all menu links that need to be imported.
   */
  public static function importMenuLinksForce($menus, &$context) {
    foreach ($menus as $menuLink) {
      MenuLinkContent::create([
        'title' => $menuLink['title'],
        'link' => [
          'uri' => $menuLink['uri'],
          'title' => $menuLink['link_title'],
          'options' => $menuLink['options'],
        ],
        'menu_name' => $menuLink['menu_name'],
        'expanded' => in_array($menuLink['expanded'], ['1', TRUE], TRUE),
        'enabled' => in_array($menuLink['enabled'], ['1', TRUE], TRUE),
        'parent' => $menuLink['parent'],
        'description' => $menuLink['description'],
        'weight' => $menuLink['weight'],
        'langcode' => $menuLink['langcode'],
        'uuid' => $menuLink['uuid'],
      ])->save();

      StructureSyncHelper::logMessage('Imported "' . $menuLink['title'] . '" into "' . $menuLink['menu_name'] . '" menu');
    }
  }

  /**
   * Function that signals that the import of menu links has finished.
   */
  public static function menuLinksImportFinishedCallback($success, $results, $operations) {
    StructureSyncHelper::logMessage('Flushing all caches');

    drupal_flush_all_caches();

    StructureSyncHelper::logMessage('Successfully flushed caches');

    StructureSyncHelper::logMessage('Successfully imported menu links');

    \Drupal::messenger()->addStatus(t('Successfully imported menu links'));
  }

}
