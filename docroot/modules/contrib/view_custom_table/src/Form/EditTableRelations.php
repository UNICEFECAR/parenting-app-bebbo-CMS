<?php

namespace Drupal\view_custom_table\Form;

use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Database\Database;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Config\Config;

/**
 * Edit views custom table form.
 */
class EditTableRelations extends FormBase {

  /**
   * Entity Manager for calss.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityManager;

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
   * EditTableRelations constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityManager
   *   The entity type manager service.
   * @param \Drupal\Core\Config\ImmutableConfig $config
   *   The config service.
   * @param \Drupal\Core\Config\Config $configEditable
   *   The editable config service.
   */
  public function __construct(EntityTypeManagerInterface $entityManager, ImmutableConfig $config, Config $configEditable) {
    $this->entityManager = $entityManager;
    $this->config = $config;
    $this->configEditable = $configEditable;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('config.factory')->get('view_custom_table.tables'),
      $container->get('config.factory')->getEditable('view_custom_table.tables')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'view_custom_table_edit_table_relations_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $table_name = NULL) {
    $config = $this->config->getRawData();
    $table_relations = unserialize($config[$table_name]['column_relations'], ['allowed_classes' => FALSE]);
    $properties = ['base_table' => $table_name];
    $views = $this->entityManager->getStorage('view')->loadByProperties($properties);
    $form['table_name'] = [
      '#type' => 'hidden',
      '#value' => $table_name,
    ];
    $form['columns'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('"@table" Int Type Columns', [
        '@table' => $table_name,
      ]),
      '#tree' => TRUE,
    ];
    $entities['none'] = $this->t('None');
    if ($config[$table_name]['table_database'] == 'default') {
      $all_entities = $this->entityManager->getDefinitions();
      foreach ($all_entities as $entity_name => $entity) {
        if ($entity->getBaseTable()) {
          $entities[$entity_name] = $entity->getLabel()->render();
        }
      }
    }
    if (!empty($config)) {
      foreach ($config as $table => $table_information) {
        $entities[$table] = $table;
      }
    }
    $int_types = [
      'tinyint',
      'smallint',
      'mediumint',
      'int',
      'int unsigned',
      'bigint'
    ];
    $connection = Database::getConnection('default', $config[$table_name]['table_database']);
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
        if (!empty($views) && isset($table_relations[$row->Field])) {
          $form['columns']['column_' . $row->Field]['entity']['#disabled'] = TRUE;
        }
        if (isset($table_relations[$row->Field])) {
          $form['columns']['column_' . $row->Field]['entity']['#default_value'] = $table_relations[$row->Field];
          $form['columns']['column_' . $row->Field]['entity']['#description'] = $this->t('"@table_name" is used in views, that is why this field con not be updated.', [
            '@table_name' => $table_name,
          ]);
        }
        $form['columns']['column_' . $row->Field]['field'] = [
          '#type' => 'hidden',
          '#value' => $row->Field,
        ];
      }
    }
    $form['actions'] = [
      '#type' => 'actions',
    ];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save'),
    ];
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
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $table_name = $form_state->getValue('table_name');
    $relations = $form_state->getValue('columns');
    $column_relations = [];
    foreach ($relations as $relation) {
      if ($relation['entity'] != 'none') {
        $column_relations[$relation['field']] = $relation['entity'];
      }
    }
    $serialize_relations = serialize($column_relations);
    $this->configEditable->set($table_name . '.column_relations', $serialize_relations);
    $result = $this->configEditable->save();
    if ($result) {
      $this->messenger()->addStatus($this->t('@table relations are updated.', [
        '@table' => $table_name,
      ]));
    }
    else {
      $this->messenger()->addError($this->t('Could not update @table data, please check log messages for error.', [
        '@table' => $table_name,
      ]));
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
