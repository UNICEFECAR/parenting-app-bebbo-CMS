(function ($, Drupal, drupalSettings, once) {
  'use strict';

  var SINGLE_QUESTION = (drupalSettings.bebboQuizType && drupalSettings.bebboQuizType.singleQuestionValue) || 'single_question_quiz';

  function getWrapper() {
    return $('[data-drupal-selector="edit-field-quiz-questions-wrapper"]');
  }

  function getQuestionCount() {
    var $wrapper = getWrapper();
    if (!$wrapper.length) {
      return 0;
    }
    return $wrapper.find('.ief-entity-table tbody tr.ief-row-entity').length;
  }

  function getQuizType() {
    var $select = $('select[data-drupal-selector="edit-field-quiz-type"]');
    return $select.length ? $select.val() : '';
  }

  /**
   * Toggle IEF add/duplicate controls and the single-question error.
   *
   * When the quiz type is single_question_quiz with >1 questions, shows an
   * inline error and lets the user remove the extras manually. Nothing is
   * auto-removed, and the quiz-type selection is left unchanged.
   */
  function toggleAddControls() {
    var $wrapper = getWrapper();
    if (!$wrapper.length) {
      return;
    }

    var isSingle = getQuizType() === SINGLE_QUESTION;
    var count = getQuestionCount();

    // Show the "Add new Quiz Question" button unless this is a single question
    // quiz that already has its one question. A single quiz with no question
    // yet still needs the button to add that one required question.
    $wrapper.find('input[name*="-add"]').toggle(!isSingle || count === 0);

    // Hide/show duplicate buttons in entity operation cells.
    $wrapper.find('.ief-entity-operations input[name*="entity-duplicate"]').toggle(!isSingle);

    // Do NOT auto-remove. Surface an inline error and let the user remove the
    // extra questions manually; leave the quiz-type value as the user set it.
    if (isSingle && count > 1) {
      showSingleQuestionError($wrapper);
    }
    else {
      clearSingleQuestionError($wrapper);
    }
  }

  /**
   * Shows the inline "too many questions for a single quiz" error.
   */
  function showSingleQuestionError($wrapper) {
    var message = Drupal.t('To change the quiz type to “Single question quiz,” the “Questions” section must contain only one question. Please remove all additional questions before changing to this quiz type.');
    var $error = $wrapper.find('.single-question-error');
    if (!$error.length) {
      $wrapper.prepend('<div class="single-question-error messages messages--error" style="margin-bottom:10px;"></div>');
      $error = $wrapper.find('.single-question-error');
    }
    $error.text(message);
  }

  /**
   * Removes the inline single-question error when the condition clears.
   */
  function clearSingleQuestionError($wrapper) {
    $wrapper.find('.single-question-error').remove();
  }

  function updateQuestionCount() {
    var count = getQuestionCount();

    // Update the number of questions field.
    var $countInput = $('[data-drupal-selector="edit-field-number-of-questions-0-value"]');
    if ($countInput.length) {
      $countInput.val(count);
    }

    // Update passing score max attribute and help text.
    var $scoreInput = $('[data-drupal-selector="edit-field-passing-score-0-value"]');
    if ($scoreInput.length) {
      $scoreInput.attr('max', count);

      var $description = $scoreInput.closest('.form-item').find('.description, .form-item__description');
      if ($description.length) {
        $description.text('The passing score out of ' + count);
      }

      validatePassingScore($scoreInput, count);
    }
  }

  function validatePassingScore($scoreInput, count) {
    var currentVal = parseInt($scoreInput.val(), 10);
    var $error = $scoreInput.closest('.form-item').find('.passing-score-error');

    if (currentVal > count && count > 0) {
      var message = 'Passing score cannot exceed ' + count + ' (total questions).';
      if (!$error.length) {
        $scoreInput.after(
          '<div class="passing-score-error messages messages--error" style="margin-top:5px;">' + message + '</div>'
        );
      }
      else {
        $error.text(message);
      }
    }
    else {
      $error.remove();
    }
  }

  Drupal.behaviors.bebboSerializerQuestionCount = {
    attach: function (context) {
      updateQuestionCount();
      toggleAddControls();

      // Bind change listener on quiz type select (once).
      once('quiz-type-toggle', 'select[data-drupal-selector="edit-field-quiz-type"]', context)
        .forEach(function (el) {
          $(el).on('change', function () {
            toggleAddControls();
          });
        });

      // Bind input listener on the passing score field (once).
      once('passing-score-validate', '[data-drupal-selector="edit-field-passing-score-0-value"]', context)
        .forEach(function (el) {
          $(el).on('input', function () {
            validatePassingScore($(this), getQuestionCount());
          });
        });

      // Enforce single correct answer for true/false questions inside IEF.
      once('true-false-type', 'select[name*="field_question_type"]', context)
        .forEach(function (el) {
          var $select = $(el);
          enforceSingleCorrect($select);
          $select.on('change', function () {
            enforceSingleCorrect($(this));
          });
        });

      once('true-false-checkbox', 'input[name*="field_answers"][name*="is_correct"]', context)
        .forEach(function (el) {
          $(el).on('change', function () {
            var $cb = $(this);
            if (!$cb.is(':checked')) {
              return;
            }
            var $iefForm = $cb.closest('.ief-form, .inline-entity-form-entity-form');
            if (!$iefForm.length) {
              $iefForm = $cb.closest('form');
            }
            var $typeSelect = $iefForm.find('select[name*="field_question_type"]');
            if ($typeSelect.val() === 'true_or_false') {
              $iefForm.find('input[name*="field_answers"][name*="is_correct"]').not($cb).prop('checked', false);
            }
          });
        });
    }
  };

  function enforceSingleCorrect($select) {
    if ($select.val() !== 'true_or_false') {
      return;
    }
    var $iefForm = $select.closest('.ief-form, .inline-entity-form-entity-form');
    if (!$iefForm.length) {
      $iefForm = $select.closest('form');
    }
    var $checked = $iefForm.find('input[name*="field_answers"][name*="is_correct"]:checked');
    if ($checked.length > 1) {
      $checked.slice(1).prop('checked', false);
    }
  }

})(jQuery, Drupal, drupalSettings, once);
