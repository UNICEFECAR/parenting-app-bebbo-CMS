<?php

/**
 * @file
 * Drush commands for the qa_accounts module.
 */

/**
 * Implements hook_drush_command().
 */
function qa_accounts_drush_command() {
  $items = [];

  $items['qa-accounts-test-users-create'] = [
    'description' => 'Create a test user for each custom user role.',
    'aliases' => [
      'test-users-create',
      'create-test-users',
    ],
  ];

  $items['qa-accounts-test-users-delete'] = [
    'description' => 'Delete test users created by `qa-accounts-create-test-users`.',
    'aliases' => [
      'test-users-delete',
      'delete-test-users',
    ],
  ];

  return $items;
}

/**
 * Implements hook_drush_help().
 */
function qa_accounts_drush_help($section) {
  switch ($section) {
    case 'meta:qa_accounts:title':
      return dt('QA Accounts commands');

    case 'drush:qa-accounts-test-users-create':
      $message = dt("Create a test user for each custom user role, with username and password both equal to the role machine name, e.g., 'page_creator'.");
      return $message;
  }
}

/**
 * Implements drush_hook_COMMAND().
 */
function drush_qa_accounts_test_users_create() {
  \Drupal::service('qa_accounts.create_delete')->createQaAccounts();
}

/**
 * Implements drush_hook_COMMAND().
 */
function drush_qa_accounts_test_users_delete() {
  \Drupal::service('qa_accounts.create_delete')->deleteQaAccounts();
}
