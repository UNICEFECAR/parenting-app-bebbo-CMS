/**
 * @file
 * Custom JS for the JSON field WYSIWYG-style widget.
 */

(function ($, Drupal, drupalSettings) {
  'use strict';

  function parseJson(string) {
    try {
      return JSON.parse(string);
    }
    catch (e) {
      return null;
    }
  }

  /**
   * Attach behavior for JSON Fields.
   *
   * @type {Drupal~behavior}
   */
  Drupal.behaviors.json_widget = {
    attach(context) {
      $(once('json-editor', '[data-json-editor]', context))
        .each(function (index, element) {
          var $textarea = $(element);
          var hash = $textarea.attr('data-json-editor');
          var options = drupalSettings.json_field[hash];
          var data = $textarea.val();
          var json = parseJson(data);

          if (options || data) {
            var $editor = $(Drupal.theme('jsonEditorWrapper', 'json-editor-' + $textarea.attr('name')));
            $textarea.addClass('js-hide');
            $textarea.after($editor);

            var instanceOptions = {
              // Copy the data as-is in the textarea regardless of the
              // validity of the JSON.
              onChange: function () {
                $textarea.text(jsonEditor.getText());
              }
            };
            if (options.schema) {
              instanceOptions.schema = parseJson(options.schema);
            }

            var jsonEditor = new JSONEditor($editor[0], Object.assign({}, options, instanceOptions));
            if (json) {
              jsonEditor.set(json);
            }
            else {
              jsonEditor.setText(data);
            }
          }
        });
    }
  };

  Drupal.theme.jsonEditorWrapper = function (id) {
    return '<div style="width:100%;height:500px" id="' + id + '"></div>';
  }

})(jQuery, Drupal, drupalSettings);
