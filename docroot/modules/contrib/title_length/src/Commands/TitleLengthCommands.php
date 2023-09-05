<?php

namespace Drupal\title_length\Commands;

use Drush\Commands\DrushCommands;
use Drush\Exceptions\CommandFailedException;

/**
 * Title length command for launch manual updating.
 */
class TitleLengthCommands extends DrushCommands {

  /**
   * Change length of entity title field.
   *
   * @param string $entity_type
   *   Entity type (node or taxonomy_term).
   *
   * @usage title_length:update node
   *   Usage description
   *
   * @command title_length:update
   *
   * @throws \Drush\Exceptions\CommandFailedException
   */
  public function update(string $entity_type): void {
    $titleLengthService = \Drupal::service($entity_type . '_title_length.' . $entity_type);
    if ($titleLengthService->checkIfExistEntitiesWithLongTitles($titleLengthService::getLength())) {
      throw new CommandFailedException('Entities or entity revisions exist with long titles. The length cannot be lowered.');
    }
    $titleLengthService->changeLength($titleLengthService::getLength());
    $this->logger()->success(dt('Update executed successfully.'));
  }

}
