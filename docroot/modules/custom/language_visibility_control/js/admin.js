(function ($, Drupal, once) {
    'use strict';

    /**
     * Dynamic language visibility controls.
     */
    Drupal.behaviors.languageVisibilityControl = {
        attach: function (context) {

            // Inject small CSS tweak to align dynamically created checkboxes
            if (!document.getElementById('language-visibility-control-inline-css')) {
                var css = '\n' +
                    +
                    '[data-drupal-selector="edit-language-visibility"] .form-item.form-type-checkbox,\n' +
                    '[data-drupal-selector="edit-language-visibility"] .form-item.form-type--checkbox {\n' +
                    '  padding-left: 1.25rem;' +
                    '}\n' +
                    '[data-drupal-selector="edit-language-visibility"] .form-item.form-type-checkbox label,\n' +
                    '[data-drupal-selector="edit-language-visibility"] .form-item.form-type--checkbox label {\n' +
                    '  display: inline-block;\n' +
                    '  margin-left: 0.25rem;\n' +
                    '}\n';

                css += '\n' +
                    '/* Newly created checkbox item gets extra left spacing and a larger checkbox plus subtle highlight. */\n' +
                    '[data-drupal-selector="edit-language-visibility"] .language-visibility-new {\n' +
                    '  padding-left: 1.5rem !important;\n' +
                    '  margin-bottom: 0.25rem;\n' +
                    '}\n';

                var style = document.createElement('style');
                style.id = 'language-visibility-control-inline-css';
                style.type = 'text/css';
                if (style.styleSheet) {
                    style.styleSheet.cssText = css;
                } else {
                    style.appendChild(document.createTextNode(css));
                }
                document.getElementsByTagName('head')[0].appendChild(style);
            }

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
                        $visibilityFieldset.find('.form-item').each(function () {
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
                $fieldset.find('.form-item').each(function () {
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
            function escapeHtml(str) {
                if (typeof str !== 'string') return '';
                return str
                    .replace(/&/g, '&amp;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;')
                    .replace(/"/g, '&quot;')
                    .replace(/'/g, '&#39;');
            }

            function createLanguageCheckbox(langcode, $fieldset) {
                var languageName = getLanguageName(langcode) || langcode;

                // Prefer cloning an existing checkbox element so we keep exact markup,
                // classes and any admin-theme wrappers that control size/position.
                var $prototype = $fieldset.find('.form-item:has(input.form-checkbox)').first();
                if ($prototype.length) {
                    var $clone = $prototype.clone(true, true);
                    var $input = $clone.find('input.form-checkbox').first();
                    var $label = $clone.find('label').first();

                    $input.attr('id', 'edit-language-visibility-' + langcode)
                        .attr('name', 'language_visibility[' + langcode + ']')
                        .val('1')
                        .prop('checked', false);

                    if ($label.length) {
                        $label.attr('for', 'edit-language-visibility-' + langcode).text(languageName + ' (' + langcode + ')');
                    }

                    $clone.addClass('language-visibility-new');
                    $fieldset.append($clone);
                }
                else {
                    // Fallback to creating a standard markup if no prototype is available.
                    var escLangcode = escapeHtml(langcode);
                    var escLanguageName = escapeHtml(languageName);
                    var checkboxHtml = '<div class="js-form-item form-item js-form-type-checkbox form-type--checkbox form-type--boolean form-item-language-visibility-' + escLangcode + ' language-visibility-new">' +
                        '<input type="checkbox" id="edit-language-visibility-' + escLangcode + '" ' +
                        'name="language_visibility[' + escLangcode + ']" value="1" class="form-checkbox">' +
                        '<label class="option" for="edit-language-visibility-' + escLangcode + '">' +
                        escLanguageName + ' (' + escLangcode + ')' +
                        '</label>' +
                        '</div>';

                    $fieldset.append(checkboxHtml);
                }
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
            setTimeout(function () {
                updateVisibilityOptions();
            }, 100);

            // Also update when the form is rebuilt (AJAX)
            $(document).on('ajaxComplete', function () {
                setTimeout(function () {
                    updateVisibilityOptions();
                }, 100);
            });
        }
    };

})(jQuery, Drupal, once);
