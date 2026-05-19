(function ($, Drupal, drupalSettings, once) {
  'use strict';

  var SINGLE_QUESTION = (drupalSettings.bebboQuizType && drupalSettings.bebboQuizType.singleQuestionValue) || 'single_question_quiz';

  // Tracks whether an auto-removal AJAX cycle is in progress.
  var removing = false;
  // Tracks whether we're waiting for the IEF confirm dialog to appear.
  var pendingConfirm = false;

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
   * Toggle IEF add/duplicate controls based on quiz type.
   *
   * When switching to single_question_quiz with >1 questions, auto-removes
   * extra entities one at a time via IEF's own AJAX remove flow.
   */
  function toggleAddControls() {
    var $wrapper = getWrapper();
    if (!$wrapper.length) {
      return;
    }

    var isSingle = getQuizType() === SINGLE_QUESTION;

    // Hide/show the "Add new Quiz Question" button.
    $wrapper.find('input[name*="-add"]').toggle(!isSingle);

    // Hide/show duplicate buttons in entity operation cells.
    $wrapper.find('.ief-entity-operations input[name*="entity-duplicate"]').toggle(!isSingle);

    // Auto-remove extra entities for single question quiz.
    if (isSingle && !removing && !pendingConfirm && getQuestionCount() > 1) {
      removeLastEntity($wrapper);
    }
  }

  /**
   * Triggers the IEF remove button on the last entity row.
   *
   * IEF removal is 2-step: click Remove -> confirm dialog appears ->
   * click Confirm -> entity removed. Each step triggers an AJAX rebuild,
   * which fires Drupal.behaviors.attach again.
   */
  function removeLastEntity($wrapper) {
    var $removeButtons = $wrapper.find('.ief-entity-operations input[name*="entity-remove"]');
    if ($removeButtons.length <= 1) {
      return;
    }

    removing = true;
    pendingConfirm = true;
    $removeButtons.last().trigger('mousedown');
  }

  /**
   * Handles step 2 of IEF removal: clicking the confirm button.
   */
  function handlePendingConfirm($wrapper) {
    var $confirmBtn = $wrapper.find('input[name*="ief-remove-confirm"]');
    if ($confirmBtn.length) {
      pendingConfirm = false;
      removing = true;
      $confirmBtn.first().trigger('mousedown');
      return true;
    }
    return false;
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
      // Reset removal flag on every attach (AJAX rebuild completed).
      removing = false;

      var $wrapper = getWrapper();

      // Step 2 of auto-removal: confirm dialog appeared, click confirm.
      if (pendingConfirm && $wrapper.length) {
        if (handlePendingConfirm($wrapper)) {
          return;
        }
      }

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
