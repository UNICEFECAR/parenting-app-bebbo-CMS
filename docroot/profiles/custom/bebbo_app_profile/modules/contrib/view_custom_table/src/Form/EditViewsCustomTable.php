<?php

namespace Drupal\view_custom_table\Form;

use Drupal\Component\Utility\UrlHelper;
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
class EditViewsCustomTable extends FormBase {

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
   * Class constructor.
   */
  public function __construct(ImmutableConfig $config, Config $configEditable) {
    $this->config = $config;
    $this->configEditable = $configEditable;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory')->get('view_custom_table.tables'),
      $container->get('config.factory')->getEditable('view_custom_table.tables')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'view_custom_table_edit_table_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $table_name = NULL) {
    $config = $this->config->getRawData();
    $all_database_connections = Database::getAllConnectionInfo();
    foreach ($all_database_connections as $connection_name => $connection) {
      $displyName = $connection['default']['database'];
      $databaseOptions[$connection_name] = $displyName;
    }
    $form['table_database'] = [
      '#type' => 'select',
      '#options' => $databaseOptions,
      '#title' => $this->t('Database'),
      '#default_value' => $config[$table_name]['table_database'],
      '#disabled' => TRUE,
      '#description' => $this->t('Database of the table cannot be changed.'),
    ];
    $form['table_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Table'),
      '#default_value' => $table_name,
      '#disabled' => TRUE,
      '#required' => TRUE,
      '#description' => $this->t('Table name cannot be changed.'),
    ];
    $form['description'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Description'),
      '#default_value' => ($config[$table_name]['description'] != NULL) ? $config[$table_name]['description'] : '',
      '#rows' => 5,
      '#description' => $this->t('Maximum 255 letters are allowed.'),
    ];

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
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $description = $form_state->getValue('description');
    if (strlen($description) > 254) {
      $form_state->setErrorByName('description', $this->t("Description can not be more then 255 letters. Please update it and try again."));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $table_name = $form_state->getValue('table_name');
    $description = $form_state->getValue('description');
    $table_database = $form_state->getValue('table_database');
    $this->configEditable->set($table_name . '.table_name', $table_name)
      ->set($table_name . '.table_database', $table_database)
      ->set($table_name . '.description', $description);
    $result = $this->configEditable->save();
    if ($result) {
      $this->messenger()->addStatus($this->t('@table is updated.', [
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
