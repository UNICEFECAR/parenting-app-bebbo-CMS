<?php

namespace Drupal\allowed_languages\Access;

use Drupal\allowed_languages\AllowedLanguagesManagerInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\Routing\Route;

/**
 * Allowed languages content translation access check.
 */
class ContentTranslationAccessCheck extends AccessCheckBase {

  /**
   * Drupal language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  private $languageManager;

  /**
   * AccessCheck constructor.
   *
   * @param \Drupal\allowed_languages\AllowedLanguagesManagerInterface $allowed_languages_manager
   *   The allowed language manager service.
   * @param \Drupal\Core\Language\LanguageManagerInterface $language_manager
   *   Drupal language manager.
   */
  public function __construct(
    AllowedLanguagesManagerInterface $allowed_languages_manager,
    LanguageManagerInterface $language_manager
  ) {
    parent::__construct($allowed_languages_manager);

    $this->languageManager = $language_manager;
  }

  /**
   * Check language access when managing content translations.
   *
   * This access check is based on the access check provided by the content
   * translation module and uses the same parameters in the access callback.
   *
   * @param \Symfony\Component\Routing\Route $route
   *   The route to check against.
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The parametrized route.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The currently logged in account.
   * @param string $source
   *   (optional) For a create operation, the language code of the source.
   * @param string $target
   *   (optional) For a create operation, the language code of the translation.
   * @param string $language
   *   (optional) For an update or delete operation, the language code of the
   *   translation being updated or deleted.
   * @param string $entity_type_id
   *   (optional) The entity type ID.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function access(Route $route, RouteMatchInterface $route_match, AccountInterface $account, $source = NULL, $target = NULL, $language = NULL, $entity_type_id = NULL) {
    /**
     * @var \Drupal\Core\Entity\ContentEntityInterface $entity
     */
    $entity = $route_match->getParameter($entity_type_id);

    // If the entity could not be found on the parameters let other modules
    // take care of the access check.
    if (!$entity) {
      return AccessResult::neutral();
    }

    $language = $this->getTargetLanguage($target);
    $user = $this->allowedLanguagesManager->userEntityFromProxy($account);
    if ($this->allowedLanguagesManager->hasPermissionForLanguage($language, $user)) {
      return AccessResult::allowed()->cachePerUser()->addCacheableDependency($user);
    }

    return AccessResult::forbidden()->cachePerUser()->addCacheableDependency($user);
  }

  /**
   * Get the target language object.
   *
   * @param string $target
   *   The target language id.
   *
   * @return \Drupal\Core\Language\LanguageInterface
   *   The target language object or the current content language.
   */
  private function getTargetLanguage($target) {
    return $this->languageManager->getLanguage($target)
      ?: $this->languageManager->getCurrentLanguage(LanguageInterface::TYPE_CONTENT);
  }

}
