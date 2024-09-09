<?php

namespace Drupal\taxonomy_access_fix;

/**
 * Allows to get a reason for forbidden operations in access control handlers.
 */
trait ReasonTrait {

  /**
   * Gets reason why an operation is forbidden.
   *
   * @param string[] $permissions
   *   Permission names that would allow the operation. The admin permission
   *   must not be included.
   *
   * @return string
   *   Reason why an operation is forbidden.
   */
  protected function getReason(array $permissions): string {
    $permissions[] = 'administer taxonomy';
    return sprintf('The %s permission is required.', sprintf('\'%s\'', implode('\' OR \'', $permissions)));
  }

}
