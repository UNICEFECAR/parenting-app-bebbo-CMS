/**
 * @file
 * Custom JS for the JSON Field formatter.
 */

(function ($, Drupal, drupalSettings, once) {
  'use strict';

  var options = $.extend(drupalSettings.json_field,
    // Merge strings on top of drupalSettings so that they are not mutable.
    {
      strings: {
        quickEdit: Drupal.t('Quick edit')
      }
    }
  );

  /**
   * Attach behavior for JSON Fields.
   *
   * @type {Drupal~behavior}
   */
  Drupal.behaviors.json_field = {
    attach: function (context) {
      // Initialize the Quick Edit app once per page load.
      $(once('json-field-init', 'pre.json-field', context)).each(function () {
        $(this).parent().JSONView($(this).parent().find('pre code').text());
      });
    }
  };

})(jQuery, Drupal, drupalSettings, once);
