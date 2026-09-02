<?php

namespace Drupal\bebbo_custom_general\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\bebbo_custom_general\ApplyNodeTranslations;

/**
 * Admin form to propagate related articles/videos across node translations.
 */
class ApplyTransRelatedArticlesVideo extends FormBase {

  /**
   * Get form ID.
   */
  public function getFormId() {
    return 'apply_trans_related_articles_video';
  }

  /**
   * Force update check build form.
   *
   * @param array $form
   *   The custom form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The custom form state.
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    $form['help'] = [
      '#type' => 'details',
      '#title' => $this->t('How this works'),
      '#open' => TRUE,
      'body' => [
        '#markup' => '<p>' . $this->t('This tool copies the <em>Related articles</em> and <em>Related video articles</em> set on the <strong>English</strong> version of a node onto <strong>all of its translations</strong>.') . '</p>'
        . '<ul>'
        . '<li>' . $this->t('It runs over <strong>every</strong> node of the selected content type — there is no per-node selection.') . '</li>'
        . '<li>' . $this->t('For each English node, every related (video) article reference that is missing on a translation is appended to that translation. Existing references are left untouched and nothing is removed.') . '</li>'
        . '<li>' . $this->t('Translations that already match English are skipped, so it is safe to run more than once.') . '</li>'
        . '<li>' . $this->t('Processing happens in a batch and may take a while on large sites. A new revision is saved on each translation that is updated.') . '</li>'
        . '</ul>'
        . '<p>' . $this->t('Pick the content type below and select <em>Apply</em> to start.') . '</p>',
      ],
    ];

    $content_types = ['article' => 'Article', 'video_article' => 'Video Article'];
    /* Dropdown Select. */
    $form['content_types'] = [
      '#type' => 'select',
      '#title' => $this->t('Content type'),
      '#description' => $this->t('Choose which content type to sync related references for across all translations.'),
      '#options' => $content_types,
    ];

    /* Add a submit button. */
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Apply'),
      '#button_type' => 'primary',
    ];

    return $form;
  }

  /**
   * Submit the form.
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    ApplyNodeTranslations::initiateBatchProcessing($form_state->getValue('content_types'));
  }

}
