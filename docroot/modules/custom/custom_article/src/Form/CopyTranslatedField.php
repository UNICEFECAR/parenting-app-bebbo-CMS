<?php

namespace Drupal\custom_article\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\node\Entity\Node;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\taxonomy\Entity\Term;

/**
 * Provides a form to copy translated field values between node translations.
 */
class CopyTranslatedField extends FormBase {

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The entity field manager service.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $entityFieldManager;

  /**
   * Constructs a CopyTranslatedField form object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entity_field_manager
   *   The entity field manager service.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, EntityFieldManagerInterface $entity_field_manager) {
    $this->entityTypeManager = $entity_type_manager;
    $this->entityFieldManager = $entity_field_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new self(
      $container->get('entity_type.manager'),
      $container->get('entity_field.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'custom_article_form';
  }

  /**
   * {@inheritdoc}
   */
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

  /**
   * {@inheritdoc}
   */
  public function updateFieldOptions(array &$form, FormStateInterface $form_state) {
    return $form['field_wrapper'];
  }

  /**
   * AJAX callback to update field options based on selected content type.
   */
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

  /**
   * Gets the translatable fields for a given content type.
   *
   * @param string $content_type
   *   The content type machine name.
   *
   * @return array
   *   An array of translatable field options.
   */
  protected function getContentFields($content_type) {
    $fields = $this->entityFieldManager->getFieldDefinitions('node', $content_type);
    $options = [];
    foreach ($fields as $field_name => $field_definition) {
      if ($field_definition->isTranslatable()) {
        if ($field_name == 'field_keywords' || $field_name == 'field_related_articles') {
          $options[$field_name] = $field_definition->getLabel();
        }

      }
    }
    return $options;
  }

  /**
   * Batch operation callback to process copying field values to translations.
   *
   * @param string $content_type
   *   The content type machine name.
   * @param string $field_name
   *   The field name to copy.
   * @param array $context
   *   The batch context array.
   */
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

    // Define the size of the batch.
    $batch_size = 5;
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
    }
    else {
      $context['finished'] = 1;
    }
  }

  /**
   * Batch finished callback.
   *
   * @param bool $success
   *   Whether the batch completed successfully.
   * @param array $results
   *   The results array.
   * @param array $operations
   *   The operations array.
   */
  public static function batchFinished($success, $results, $operations) {
    if ($success) {
      \Drupal::messenger()->addMessage(t('Field values have been successfully copied to translations.'));
    }
    else {
      \Drupal::messenger()->addMessage(t('An error occurred during the process.'));
    }
  }

}
