<?php

namespace Drupal\qa_accounts;

/**
 * Interface for a service that creates and deletes QA accounts for roles.
 */
interface QaAccountsCreateDeleteInterface {

  /**
   * Creates QA accounts for all existing roles.
   */
  public function createQaAccounts();

  /**
   * Creates QA account for specified role.
   *
   * @param string $role_name
   *   The machine name of the role the account is to be created for.
   */
  public function createQaAccountForRole($role_name);

  /**
   * Deletes QA accounts.
   */
  public function deleteQaAccounts();

  /**
   * Deletes QA account for specified role.
   *
   * @param string $role_name
   *   The machine name of the role the account is to be deleted for.
   */
  public function deleteQaAccountForRole($role_name);

}
