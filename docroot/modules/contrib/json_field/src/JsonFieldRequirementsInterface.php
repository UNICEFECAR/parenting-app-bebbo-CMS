<?php

namespace Drupal\json_field;

/**
 * Defines Json Field Library interface.
 */
interface JsonFieldRequirementsInterface {

  /**
   * Check library avalilability.
   *
   * @param bool $warning
   *   Add a warning message if library is not available.
   *
   * @return bool
   *   TRUE if library is installed, FALSE if not.
   */
  public function libraryIsAvailable(bool $warning = FALSE);

  /**
   * Get the warning message.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup
   *   The warning message.
   */
  public function getLibraryWarningMessage();

  /**
   * Check if the current application driver supports Json Data Type.
   *
   * @return bool
   *   TRUE if database supports Json Data Type, FALSE if not.
   */
  public function databaseIsCompatible();

  /**
   * Get the database warning message.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup
   *   The warning message.
   */
  public function getDatabaseWarningMessage();

}
