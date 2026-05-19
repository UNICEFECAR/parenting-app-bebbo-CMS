<?php

namespace Drupal\pb_strings\Controller;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Session\AccountInterface;
use Drupal\pb_strings\Form\StringsFilterForm;
use Drupal\pb_strings\Form\StringsTranslateForm;
use Drupal\taxonomy\VocabularyInterface;

/**
 * Controller for the Strings admin list page.
 */
class StringsListController extends ControllerBase {

  /**
   * Renders the strings list page with filter and translate forms.
   *
   * @param \Drupal\taxonomy\VocabularyInterface $taxonomy_vocabulary
   *   The taxonomy vocabulary entity.
   *
   * @return array
   *   Render array containing both forms.
   */
  public function listPage(VocabularyInterface $taxonomy_vocabulary): array {
    return [
      'filter' => $this->formBuilder()->getForm(StringsFilterForm::class),
      'form' => $this->formBuilder()->getForm(StringsTranslateForm::class),
    ];
  }

  /**
   * Access check for the strings list page.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The current user account.
   * @param \Drupal\taxonomy\VocabularyInterface|null $taxonomy_vocabulary
   *   The taxonomy vocabulary entity.
   *
   * @return \Drupal\Core\Access\AccessResult
   *   The access result.
   */
  public function access(AccountInterface $account, ?VocabularyInterface $taxonomy_vocabulary = NULL): AccessResult {
    if ($taxonomy_vocabulary === NULL || $taxonomy_vocabulary->id() !== 'strings') {
      return AccessResult::forbidden()->addCacheableDependency($taxonomy_vocabulary ?? AccessResult::neutral());
    }

    return AccessResult::allowedIfHasPermissions($account, [
      'administer strings',
      'translate strings',
    ], 'OR')->addCacheableDependency($taxonomy_vocabulary);
  }

}
