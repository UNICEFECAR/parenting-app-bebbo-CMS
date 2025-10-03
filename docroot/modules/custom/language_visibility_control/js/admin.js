(function ($, Drupal, once) {
  'use strict';

  /**
   * Dynamic language visibility controls.
   */
  Drupal.behaviors.languageVisibilityControl = {
    attach: function (context) {

      // Watch for changes in the language field (both single and multiple select)
      once('language-visibility', 'select[name*="field_language"]', context).forEach(function (element) {
        $(element).on('change', function () {
          updateVisibilityOptions();
        });
      });

      // Also watch for changes in multi-value language fields
      once('language-visibility-input', 'input[name*="field_language"]', context).forEach(function (element) {
        $(element).on('change', function () {
          updateVisibilityOptions();
        });
      });

      /**
       * Updates visibility options based on selected languages.
       */
      function updateVisibilityOptions() {
        var selectedLanguages = getSelectedLanguages();
        var $visibilityFieldset = $('[data-drupal-selector="edit-language-visibility"]');

        if ($visibilityFieldset.length > 0) {
          // Always ensure help messages and descriptions are visible
          $visibilityFieldset.find('.language-visibility-help').show();
          $visibilityFieldset.find('.language-visibility-description').show();
          $visibilityFieldset.find('.form-item em').parent().show();

          if (selectedLanguages.length === 0) {
            // Hide all checkboxes but keep help messages and descriptions visible
            $visibilityFieldset.find('.form-item').each(function() {
              var $item = $(this);
              if (!$item.find('.language-visibility-help').length &&
                  !$item.find('em').length &&
                  !$item.find('.language-visibility-description').length) {
                $item.hide();
              } else {
                $item.show();
              }
            });
          } else {
            createVisibilityCheckboxes(selectedLanguages, $visibilityFieldset);
          }
        }
      }

      /**
       * Gets currently selected languages from form fields.
       */
      function getSelectedLanguages() {
        var selectedLanguages = [];

        // Get selected languages from select field(s)
        $('select[name*="field_language"] option:selected').each(function () {
          var val = $(this).val();
          if (val && val !== '_none' && val !== '') {
            selectedLanguages.push(val);
          }
        });

        // Also check for multi-value text inputs (if using a different widget)
        $('input[name*="field_language"]:checked').each(function () {
          var val = $(this).val();
          if (val && val !== '_none' && val !== '') {
            selectedLanguages.push(val);
          }
        });

        return selectedLanguages;
      }

      /**
       * Creates or shows visibility checkboxes for selected languages.
       */
      function createVisibilityCheckboxes(selectedLanguages, $fieldset) {
        // Always ensure description elements are visible
        $fieldset.find('.language-visibility-description').show();
        $fieldset.find('.language-visibility-checkbox-description').show();

        // First, hide all existing checkboxes but keep help messages and descriptions visible
        $fieldset.find('.form-item').each(function() {
          var $item = $(this);
          if (!$item.find('.language-visibility-help').length &&
              !$item.find('em').length &&
              !$item.find('.language-visibility-description').length &&
              !$item.find('.language-visibility-checkbox-description').length) {
            $item.hide();
          } else {
            $item.show();
          }
        });

        // For each selected language, show or create a checkbox and its description
        selectedLanguages.forEach(function (langcode) {
          var $existingCheckbox = $fieldset.find('input[name="language_visibility[' + langcode + ']"]');

          if ($existingCheckbox.length > 0) {
            // Show existing checkbox and its description
            $existingCheckbox.closest('.form-item').show();
            $fieldset.find('[data-drupal-selector*="' + langcode + '-description"]').show();
          } else {
            // Create new checkbox dynamically
            createLanguageCheckbox(langcode, $fieldset);
          }
        });

        // Uncheck and hide checkboxes for unselected languages
        $fieldset.find('input[type="checkbox"]').each(function () {
          var $checkbox = $(this);
          var nameAttr = $checkbox.attr('name');

          if (nameAttr) {
            var matches = nameAttr.match(/language_visibility\[([^\]]+)\]/);
            if (matches && matches[1]) {
              var langcode = matches[1];
              var $formItem = $checkbox.closest('.form-item');

              if (selectedLanguages.indexOf(langcode) === -1) {
                $formItem.hide();
                $checkbox.prop('checked', false);
                // Also hide the description for this language
                $fieldset.find('[data-drupal-selector*="' + langcode + '-description"]').hide();
              }
            }
          }
        });
      }

      /**
       * Creates a new language checkbox element.
       */
      function createLanguageCheckbox(langcode, $fieldset) {
        var languageName = getLanguageName(langcode) || langcode;

        var checkboxHtml = '<div class="form-item form-type-checkbox form-item-language-visibility-' + langcode + '">' +
          '<input type="checkbox" id="edit-language-visibility-' + langcode + '" ' +
          'name="language_visibility[' + langcode + ']" value="1" class="form-checkbox">' +
          '<label class="option" for="edit-language-visibility-' + langcode + '">' +
          languageName + ' (' + langcode + ')' +
          '</label>' +
          '</div>';

        $fieldset.append(checkboxHtml);
      }



      /**
       * Gets language name from Drupal settings.
       */
      function getLanguageName(langcode) {
        var availableLanguages = drupalSettings.languageVisibilityControl ?
          drupalSettings.languageVisibilityControl.availableLanguages : {};
        return availableLanguages[langcode];
      }

      // Initial update on page load
      setTimeout(function() {
        updateVisibilityOptions();
      }, 100);

      // Also update when the form is rebuilt (AJAX)
      $(document).on('ajaxComplete', function () {
        setTimeout(function() {
          updateVisibilityOptions();
        }, 100);
      });
    }
  };

})(jQuery, Drupal, once);
