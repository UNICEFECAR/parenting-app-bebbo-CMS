<?php

namespace Drupal\symfony_mailer\Commands;

use Consolidation\OutputFormatters\StructuredData\RowsOfFields;
use Drupal\symfony_mailer\Processor\OverrideManagerInterface;
use Drush\Commands\DrushCommands;

/**
 * Symfony Mailer drush commands.
 */
class MailerCommands extends DrushCommands {

  /**
   * The override manager.
   *
   * @var \Drupal\symfony_mailer\Processor\OverrideManagerInterface
   */
  protected $overrideManager;

  /**
   * Constructs the MailerCommands object.
   *
   * @param \Drupal\symfony_mailer\Processor\OverrideManagerInterface $override_manager
   *   The override manager.
   */
  public function __construct(OverrideManagerInterface $override_manager) {
    $this->overrideManager = $override_manager;
  }

  /**
   * Executes an override action.
   *
   * @param string $action
   *   Action to run: 'import', 'enable', or 'disable'.
   * @param string $id
   *   (optional) Override ID, or omit to execute all.
   *
   * @command mailer:override
   */
  public function override(string $action, string $id = OverrideManagerInterface::ALL_OVERRIDES) {
    $info = $this->overrideManager->getInfo($id);
    $action_name = $info['action_names'][$action] ?? NULL;
    if (!$action_name) {
      throw new NotFoundHttpException();
    }

    $warnings = $this->overrideManager->action($id, $action, TRUE);
    if (!$warnings) {
      $this->logger->warning(dt('No available actions'));
      return FALSE;
    }

    // Use the last warning as the description.
    $warnings = $this->overrideManager->action($id, $action, TRUE);
    $description = array_pop($warnings);
    foreach ($warnings as $warning) {
      $warning = preg_replace("|</?em[^>]*>|", "'", $warning);
      $this->output()->writeln($warning);
    }
    if (!$this->io()->confirm(dt('!description Do you want to continue?', ['!description' => $description]))) {
      throw new UserAbortException();
    }

    $this->overrideManager->action($id, $action);
    $args = ['%name' => $info['name'], '%action' => $action_name];
    if ($id == OverrideManagerInterface::ALL_OVERRIDES) {
      $this->logger()->success(dt('Completed %action for all overrides', $args));
    }
    else {
      $this->logger()->success(dt('Completed %action for override %name', $args));
    }
  }

  /**
   * Gets information about Mailer overrides.
   *
   * @param array $options
   *   An associative array of options whose values come from cli, aliases,
   *   config, etc.
   *
   * @command mailer:override-info
   * @field-labels
   *   name: Name
   *   state_name: State
   *   import: Import
   */
  public function overrideInfo(array $options = ['format' => 'table']) {
    $info = $this->overrideManager->getInfo();
    foreach ($info as &$row) {
      if ($warning = $row['warning']) {
        $row['name'] .= "\nWarning: $warning";
      }
      if ($import_warning = $row['import_warning']) {
        $row['import'] .= "\nWarning: $import_warning";
      }
    }
    return new RowsOfFields($info);
  }

}
