(function ($, Drupal, once) {
  'use strict';

  Drupal.behaviors.bebboSerializerCourseExpiry = {
    attach: function (context) {
      once('course-expiry-min', 'input[name="field_course_expiry[0][value][date]"]', context)
        .forEach(function (el) {
          var today = new Date();
          var yyyy = today.getFullYear();
          var mm = String(today.getMonth() + 1).padStart(2, '0');
          var dd = String(today.getDate() + 1).padStart(2, '0');
          el.setAttribute('min', yyyy + '-' + mm + '-' + dd);

          // A course expires at the end of its last day, so the time follows
          // the date rather than being chosen alongside it.
          var time = el.form.querySelector('input[name="field_course_expiry[0][value][time]"]');
          if (!time) {
            return;
          }

          var setEndOfDay = function () {
            time.value = '23:59:59';
            time.dispatchEvent(new Event('change', { bubbles: true }));
          };

          // Setting a date re-schedules expiry to the end of that day, whatever
          // the time was before. Clearing the date must not leave a stray time
          // behind: a time with no date fails validation.
          el.addEventListener('change', function () {
            if (el.value) {
              setEndOfDay();
            }
          });

          // Emptying the time is the way to ask for end of day explicitly.
          time.addEventListener('blur', function () {
            if (el.value && !time.value) {
              setEndOfDay();
            }
          });
        });
    }
  };

})(jQuery, Drupal, once);
