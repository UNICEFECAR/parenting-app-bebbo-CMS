<?php

namespace Drupal\allowed_languages;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Entity\ContentEntityInterface;

/**
 * The allowed language manager controls access to content by language.
 *
 * @package Drupal\allowed_languages
 */
class AllowedLanguagesManager implements AllowedLanguagesManagerInterface {

  /**
   * The current user service.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * AllowedLanguagesManager constructor.
   *
   * @param \Drupal\Core\Session\AccountProxyInterface $current_user
   *   The current user service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   */
  public function __construct(AccountProxyInterface $current_user, EntityTypeManagerInterface $entity_type_manager) {
    $this->currentUser = $current_user;
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public function accountFromProxy(AccountInterface $account = NULL) {
    if ($account === NULL) {
      $account = $this->currentUser;
    }

    if ($account instanceof AccountProxyInterface) {
      $account = $this->entityTypeManager
        ->getStorage('user')
        ->load($account->id());
    }

    return $account;
  }

  /**
   * {@inheritdoc}
   */
  public function assignedLanguages(AccountInterface $account = NULL) {
    $account = $this->accountFromProxy($account ?? $this->currentUser);

    $language_values = [];

    // Make sure the field exists before attempting to get languages.
    if (!$account->hasField('allowed_languages')) {
      return $language_values;
    }

    // Get the id of each referenced language.
    foreach ($account->get('allowed_languages')->getValue() as $item) {
      $language_values[] = $item['target_id'];
    }

    return $language_values;
  }

  /**
   * {@inheritdoc}
   */
  public function hasPermissionForLanguage(LanguageInterface $language, AccountInterface $account = NULL) {
    $account = $this->accountFromProxy($this->currentUser);

    // Bypass the check if the user has permission to translate all languages.
    if ($account->hasPermission('translate all languages')) {
      return TRUE;
    }

    $allowed_languages = $this->assignedLanguages($account);
    return in_array($language->getId(), $allowed_languages);
  }

  /**
   * {@inheritdoc}
   */
  public function isEntityLanguageControlled(EntityInterface $entity) {
    if ($entity instanceof ContentEntityInterface && $entity->isTranslatable()) {
      return TRUE;
    }

    return FALSE;
  }

}
