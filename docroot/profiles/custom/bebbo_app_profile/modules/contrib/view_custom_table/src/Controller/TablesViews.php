<?php

namespace Drupal\view_custom_table\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines TablesViews class.
 */
class TablesViews extends ControllerBase {

  /**
   * Entity Manager for calss.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityManager;

/**
  * TablesViews class constructor.
  *
  * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityManager
  *   EntityTypeManager.
  */
  public function __construct(EntityTypeManagerInterface $entityManager) {
    $this->entityManager = $entityManager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager')
    );
  }

  /**
   * Display views created by custom tables.
   *
   * @param null $table_name
   *   Table name.
   *
   * @return array
   *   Return markup array of views custom table created by logedin user.
   *   Return markup array of views custom table created by current user.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function content($table_name = NULL) {
    $properties = ['base_table' => $table_name];
    $views = $this->entityManager->getStorage('view')->loadByProperties($properties);
    if (!empty($views)) {
      foreach ($views as $machine_name => $view) {
        $parameter = [
          'view' => $machine_name,
        ];
        $options = [
          'query' => [
            'destination' => 'admin/structure/views/custom_table/views/' . $table_name,
          ],
        ];
        $edit_url = Url::fromRoute('entity.view.edit_form', $parameter, $options);
        $duplicate_url = Url::fromRoute('entity.view.duplicate_form', $parameter, $options);
        $enable_url = Url::fromRoute('entity.view.enable', $parameter, $options);
        $disable_url = Url::fromRoute('entity.view.disable', $parameter, $options);
        $delete_url = Url::fromRoute('entity.view.delete_form', $parameter, $options);
        if (!$view->status()) {
          $links = [
            [
              '#type' => 'dropbutton',
              '#links' => [
                [
                  'title' => $this->t('Enable'),
                  'url' => $enable_url,
                ],
                [
                  'title' => $this->t('Edit'),
                  'url' => $edit_url,
                ],
                [
                  'title' => $this->t('Duplicate'),
                  'url' => $duplicate_url,
                ],
                [
                  'title' => $this->t('Delete'),
                  'url' => $delete_url,
                ],
              ],
            ],
          ];
        }
        else {
          $links = [
            [
              '#type' => 'dropbutton',
              '#links' => [
                [
                  'title' => $this->t('Edit'),
                  'url' => $edit_url,
                ],
                [
                  'title' => $this->t('Duplicate'),
                  'url' => $duplicate_url,
                ],
                [
                  'title' => $this->t('Disable'),
                  'url' => $disable_url,
                ],
                [
                  'title' => $this->t('Delete'),
                  'url' => $delete_url,
                ],
              ],
            ],
          ];
        }

        $rows[] = [
          'name' => $view->label(),
          'machine_name' => $machine_name,
          'description' => $view->get('description'),
          'operations' => render($links),
        ];
      }
      $headers = [
        $this->t('View Name'),
        $this->t('Machine Name'),
        $this->t('Description'),
        $this->t('Operations'),
      ];
      return [
        '#theme' => 'table',
        '#header' => $headers,
        '#rows' => isset($rows) ? $rows : [],
      ];
    }
    else {
      return [
        '#type' => 'markup',
        '#markup' => $this->t('No entry found for views custom tables'),
      ];
    }
    return [
      '#type' => 'markup',
      '#markup' => $this->t('Module not installed properly, please reinstall module again.'),
    ];
  }

}
