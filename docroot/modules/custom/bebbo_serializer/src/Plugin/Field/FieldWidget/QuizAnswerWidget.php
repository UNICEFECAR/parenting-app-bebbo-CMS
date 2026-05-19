<?php

namespace Drupal\bebbo_serializer\Plugin\Field\FieldWidget;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Field\Attribute\FieldWidget;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Widget for the 'quiz_answer' field type.
 *
 * Renders a textarea for answer text and a checkbox for marking
 * the answer as correct.
 */
#[FieldWidget(
  id: "quiz_answer_widget",
  label: new TranslatableMarkup("Quiz Answer (textarea + checkbox)"),
  field_types: ["quiz_answer"],
)]
class QuizAnswerWidget extends WidgetBase {

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings(): array {
    return [
      'rows' => 3,
      'placeholder' => '',
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state): array {
    $element['rows'] = [
      '#type' => 'number',
      '#title' => $this->t('Rows'),
      '#default_value' => $this->getSetting('rows'),
      '#required' => TRUE,
      '#min' => 1,
    ];
    $element['placeholder'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Placeholder'),
      '#default_value' => $this->getSetting('placeholder'),
    ];
    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary(): array {
    $summary = [];
    $summary[] = $this->t('Number of rows: @rows', ['@rows' => $this->getSetting('rows')]);
    $placeholder = $this->getSetting('placeholder');
    if (!empty($placeholder)) {
      $summary[] = $this->t('Placeholder: @placeholder', ['@placeholder' => $placeholder]);
    }
    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state): array {
    $element['value'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Answer'),
      '#default_value' => $items[$delta]->value ?? '',
      '#rows' => $this->getSetting('rows'),
      '#placeholder' => $this->getSetting('placeholder'),
    ];

    $element['is_correct'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Correct answer'),
      '#default_value' => $items[$delta]->is_correct ?? 0,
    ];

    if ($delta === 0) {
      $element['#element_validate'][] = [static::class, 'validateCorrectAnswer'];
    }

    return $element;
  }

  /**
   * Validates answer correctness rules per question type.
   */
  public static function validateCorrectAnswer(array $element, FormStateInterface $form_state): void {
    $field_parents = array_slice($element['#parents'], 0, -1);
    $values = NestedArray::getValue($form_state->getValues(), $field_parents);

    if (!is_array($values)) {
      return;
    }

    $has_answer = FALSE;
    $correct_count = 0;

    foreach ($values as $delta => $item) {
      if (!is_numeric($delta)) {
        continue;
      }
      if (trim($item['value'] ?? '') !== '') {
        $has_answer = TRUE;
        if (!empty($item['is_correct'])) {
          $correct_count++;
        }
      }
    }

    if ($has_answer && $correct_count === 0) {
      $form_state->setError($element, t('At least one answer must be marked as correct.'));
    }

    if ($has_answer && $correct_count > 1) {
      $question_type = static::getQuestionType($element['#parents'], $form_state);
      if ($question_type === 'true_or_false') {
        $form_state->setError($element, t('True or False questions can have only one correct answer.'));
      }
    }
  }

  /**
   * Resolves the question type from the parent IEF form values.
   */
  protected static function getQuestionType(array $parents, FormStateInterface $form_state): ?string {
    // Parents: [..., 'field_answers', 0, 'value'] or [..., 'field_answers', 0].
    // Walk up to find the IEF entity level (above 'field_answers').
    $ief_parents = [];
    foreach ($parents as $i => $key) {
      if ($key === 'field_answers') {
        $ief_parents = array_slice($parents, 0, $i);
        break;
      }
    }

    if (empty($ief_parents)) {
      return NULL;
    }

    $type_parents = array_merge($ief_parents, ['field_question_type', 0, 'value']);
    return NestedArray::getValue($form_state->getValues(), $type_parents);
  }

}
