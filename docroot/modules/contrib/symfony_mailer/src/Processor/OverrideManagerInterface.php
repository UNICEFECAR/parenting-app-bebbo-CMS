<?php

namespace Drupal\symfony_mailer\Processor;

/**
 * Provides the interface for the override manager.
 */
interface OverrideManagerInterface {

  /**
   * Enabled and imported.
   */
  const STATE_IMPORTED = 1;

  /**
   * Enabled.
   */
  const STATE_ENABLED = 2;

  /**
   * Disabled.
   */
  const STATE_DISABLED = 3;

  /**
   * Actions.
   *
   * The array key is the action name and the value is the corresponding state.
   */
  const ACTIONS = [
    'import' => self::STATE_IMPORTED,
    'enable' => self::STATE_ENABLED,
    'disable' => self::STATE_DISABLED,
  ];

  /**
   * All overrides.
   *
   * Special value passed for an override ID meaning to apply to all overrides.
   */
  const ALL_OVERRIDES = '_';

  /**
   * Gets information about Mailer overrides.
   *
   * @param string $filterId
   *   (optional) If set, return only the matching override ID, or NULL if it
   *   does not exist. If omitted, return an array of all overrides. If set to
   *   ALL_OVERRIDES, then return a single entry with human-readable strings
   *   describing the an action applied to all overrides.
   *
   * @return array
   *   Array keyed by plugin ID with values as an array with these keys:
   *   - name: Human-readable name for this override.
   *   - warning: Human-readable warning for this override.
   *   - state: State, one of the STATE_ constants.
   *   - state_name: Human-readable name for the state.
   *   - import: Human-readable description of the import operation.
   *   - import_warning: Human-readable warning for the import operation.
   *   - action_names: Array of human-readable action names.
   */
  public function getInfo(string $filterId = NULL);

  /**
   * Executes an override action.
   *
   * @param string $id
   *   The override ID, or ALL_OVERRIDES for all overrides.
   * @param string $action
   *   The action to execute.
   * @param bool $confirming
   *   (optional) Indicates to show human-readable warnings for confirming the
   *   action, then exit without running anything.
   *
   * @return string[]|null
   *   An array of warnings (if $confirming is set) or NULL.
   */
  public function action(string $id, string $action, bool $confirming = FALSE);

}
