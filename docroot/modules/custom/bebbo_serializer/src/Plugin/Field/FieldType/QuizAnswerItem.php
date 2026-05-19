<?php

namespace Drupal\bebbo_serializer\Plugin\Field\FieldType;

use Drupal\Core\Field\Attribute\FieldType;
use Drupal\Core\Field\FieldItemBase;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\TypedData\DataDefinition;

/**
 * Defines the 'quiz_answer' field type.
 *
 * Stores answer text together with a boolean flag indicating whether
 * the answer is correct.
 */
#[FieldType(
  id: "quiz_answer",
  label: new TranslatableMarkup("Quiz Answer"),
  description: new TranslatableMarkup("Answer text with a correct answer checkbox."),
  category: "plain_text",
  default_widget: "quiz_answer_widget",
  default_formatter: "quiz_answer_formatter",
)]
class QuizAnswerItem extends FieldItemBase {

  /**
   * {@inheritdoc}
   */
  public static function propertyDefinitions(FieldStorageDefinitionInterface $field_definition): array {
    $properties['value'] = DataDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Answer text'))
      ->setRequired(TRUE);

    $properties['is_correct'] = DataDefinition::create('integer')
      ->setLabel(new TranslatableMarkup('Is correct answer'));

    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  public static function schema(FieldStorageDefinitionInterface $field_definition): array {
    return [
      'columns' => [
        'value' => [
          'type' => 'text',
          'size' => 'big',
        ],
        'is_correct' => [
          'type' => 'int',
          'size' => 'tiny',
          'default' => 0,
        ],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function isEmpty(): bool {
    $value = $this->get('value')->getValue();
    return $value === NULL || $value === '';
  }

}
