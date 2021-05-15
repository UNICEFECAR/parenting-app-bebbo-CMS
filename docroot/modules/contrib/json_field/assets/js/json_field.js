/**
 * @file
 * Attaches behavior for the JSON FIELD module.
 */

(function ($, Drupal, drupalSettings) {

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
      $(context).find('.field.field--type-json.field__item').once('json-field-init').each(function () {
        $(this).JSONView($(this).find('pre code').text());
      });
    }
  };

})(jQuery, Drupal, drupalSettings);
