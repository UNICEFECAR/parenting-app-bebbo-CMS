<?php

namespace Drupal\qa_accounts\Commands;

use Drush\Commands\DrushCommands;
use Drupal\qa_accounts\QaAccountsCreateDeleteInterface;

/**
 * Defines drush commands for QA Accounts module.
 */
class QaAccountsCommands extends DrushCommands {

  /**
   * Service that creates and deletes QA accounts.
   *
   * @var \Drupal\qa_accounts\QaAccountsCreateDeleteInterface
   */
  protected $qaAccountsCreateDelete;

  /**
   * QaAccountsCommands constructor.
   *
   * @param \Drupal\qa_accounts\QaAccountsCreateDeleteInterface $qa_accounts_create_delete
   *   Service that creates and deletes QA accounts.
   */
  public function __construct(QaAccountsCreateDeleteInterface $qa_accounts_create_delete) {
    $this->qaAccountsCreateDelete = $qa_accounts_create_delete;
  }

  /**
   * Creates a test user for each custom role.
   *
   * @usage drush qa_accounts:create
   *   Create a test user for each custom user role.
   *
   * @command qa_accounts:create
   *
   * @aliases test-users-create,create-test-users,qac
   */
  public function testUsersCreate() {
    $this->qaAccountsCreateDelete->createQaAccounts();
  }

  /**
   * Deletes the test users created by QA Accounts.
   *
   * @usage drush qa_accounts:delete
   *   Deletes the test users created by QA Accounts.
   *
   * @command qa_accounts:delete
   *
   * @aliases test-users-delete,delete-test-users,qad
   */
  public function testUsersDelete() {
    $this->qaAccountsCreateDelete->deleteQaAccounts();
  }

}
