<?php

namespace Drupal\symfony_mailer\Processor;

use Drupal\Core\Config\ConfigManagerInterface;
use Drupal\Core\Config\Entity\ConfigEntityStorage;
use Drupal\Core\Config\ExtensionInstallStorage;
use Drupal\Core\Config\StorageInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Provides the Mailer override manager.
 */
class OverrideManager implements OverrideManagerInterface {

  use StringTranslationTrait;

  /**
   * The email builder manager.
   *
   * @var \Drupal\symfony_mailer\Processor\EmailBuilderManagerInterface
   */
  protected $builderManager;

  /**
   * The configuration manager.
   *
   * @var \Drupal\Core\Config\ConfigManagerInterface
   */
  protected $configManager;

  /**
   * The config storage.
   *
   * @var \Drupal\Core\Config\StorageInterface
   */
  protected $configStorage;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The override config storage.
   *
   * @var \Drupal\Core\Config\ExtensionInstallStorage
   */
  protected $overrideStorage;

  /**
   * Mapping from override state code to human-readable state string.
   *
   * @var string[]
   */
  protected $stateName;

  /**
   * Array of action names.
   *
   * This a 2-dimensional array indexed by override state code and action code.
   *
   * @var string[][]
   */
  protected $actionName;

  /**
   * Mapping from action code to human-readable warning string.
   *
   * @var string[]
   */
  protected $actionWarning;

  /**
   * The config prefix for the MailerPolicy entity type.
   *
   * @var string
   */
  protected $policyConfigPrefix;

  /**
   * Constructs the OverrideManager object.
   *
   * @param \Drupal\symfony_mailer\Processor\EmailBuilderManagerInterface $email_builder_manager
   *   The email builder manager.
   * @param \Drupal\Core\Config\ConfigManagerInterface $config_manager
   *   The configuration manager.
   * @param \Drupal\Core\Config\StorageInterface $config_storage
   *   The config storage.
   */
  public function __construct(EmailBuilderManagerInterface $email_builder_manager, ConfigManagerInterface $config_manager, StorageInterface $config_storage) {
    $this->builderManager = $email_builder_manager;
    $this->configManager = $config_manager;
    $this->configStorage = $config_storage;
    $this->entityTypeManager = $config_manager->getEntityTypeManager();
    $this->configFactory = $config_manager->getConfigFactory();
    $this->overrideStorage = new ExtensionInstallStorage($this->configStorage, 'config/mailer_override', StorageInterface::DEFAULT_COLLECTION, FALSE, '');

    $this->stateName = [
      self::STATE_DISABLED => $this->t('Disabled'),
      self::STATE_ENABLED => $this->t('Enabled'),
      self::STATE_IMPORTED => $this->t('Enabled & imported'),
    ];
    $this->actionName = [
      self::STATE_DISABLED => [
        'import' => $this->t('Enable & import'),
        'enable' => $this->t('Enable'),
        'disable' => $this->t('Delete'),
      ],
      self::STATE_ENABLED => [
        'import' => $this->t('Import'),
        'disable' => $this->t('Disable'),
        'enable' => $this->t('Reset'),
      ],
      self::STATE_IMPORTED => [
        'disable' => $this->t('Disable'),
        'enable' => $this->t('Reset'),
        'import' => $this->t('Re-import'),
      ],
      self::ALL_OVERRIDES => [
        'import' => $this->t('Enable & import'),
        'enable' => $this->t('Enable'),
        'disable' => $this->t('Disable'),
      ],
    ];
    $this->actionWarning = [
      'disable' => $this->t('Related Mailer Policy will be deleted.'),
      'enable' => $this->t('Related Mailer Policy will be reset to default values.'),
      'import' => $this->t('Importing overwrites existing policy.'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function isEnabled(string $id) {
    $state = $this->configFactory->get('symfony_mailer.settings')->get("override.$id") ?: self::STATE_DISABLED;
    return $state != self::STATE_DISABLED;
  }

  /**
   * {@inheritdoc}
   */
  public function getInfo(string $filterId = NULL) {
    if ($filterId == self::ALL_OVERRIDES) {
      return [
        'name' => $this->t('<b>*All*</b>'),
        'warning' => '',
        'state_name' => '',
        'import' => '',
        'import_warning' => '',
        'action_names' => $this->actionName[self::ALL_OVERRIDES],
      ];
    }

    $settings = $this->configFactory->get('symfony_mailer.settings')->get('override');
    $info = [];

    foreach ($this->builderManager->getOriginalDefinitions() as $id => $definition) {
      // The key 'proxy' is the deprecated equivalent of 'override' and it
      // indicates a plug-in that doesn't support disabling.
      if ($proxy = $definition['proxy'] ?? FALSE) {
        @trigger_error("The annotation 'proxy' is deprecated in symfony_mailer:1.3.0 and is removed from symfony_mailer:2.0.0. Instead you should 'override'. See https://www.drupal.org/node/3354665", E_USER_DEPRECATED);
      }

      if ($definition['override'] || $proxy) {
        if (!isset($settings[$id])) {
          $settings[$id] = $definition['override'] ? self::STATE_DISABLED : self::STATE_ENABLED;
          $save = TRUE;
        }
        $state = $settings[$id];
        $action_names = $this->actionName[$state];
        if ($proxy) {
          unset($action_names['enable']);
          unset($action_names['disable']);
        }
        if (!$definition['import']) {
          unset($action_names['import']);
        }
        if ($definition['import_warning']) {
          // Move import to the end.
          $import = $action_names['import'];
          unset($action_names['import']);
          $action_names['import'] = $import;
        }

        $info[$id] = [
          'name' => $definition['label'],
          'warning' => $definition['override_warning'],
          'state' => $state,
          'state_name' => $this->stateName[$state],
          'import' => $definition['import'],
          'import_warning' => $definition['import_warning'],
          'action_names' => $action_names,
        ];
      }
    }

    if (!empty($save) || (count($settings) > count($info))) {
      // Fix missing or extra values in settings.
      $settings = array_intersect_key($settings, $info);
      $this->configFactory->getEditable('symfony_mailer.settings')->set('override', $settings)->save();
    }

    ksort($info);
    return $filterId ? ($info[$filterId] ?? NULL) : $info;
  }

  /**
   * {@inheritdoc}
   */
  public function action(string $id, string $action, bool $confirming = FALSE) {
    $info = $this->getInfo($id);
    if (empty($info['action_names'][$action])) {
      throw new \LogicException("Invalid override action '$action'");
    }

    if ($id == self::ALL_OVERRIDES) {
      [$steps, $warnings] = $this->bulkActionSteps($action);
    }
    else {
      $steps[$id] = $action;
    }

    if ($confirming) {
      // Return warnings.
      if (!$steps) {
        return NULL;
      }
      if ($info['warning'] && ($info['state'] == self::STATE_DISABLED) && ($action != 'disable')) {
        $warnings[] = $info['warning'];
      }
      if ($action == 'import' && $info['import_warning']) {
        $warnings[] = $info['import_warning'];
      }
      $warnings[] = $this->actionWarning[$action];
      return $warnings;
    }

    foreach ($steps as $loop_id => $loop_action) {
      $this->doAction($loop_id, $loop_action);
    }
  }

  /**
   * Internal helper function to executes an action.
   *
   * @param string $id
   *   The override ID.
   * @param string $action
   *   The action to execute.
   */
  protected function doAction(string $id, string $action) {
    // Save the state and clear cached definitions so that we can create a
    // newly enabled builder later in this function.
    $settings = $this->configFactory->getEditable('symfony_mailer.settings');
    $existing_state = $settings->get("override.$id");
    $new_state = self::ACTIONS[$action];
    $settings->set("override.$id", $new_state)->save();
    $this->builderManager->clearCachedDefinitions();

    // Find the config names to set or delete.
    $config_names = $this->overrideStorage->listAll($this->getPolicyConfigPrefix() . ".$id");
    $definition = $this->builderManager->getOriginalDefinitions()[$id];
    $config_names = array_merge($config_names, $definition['override_config']);

    if ($action == 'disable') {
      $this->deleteConfig($config_names);
    }
    else {
      // When importing from disabled state, first have to enable.
      $do_defaults = ($action == 'enable') || ($action == 'import' && $existing_state == self::STATE_DISABLED);

      if ($do_defaults) {
        $this->defaultConfig($config_names);
      }

      if ($action == 'import') {
        $this->builderManager->createInstance($id)->import();
      }
    }
  }

  /**
   * Gets the config prefix for the mailer_policy entity type.
   *
   * @return string
   *   The config prefix.
   */
  protected function getPolicyConfigPrefix() {
    if (!$this->policyConfigPrefix) {
      // Don't calculate this in the constructor as the entity types may not
      // have loaded yet.
      $this->policyConfigPrefix = $this->entityTypeManager->getDefinition('mailer_policy')->getConfigPrefix();
    }
    return $this->policyConfigPrefix;
  }

  /**
   * Gets the steps required for a bulk override action.
   *
   * @param string $action
   *   The action to execute.
   *
   * @return array
   *   List of two items:
   *   - steps: array keyed by plugin ID with value equal to the action to run.
   *   - warnings: array of warning messages to display.
   */
  protected function bulkActionSteps(string $action) {
    $steps = [];
    $warnings = [];
    $all_info = $this->getInfo();
    $new_state = self::ACTIONS[$action];

    foreach ($all_info as $id => $info) {
      // Skip if already in the required state.
      if ($info['state'] == $new_state) {
        continue;
      }
      if (($new_state == self::STATE_ENABLED) && ($info['state'] == self::STATE_IMPORTED)) {
        continue;
      }

      // Skip enable if there is a warning.
      $args = ['%name' => $info['name'], '%warning' => $info['warning'], '%import_warning' => $info['import_warning']];
      if ($info['warning'] && ($action != 'disable')) {
        $warnings[] = $this->t('Skipped %name: %warning', $args);
        continue;
      }

      // Skip importing if not available or there is a warning.
      if ($action == 'import' && (!$info['import'] || $info['import_warning'])) {
        $loop_action = 'enable';

        if ($info['state'] == self::STATE_ENABLED) {
          continue;
        }

        $warnings[] = $info['import_warning'] ?
          $this->t('Import skipped for %name: %import_warning', $args) :
          $this->t('Import unavailable for %name', $args);
      }
      else {
        $loop_action = $action;
      }

      $warnings[] = $this->t('Run %action for override %name', ['%name' => $info['name'], '%action' => $loop_action]);
      $steps[$id] = $loop_action;
    }

    return [$steps, $warnings];
  }

  /**
   * Sets default configuration for Mailer override.
   *
   * @param string[] $config_names
   *   The configuration names.
   */
  protected function defaultConfig(array $config_names) {
    foreach ($this->overrideStorage->readMultiple($config_names) as $name => $values) {
      $config_type = $this->configManager->getEntityTypeIdByName($name);
      $storage = $this->entityTypeManager->getStorage($config_type);
      $entity_type = $this->entityTypeManager->getDefinition($config_type);
      $id = ConfigEntityStorage::getIDFromConfigName($name, $entity_type->getConfigPrefix());

      if ($entity = $storage->load($id)) {
        $uuid = $entity->uuid();
        $storage->updateFromStorageRecord($entity, $values);
        $entity->set('uuid', $uuid);
      }
      else {
        $entity = $storage->createFromStorageRecord($values);
      }
      $entity->save();
    }
  }

  /**
   * Deletes configuration.
   *
   * @param string[] $config_names
   *   The configuration names.
   */
  protected function deleteConfig(array $config_names) {
    // Delete config.
    foreach ($config_names as $name) {
      $this->configStorage->delete($name);
    }
  }

}
