<?php

namespace Drupal\group_country_field\Access;

use Drupal\allowed_languages\AllowedLanguagesManagerInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Restricts AI translation to the languages assigned to the user.
 *
 * The ai_translate route only requires the 'create ai content translation'
 * permission. The allowed_languages module registers its language check
 * against '_access_content_translation_manage', which that route does not use,
 * so without this check a user could AI translate into any language on the
 * site regardless of the languages assigned to their profile.
 */
class AiTranslateLanguageAccess implements ContainerInjectionInterface {

  /**
   * The allowed languages manager, NULL when the module is not installed.
   *
   * @var \Drupal\allowed_languages\AllowedLanguagesManagerInterface|null
   */
  protected ?AllowedLanguagesManagerInterface $allowedLanguagesManager;

  /**
   * Constructs the access checker.
   *
   * @param \Drupal\allowed_languages\AllowedLanguagesManagerInterface|null $allowed_languages_manager
   *   The allowed languages manager, or NULL when unavailable.
   */
  public function __construct(?AllowedLanguagesManagerInterface $allowed_languages_manager) {
    $this->allowedLanguagesManager = $allowed_languages_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static($container->has('allowed_languages.allowed_languages_manager')
      ? $container->get('allowed_languages.allowed_languages_manager')
      : NULL);
  }

  /**
   * Checks that the target language is assigned to the account.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The account requesting the translation.
   * @param string $lang_to
   *   Language code of the requested translation.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function access(AccountInterface $account, string $lang_to): AccessResultInterface {
    if ($account->hasPermission('translate all languages') || $this->allowedLanguagesManager === NULL) {
      return AccessResult::allowed()->cachePerPermissions();
    }

    $assigned = $this->allowedLanguagesManager->assignedLanguages($account);

    return AccessResult::allowedIf(in_array($lang_to, $assigned, TRUE))
      ->cachePerUser();
  }

}
