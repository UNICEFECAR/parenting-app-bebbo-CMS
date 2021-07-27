(function ($, Drupal) {

  "use strict";

  Drupal.behaviors.allowedLanguagesUser = {
    attach: function (context) {
      $('#edit-allowed-languages', context)
        .once('allowedLanguagesUser')
        .each(function (index, element) {
          var checkAll = $('#edit-allowed-languages-languages-all', element);
          var checkboxes = $('input', element).not(checkAll);

          // When the check all checkbox value changes make sure to
          // check/un-check all checkboxes.
          checkAll.on('change', function () {
            var $element = $(this);
            checkboxes.prop('checked', $element.is(':checked'));
          });

          // When checkboxes are checked make sure to automatically check the all
          // checkbox when all checkboxes are checked.
          checkboxes.on('change', function () {
            var shouldBeChecked = checkboxes.filter(':checked').length === checkboxes.length;
            checkAll.prop('checked', shouldBeChecked);
          });
        });
    },
  };
})(jQuery, Drupal);
