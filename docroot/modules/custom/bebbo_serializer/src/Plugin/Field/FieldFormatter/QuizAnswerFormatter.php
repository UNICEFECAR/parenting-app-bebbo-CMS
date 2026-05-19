<?php

namespace Drupal\bebbo_serializer\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\Attribute\FieldFormatter;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Formatter for the 'quiz_answer' field type.
 *
 * Displays answer text with a visual indicator for correct answers.
 */
#[FieldFormatter(
  id: "quiz_answer_formatter",
  label: new TranslatableMarkup("Quiz Answer"),
  field_types: ["quiz_answer"],
)]
class QuizAnswerFormatter extends FormatterBase {

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode): array {
    $elements = [];

    foreach ($items as $delta => $item) {
      $elements[$delta] = [
        '#type' => 'inline_template',
        '#template' => '<div class="quiz-answer {{ is_correct ? "quiz-answer--correct" : "" }}">{{ value }} {% if is_correct %}<strong>({{ correct_label }})</strong>{% endif %}</div>',
        '#context' => [
          'value' => $item->value,
          'is_correct' => (bool) $item->is_correct,
          'correct_label' => $this->t('Correct'),
        ],
      ];
    }

    return $elements;
  }

}
