<?php

namespace Drupal\symfony_mailer\Processor;

use Drupal\Component\Plugin\PluginManagerInterface;

/**
 * Provides the interface for the email builder plugin manager.
 */
interface EmailBuilderManagerInterface extends PluginManagerInterface {

  /**
   * Import not yet done, ready to import.
   *
   * @deprecated in symfony_mailer:1.3.0 and is removed from symfony_mailer:2.0.0.
   *   There is no equivalent.
   *
   * @see https://www.drupal.org/node/3354665
   */
  const IMPORT_READY = 0;

  /**
   * Import complete.
   *
   * @deprecated in symfony_mailer:1.3.0 and is removed from symfony_mailer:2.0.0.
   *   Instead you should use 'OverrideManagerInterface::STATE_IMPORTED'.
   *
   * @see https://www.drupal.org/node/3354665
   */
  const IMPORT_COMPLETE = 1;

  /**
   * Import skipped.
   *
   * @deprecated in symfony_mailer:1.3.0 and is removed from symfony_mailer:2.0.0.
   *   Instead you should use 'OverrideManagerInterface::STATE_ENABLED'.
   *
   * @see https://www.drupal.org/node/3354665
   */
  const IMPORT_SKIPPED = 2;

  /**
   * Gets information about config importing.
   *
   * @return array
   *   Array keyed by plugin ID with values as an array with these keys:
   *   - name: A human-readable name for this import operation.
   *   - state: State, one of the IMPORT_ constants.
   *   - state_name: A human-readable name for the state.
   *   - warning: A human-readable warning.
   *
   * @deprecated in symfony_mailer:1.3.0 and is removed from symfony_mailer:2.0.0.
   *   Instead you should use OverrideManagerInterface::getInfo().
   *
   * @see https://www.drupal.org/node/3354665
   */
  public function getImportInfo();

  /**
   * Checks if config importing is required.
   *
   * @return bool
   *   TRUE if import is required.
   *
   * @deprecated in symfony_mailer:1.3.0 and is removed from symfony_mailer:2.0.0.
   *   The concept has been removed and you can assume a value of FALSE.
   *
   * @see https://www.drupal.org/node/3354665
   */
  public function importRequired();

  /**
   * Imports config for the specified id.
   *
   * @param string $id
   *   The plugin ID.
   *
   * @deprecated in symfony_mailer:1.3.0 and is removed from symfony_mailer:2.0.0.
   *   Instead you should use OverrideManagerInterface::action()
   *
   * @see https://www.drupal.org/node/3354665
   */
  public function import(string $id);

  /**
   * Imports all config not yet imported.
   *
   * @deprecated in symfony_mailer:1.3.0 and is removed from symfony_mailer:2.0.0.
   *   Instead you should use OverrideManagerInterface::bulkAction()
   *
   * @see https://www.drupal.org/node/3354665
   */
  public function importAll();

  /**
   * Imports all config not yet imported.
   *
   * @param string $id
   *   The plugin ID.
   * @param int $state
   *   The state, one of the IMPORT_ constants.
   *
   * @deprecated in symfony_mailer:1.3.0 and is removed from symfony_mailer:2.0.0.
   *   Instead you should use OverrideManagerInterface::action()
   *
   * @see https://www.drupal.org/node/3354665
   */
  public function setImportState(string $id, int $state);

  /**
   * Creates a plugin instance from a legacy message array.
   *
   * @param array $message
   *   The message.
   */
  public function createInstanceFromMessage(array $message);

}
