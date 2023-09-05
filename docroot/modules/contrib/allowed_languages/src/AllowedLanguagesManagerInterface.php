<?php

namespace Drupal\allowed_languages;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * The allowed language manager controls access to content by language.
 *
 * @package Drupal\allowed_languages
 */
interface AllowedLanguagesManagerInterface {

  /**
   * Get the actual account entity behind the proxy.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The account proxy object to use to get the account entity.
   *
   * @return \Drupal\user\UserInterface
   *   The account entity behind the proxy.
   */
  public function accountFromProxy(AccountInterface $account = NULL);

  /**
   * Get the allowed languages for the specified user.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The user to get allowed languages for.
   *
   * @return array
   *   An array of allowed language ids.
   */
  public function assignedLanguages(AccountInterface $account = NULL);

  /**
   * Checks if the user is allowed to translate the specified language.
   *
   * @param \Drupal\Core\Language\LanguageInterface $language
   *   The language to check for.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The user to check.
   *
   * @return bool
   *   If the user is allowed to or not.
   */
  public function hasPermissionForLanguage(LanguageInterface $language, AccountInterface $account = NULL);

  /**
   * Decide whether the entity should be language controlled or not.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity to test.
   *
   * @return bool
   *   TRUE if the entity should have permissions applied, FALSE otherwise.
   */
  public function isEntityLanguageControlled(EntityInterface $entity);

}
