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
        });
    }
  };

})(jQuery, Drupal, once);
