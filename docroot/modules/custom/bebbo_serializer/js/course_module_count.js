(function ($, Drupal) {
  'use strict';

  function getModuleCount() {
    var $wrapper = $('[data-drupal-selector="edit-field-course-modules-wrapper"]');
    if (!$wrapper.length) {
      return 0;
    }
    return $wrapper.find('.ief-entity-table tbody tr.ief-row-entity').length;
  }

  function updateModuleCount() {
    var count = getModuleCount();
    var $input = $('[data-drupal-selector="edit-field-number-of-modules-0-value"]');
    if ($input.length) {
      $input.val(count);
    }
  }

  Drupal.behaviors.bebboSerializerModuleCount = {
    attach: function () {
      updateModuleCount();
    }
  };

})(jQuery, Drupal);
