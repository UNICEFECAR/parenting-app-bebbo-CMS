<?php

namespace Drupal\custom_article\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\taxonomy\Entity\Vocabulary;

/**
 * Provides a form to start the batch process.
 */
class LowercaseKeywordsForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'custom_article_lowercase_keywords_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['actions'] = [
      '#type' => 'actions',
    ];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Start Batch Process'),
      '#button_type' => 'primary',
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Set up the batch process.
    $batch = [
      'title' => $this->t('Lowercasing keyword terms'),
      'operations' => [],
      'finished' => '\Drupal\custom_article\Form\LowercaseKeywordsForm::batchFinished',
    ];

    // Load all terms in the "keyword" vocabulary.
    $vocabulary = Vocabulary::load('keywords');
    $terms = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadTree($vocabulary->id());

    // Add each term to the batch operations.
    foreach ($terms as $term) {
      $batch['operations'][] = ['\Drupal\custom_article\Form\LowercaseKeywordsForm::lowercaseTerm', [$term]];
    }

    batch_set($batch);
  }

  /**
   * Batch operation callback for processing each term.
   */
  public static function lowercaseTerm($term) {
    // Load the term by ID.
    $taxonomy_term = \Drupal\taxonomy\Entity\Term::load($term->tid);

    // Process the term for each language.
    foreach (\Drupal::languageManager()->getLanguages() as $language) {
      $langcode = $language->getId();
      $translation = $taxonomy_term->hasTranslation($langcode) ? $taxonomy_term->getTranslation($langcode) : $taxonomy_term;
      $name = $translation->getName();
      $name = trim($name);
     

      // Make the first letter lowercase.
  
      $lowercase_name = mb_strtolower(mb_substr($name, 0, 1)) . mb_substr($name, 1);
     // $lowercase_name = lcfirst(trim($name));

      // Save the updated term name if it's changed.
      if ($lowercase_name !== $name) {
        \Drupal::logger('lowercase_keyword')->notice("updated term name to lowercase ".$term->tid. " for name= ". $name ." language= ".$langcode);
        $translation->setName($lowercase_name);
        $translation->save();
      
      }
    }
  }

  /**
   * Batch finished callback.
   */
  public static function batchFinished($success, $results, $operations) {
    if ($success) {
      \Drupal::messenger()->addMessage(t('All terms have been successfully processed.'));
    }
    else {
      \Drupal::messenger()->addMessage(t('Some errors occurred during processing.'), 'error');
    }
  }

}
