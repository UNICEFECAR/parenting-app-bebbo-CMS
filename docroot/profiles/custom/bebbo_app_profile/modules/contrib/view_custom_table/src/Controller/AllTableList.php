<?php

namespace Drupal\view_custom_table\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Database\Database;
use Drupal\Core\Config\ImmutableConfig;

/**
 * Defines AllTableList class.
 */
class AllTableList extends ControllerBase {

  /**
   * Drupal\Core\Config\ImmutableConfig definition.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected $config;

  /**
   * Class constructor.
   */
  public function __construct(ImmutableConfig $config) {
    $this->config = $config;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory')->get('view_custom_table.tables')
    );
  }

  /**
   * Display views custom tables.
   *
   * @return array
   *   Return markup array of views custom tables.
   */
  public function content() {
    if ($this->config) {
      $configData = $this->config->getRawData();
      if (!empty($configData)) {
        $all_database_connections = Database::getAllConnectionInfo();
        foreach ($configData as $rowData) {
          $delete_url = Url::fromRoute('view_custom_table.removecustomtable', ['table_name' => $rowData['table_name']]);
          $edit_url = Url::fromRoute('view_custom_table.editcustomtable', ['table_name' => $rowData['table_name']]);
          $edit_relations_url = Url::fromRoute('view_custom_table.edittablerelations', ['table_name' => $rowData['table_name']]);
          $views_url = Url::fromRoute('view_custom_table.customtable_views', ['table_name' => $rowData['table_name']]);

          $links = [
            [
              '#type' => 'dropbutton',
              '#links' => [
                [
                  'title' => $this->t('Edit'),
                  'url' => $edit_url,
                ],
                [
                  'title' => $this->t('Edit Relations'),
                  'url' => $edit_relations_url,
                ],
                [
                  'title' => $this->t('Views'),
                  'url' => $views_url,
                ],
                [
                  'title' => $this->t('Delete'),
                  'url' => $delete_url,
                ],
              ],
            ],
          ];
          $rows[] = [
            'name' => $rowData['table_name'],
            'database' => $all_database_connections[$rowData['table_database']]['default']['database'],
            'description' => $rowData['description'],
            'operations' => render($links),
          ];
        }
        $headers = [
          $this->t('Table Name'),
          $this->t('Database'),
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
    }
    return [
      '#type' => 'markup',
      '#markup' => $this->t('Module not installed properly, please reinstall module again.'),
    ];
  }

}
