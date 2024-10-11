<?php

namespace Drupal\custom_article\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\node\Entity\Node;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\taxonomy\Entity\Term;

class CopyTranslatedField extends FormBase {

  protected $entityTypeManager;

  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
  }

  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager')
    );
  }

  public function getFormId() {
    return 'custom_article_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state) {
    $content_types = $this->entityTypeManager->getStorage('node_type')->loadMultiple();
    $options = [];
    foreach ($content_types as $type_id => $type) {
      $options[$type_id] = $type->label();
    }

    $form['content_type'] = [
      '#type' => 'select',
      '#title' => $this->t('Content Type'),
      '#options' => $options,
      '#required' => TRUE,
      '#ajax' => [
        'callback' => '::updateFieldOptions',
        'event' => 'change',
        'wrapper' => 'field-select-wrapper',
      ],
    ];

    $form['field_wrapper'] = [
      '#type' => 'container',
      '#attributes' => ['id' => 'field-select-wrapper'],
    ];

    if ($content_type = $form_state->getValue('content_type')) {
      $fields = $this->getContentFields($content_type);

      $form['field_wrapper']['field_name'] = [
        '#type' => 'select',
        '#title' => $this->t('Field'),
        '#options' => $fields,
        '#required' => TRUE,
      ];
    }

    $form['actions']['#type'] = 'actions';
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Copy Field Value'),
    ];

    return $form;
  }

  public function updateFieldOptions(array &$form, FormStateInterface $form_state) {
    return $form['field_wrapper'];
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {
    $content_type = $form_state->getValue('content_type');
    $field_name = $form_state->getValue('field_name');

    // Define batch operations.
    $operations = [
      [
        '\Drupal\custom_article\Form\CopyTranslatedField::processBatch',
        [$content_type, $field_name],
      ],
    ];

    $batch = [
      'title' => $this->t('Copying field values to translations...'),
      'operations' => $operations,
      'finished' => '\Drupal\custom_article\Form\CopyTranslatedField::batchFinished',
    ];

    batch_set($batch);
  }

  protected function getContentFields($content_type) {
    $fields = \Drupal::service('entity_field.manager')->getFieldDefinitions('node', $content_type);
    $options = [];
    foreach ($fields as $field_name => $field_definition) {
      if ($field_definition->isTranslatable()) {
        if($field_name == 'field_keywords' || $field_name == 'field_related_articles'){
            $options[$field_name] = $field_definition->getLabel();
        }
        
      }
    }
    return $options;
  }

  public static function processBatch($content_type, $field_name, &$context) {
    if (empty($context['sandbox'])) {
      $query = \Drupal::entityQuery('node')
        ->accessCheck(TRUE)
        ->condition('type', $content_type)
        ->condition('status', 1)
        ->sort('nid', 'DESC');

      $nids = $query->execute();
      $context['sandbox']['nids'] = $nids;
      $context['sandbox']['progress'] = 0;
      $context['sandbox']['current_node'] = 0;
      $context['sandbox']['max'] = count($nids);
    }

    $batch_size = 5; // Define the size of the batch.
    $nids = array_slice($context['sandbox']['nids'], $context['sandbox']['progress'], $batch_size);
    $nodes = Node::loadMultiple($nids);

    foreach ($nodes as $node) {
      \Drupal::logger('custom_article')->notice('Processing node @nid', ['@nid' => $node->id()]);

      if ($node->hasField($field_name)) {
        $field_values = $node->get($field_name)->getValue();
        $field_type = $node->get($field_name)->getFieldDefinition()->getType();
        $field_type_setting = $node->get($field_name)->getSetting('target_type');

        foreach ($node->getTranslationLanguages() as $langcode => $language) {
          if ($node->hasTranslation($langcode)) {
            if (!empty($field_values)) {
              $field_value_list = [];

              if ($field_type == 'entity_reference') {
                foreach ($field_values as $field_value) {
                  $term = NULL;
                  if ($field_type_setting == 'taxonomy_term') {
                    $term = Term::load($field_value['target_id']);
                  }
                  if ($field_type_setting == 'node') {
                    $term = Node::load($field_value['target_id']);
                  }
                  if ($term && $term->hasTranslation($langcode)) {
                    $translated_term = $term->getTranslation($langcode);
                    $field_value_list[] = $translated_term->id();
                  }
                }
              }

              $translated_node = $node->getTranslation($langcode);
              $translated_node->set($field_name, $field_value_list);
              $translated_node->save();
            }
          }
        }
      }
      $context['sandbox']['progress'] += $batch_size;
      $context['sandbox']['current_node'] = $node->id();
      $context['message'] = t('Processing node @nid...', ['@nid' => $node->id()]);
    }

    // Check if we are done.
    if ($context['sandbox']['progress'] < $context['sandbox']['max']) {
      $context['finished'] = $context['sandbox']['progress'] / $context['sandbox']['max'];
    } else {
      $context['finished'] = 1;
    }
  }

  public static function batchFinished($success, $results, $operations) {
    if ($success) {
      \Drupal::messenger()->addMessage(t('Field values have been successfully copied to translations.'));
    } else {
      \Drupal::messenger()->addMessage(t('An error occurred during the process.'));
    }
  }
}
