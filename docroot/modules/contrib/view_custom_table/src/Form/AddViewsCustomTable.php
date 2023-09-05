<?php

namespace Drupal\view_custom_table\Form;

use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Database\Database;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Config\Config;
use Drupal\Core\Cache\CacheBackendInterface;

/**
 * Add views custom table form.
 */
class AddViewsCustomTable extends FormBase {
  /**
   * Step of the form.
   *
   * @var int
   */
  protected $step;

  /**
   * Previous step form data.
   *
   * @var array
   */
  protected $previousStepData;

  /**
   * Entity Manager for calss.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityManager;

  /**
   * Drupal\Core\Session\AccountProxy definition.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $account;

  /**
   * Drupal\Core\Config\ImmutableConfig definition.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected $config;

  /**
   * Drupal\Core\Config\Config definition.
   *
   * @var \Drupal\Core\Config\Config
   */
  protected $configEditable;

  /**
   * Drupal\Core\Cache\CacheBackendInterface definition.
   *
   * @var \Drupal\Core\Cache\CacheBackendInterface
   */
  protected $cacheDefault;

  /**
   * Drupal\Core\Cache\CacheBackendInterface definition.
   *
   * @var \Drupal\Core\Cache\CacheBackendInterface
   */
  protected $cacheDiscovery;

  /**
   * AddViewsCustomTable constructor.
   *
   * @param \Drupal\Core\Cache\CacheBackendInterface $cacheDiscovery
   *   The cache backend to be used.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cacheDefault
   *   The cache backend to be used.
   * @param \Drupal\Core\Session\AccountProxyInterface $account
   *   The user account.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityManager
   *   The entity type manager service.
   * @param \Drupal\Core\Config\ImmutableConfig $config
   *   The config service.
   * @param \Drupal\Core\Config\Config $configEditable
   *   The editable config service.
   */
  public function __construct(CacheBackendInterface $cacheDiscovery,
  CacheBackendInterface $cacheDefault,
  AccountProxyInterface $account,
  EntityTypeManagerInterface $entityManager,
  ImmutableConfig $config,
  Config $configEditable) {
    $this->cacheDiscovery = $cacheDiscovery;
    $this->cacheDefault = $cacheDefault;
    $this->step = 1;
    $this->previousStepData = [];
    $this->account = $account;
    $this->entityManager = $entityManager;
    $this->config = $config;
    $this->configEditable = $configEditable;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('cache.discovery'),
      $container->get('cache.default'),
      $container->get('current_user'),
      $container->get('entity_type.manager'),
      $container->get('config.factory')->get('view_custom_table.tables'),
      $container->get('config.factory')->getEditable('view_custom_table.tables')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'view_custom_table_add_table_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    if ($this->step == 1) {
      $all_database_connections = Database::getAllConnectionInfo();
      foreach ($all_database_connections as $connection_name => $connection) {
        $displyName = $connection['default']['database'];
        $databaseOptions[$connection_name] = $displyName;
      }
      $form['table_database'] = [
        '#type' => 'select',
        '#options' => $databaseOptions,
        '#title' => $this->t('Database'),
      ];
      $form['table_name'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Table'),
        '#required' => TRUE,
      ];
      $form['description'] = [
        '#type' => 'textarea',
        '#title' => $this->t('Description'),
        '#rows' => 5,
        '#description' => $this->t('Maximum 255 letters are allowed.'),
      ];

      $form['actions'] = [
        '#type' => 'actions',
      ];
      $form['actions']['next'] = [
        '#type' => 'submit',
        '#value' => $this->t('Next'),
      ];
    }
    elseif ($this->step == 2) {
      $table_name = $this->previousStepData['table_name'];
      $databaseName = $this->previousStepData['table_database'];
      $form['columns'] = [
        '#type' => 'fieldset',
        '#title' => $this->t('"@table" Int Type Columns', [
          '@table' => $table_name,
        ]),
        '#tree' => TRUE,
      ];
      $entities['none'] = $this->t('None');
      if ($databaseName == 'default') {
        $all_entities = $this->entityManager->getDefinitions();
        foreach ($all_entities as $entity_name => $entity) {
          if ($entity->getBaseTable()) {
            $entities[$entity_name] = $entity->getLabel()->render();
          }
        }
      }
      $all_custom_tables = $this->config->getRawData();
      if (!empty($all_custom_tables)) {
        foreach ($all_custom_tables as $custom_table) {
          if (($custom_table['table_database'] == $databaseName) && $custom_table['table_name'] != $table_name) {
            $entities[$custom_table['table_name']] = $custom_table['table_name'];
          }
        }
      }
      $int_types = [
        'tinyint',
        'smallint',
        'mediumint',
        'int',
        'bigint',
        'varchar',
      ];
      $connection = Database::getConnection('default', $databaseName);
      $text_query = 'DESCRIBE ' . $connection->tablePrefix($table_name) . $connection->escapeTable($table_name);
      $query = $connection->query($text_query);
      foreach ($query as $row) {
        $row_type = explode('(', $row->Type);
        if (in_array($row_type[0], $int_types)) {
          $form['columns']['column_' . $row->Field] = [
            '#type' => 'fieldset',
            '#title' => $this->t('Relation of "@field_name" with', [
              '@field_name' => ucfirst($row->Field),
            ]),
            '#tree' => TRUE,
            '#attributes' => [
              'class' => [
                'container-inline',
              ],
            ],
          ];
          $form['columns']['column_' . $row->Field]['entity'] = [
            '#type' => 'select',
            '#title' => $this->t('Entity'),
            '#options' => $entities,
          ];
          $form['columns']['column_' . $row->Field]['field'] = [
            '#type' => 'hidden',
            '#value' => $row->Field,
          ];
        }
      }
      $form['actions']['submit'] = [
        '#type' => 'submit',
        '#value' => $this->t('Save'),
      ];
    }
    $form['actions']['cancel'] = [
      '#type' => 'link',
      '#title' => $this->t('Cancel'),
      '#url' => $this->buildCancelLinkUrl(),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    if ($this->step == 1) {
      $databaseName = $form_state->getValue('table_database');
      $table_name = $form_state->getValue('table_name');
      $description = $form_state->getValue('description');
      $connection = Database::getConnection('default', $databaseName);
      if (!$connection->schema()->tableExists($table_name)) {
        $form_state->setErrorByName('table_name', $this->t('@table not found in database @database_name, please check table name, and database again.', [
          '@table' => $table_name,
          '@database_name' => $databaseName,
        ]));
      }
      $config = $this->config->getRawData();
      if (isset($config[$table_name]) && $config[$table_name]['table_database'] == $databaseName) {
        $form_state->setErrorByName('table_name', $this->t("@table is already available for views. If you can't find it, please clear cache and try again.", [
          '@table' => $table_name,
        ]));
      }
      if (strlen($description) > 254) {
        $form_state->setErrorByName('description', $this->t("Description can not be more then 255 letters. Please update it and try again.", [
          '@table' => $table_name,
        ]));
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $opeation = $form_state->getValue('op')->render();
    if ($opeation == $this->t('Next')) {
      $this->previousStepData = $form_state->cleanValues()->getValues();
      $form_state->setRebuild();
      $this->step++;
    }
    if ($opeation == $this->t('Save')) {
      $user = $this->account;
      $table_name = $this->previousStepData['table_name'];
      $table_database = $this->previousStepData['table_database'];
      $description = $this->previousStepData['description'];
      $relations = $form_state->getValue('columns');
      $column_relations = [];
      foreach ($relations as $relation) {
        if ($relation['entity'] != 'none') {
          $column_relations[$relation['field']] = $relation['entity'];
        }
      }
      $serialize_relations = serialize($column_relations);

      $this->configEditable->set($table_name . '.table_name', $table_name)
        ->set($table_name . '.table_database', $table_database)
        ->set($table_name . '.description', $description)
        ->set($table_name . '.column_relations', $serialize_relations)
        ->set($table_name . '.created_by', $user->id());
      $result = $this->configEditable->save();
      if ($result) {
        $this->messenger()->addStatus($this->t('@table is added to views. Please clear cache to see changes.', [
          '@table' => $table_name,
        ]));
        /* \Drupal::service('cache.discovery')->invalidateAll();
        \Drupal::service('cache.default')->invalidateAll(); */
        $this->cacheDiscovery->invalidateAll();
        $this->cacheDefault->invalidateAll();
      }
      else {
        $this->messenger()->addError($this->t('Could not add table to views, please check log messages for error.'));
      }
    }
    $form_state->setRedirect('view_custom_table.customtable');
  }

  /**
   * Builds the cancel link url for the form.
   *
   * @return Drupal\Core\Url
   *   Cancel url
   */
  private function buildCancelLinkUrl() {
    $query = $this->getRequest()->query;

    if ($query->has('destination')) {
      $options = UrlHelper::parse($query->get('destination'));
      $url = Url::fromUri('internal:/' . $options['path'], $options);
    }
    else {
      $url = Url::fromRoute('view_custom_table.customtable');
    }

    return $url;
  }

}
