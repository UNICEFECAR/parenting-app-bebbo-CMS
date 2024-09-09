<?php

namespace Drupal\taxonomy_access_fix;

use Drupal\Core\Url;
use Drupal\taxonomy\Form\VocabularyResetForm as OriginalVocabularyResetForm;

/**
 * {@inheritdoc}
 */
class VocabularyResetForm extends OriginalVocabularyResetForm {

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl() {
    $url = parent::getCancelUrl();
    if (!$url->access()) {
      return Url::fromRoute('<front>');
    }
    return $url;
  }

}
