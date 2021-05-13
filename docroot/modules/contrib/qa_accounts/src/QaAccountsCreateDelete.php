<?php

namespace Drupal\qa_accounts;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;

/**
 * Service that creates and deletes QA accounts for roles.
 */
class QaAccountsCreateDelete implements QaAccountsCreateDeleteInterface {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The logger channel for this service.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected $logger;

  /**
   * QaAccountsCreateDelete constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger factory.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, LoggerChannelFactoryInterface $logger_factory) {
    $this->entityTypeManager = $entity_type_manager;
    $this->logger = $logger_factory->get('qa_accounts');
  }

  /**
   * {@inheritdoc}
   */
  public function createQaAccounts() {
    $roles = $this->entityTypeManager->getStorage('user_role')->loadMultiple();
    foreach ($roles as $role_name => $role) {
      if ($role_name === 'anonymous') {
        continue;
      }

      $this->createQaAccountForRole($role_name);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function createQaAccountForRole($role_name) {
    $username = 'qa_' . $role_name;

    // Check if qa user already exists.
    $user_storage = $this->entityTypeManager->getStorage('user');
    $uids = $user_storage
      ->getQuery()
      ->condition('name', $username)
      ->accessCheck(FALSE)
      ->range(0, 1)
      ->execute();

    if ($uids) {
      $this->logger->notice('User @name already exists.', ['@name' => $username]);
    }
    else {
      /** @var \Drupal\user\Entity\User $user */
      $user = $user_storage->create();
      $user->enforceIsNew();
      $user->setUsername($username);
      $user->setEmail($username . '@example.com');
      $user->setPassword($username);
      if ($role_name !== 'authenticated') {
        $user->addRole($role_name);
      }
      $user->activate();
      $user->save();
      $this->logger->notice('Created user @name.', ['@name' => $username]);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function deleteQaAccounts() {
    $roles = $this->entityTypeManager->getStorage('user_role')->loadMultiple();
    foreach ($roles as $role_name => $role) {
      if ($role_name === 'anonymous') {
        continue;
      }

      $this->deleteQaAccountForRole($role_name);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function deleteQaAccountForRole($role_name) {
    $username = 'qa_' . $role_name;

    // Check that user for role exists.
    $users = $this->entityTypeManager->getStorage('user')
      ->loadByProperties(['name' => $username]);
    $user = $users ? reset($users) : NULL;

    if ($user) {
      $user->delete();
      $this->logger->notice('Deleted user @name.', ['@name' => $username]);
    }
    else {
      $this->logger->notice('No such user @name.', ['@name' => $username]);
    }
  }

}
