<?php

namespace Drupal\group_permissions_merge;

use Drupal\allowed_languages\AllowedLanguagesManagerInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\group\GroupMembershipLoaderInterface;

/**
 * Decorator for the allowed_languages_manager service.
 */
class AllowedLanguagesManagerDecorator implements AllowedLanguagesManagerInterface {

  /**
   * The decorated allowed languages manager.
   *
   * @var \Drupal\allowed_languages\AllowedLanguagesManagerInterface
   */
  protected $allowedLanguagesManager;

  /**
   * The group membership loader.
   *
   * @var \Drupal\group\GroupMembershipLoaderInterface
   */
  protected $membershipLoader;

  /**
   * The current user service.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * Constructs a new AllowedLanguagesManagerDecorator.
   *
   * @param \Drupal\allowed_languages\AllowedLanguagesManagerInterface $allowed_languages_manager
   *   The decorated allowed languages manager.
   * @param \Drupal\group\GroupMembershipLoaderInterface $membership_loader
   *   The group membership loader.
   * @param \Drupal\Core\Session\AccountProxyInterface $current_user
   *   The current user service.
   */
  public function __construct(AllowedLanguagesManagerInterface $allowed_languages_manager, GroupMembershipLoaderInterface $membership_loader, AccountProxyInterface $current_user) {
    $this->allowedLanguagesManager = $allowed_languages_manager;
    $this->membershipLoader = $membership_loader;
    $this->currentUser = $current_user;
  }

  /**
   * {@inheritdoc}
   */
  public function accountFromProxy(?AccountInterface $account = NULL) {
    return $this->allowedLanguagesManager->accountFromProxy($account);
  }

  /**
   * {@inheritdoc}
   */
  public function assignedLanguages(?AccountInterface $account = NULL) {
    try {
      if (!$account) {
        $account = $this->currentUser;
      }

      // Only process for target roles.
      if (!array_intersect($account->getRoles(), GroupPermissionsMergeService::getTargetRoles())) {
        return $this->allowedLanguagesManager->assignedLanguages($account);
      }

      // Check if user has multiple group memberships.
      $memberships = $this->membershipLoader->loadByUser($account);

      if (count($memberships) <= 1) {
        // Single country user, use original service.
        return $this->allowedLanguagesManager->assignedLanguages($account);
      }

      // Multi-country user - get all languages from all groups.
      $all_group_languages = [];
      foreach ($memberships as $membership) {
        $group = $membership->getGroup();
        if ($group && $group->hasField('field_language') && !$group->get('field_language')->isEmpty()) {
          $group_languages = $group->get('field_language')->getValue();
          foreach ($group_languages as $lang_value) {
            if (!empty($lang_value['value'])) {
              $all_group_languages[] = $lang_value['value'];
            }
          }
        }
      }

      return !empty($all_group_languages) ? array_unique($all_group_languages) : $this->allowedLanguagesManager->assignedLanguages($account);
    }
    catch (\Exception $e) {
      // If anything goes wrong, fall back to the original service.
      return $this->allowedLanguagesManager->assignedLanguages($account);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function hasPermissionForLanguage(LanguageInterface $language, ?AccountInterface $account = NULL) {
    try {
      if (!$account) {
        $account = $this->currentUser;
      }

      // Only process for target roles.
      if (!array_intersect($account->getRoles(), GroupPermissionsMergeService::getTargetRoles())) {
        return $this->allowedLanguagesManager->hasPermissionForLanguage($language, $account);
      }

      // Check if user has multiple group memberships.
      $memberships = $this->membershipLoader->loadByUser($account);

      if (count($memberships) <= 1) {
        // Single country user, use original service.
        return $this->allowedLanguagesManager->hasPermissionForLanguage($language, $account);
      }

      // Multi-country user - check if language is in any of their groups.
      $assigned_languages = $this->assignedLanguages($account);
      return in_array($language->getId(), $assigned_languages);
    }
    catch (\Exception $e) {
      // If anything goes wrong, fall back to the original service.
      return $this->allowedLanguagesManager->hasPermissionForLanguage($language, $account);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function isEntityLanguageControlled(EntityInterface $entity) {
    return $this->allowedLanguagesManager->isEntityLanguageControlled($entity);
  }

}
