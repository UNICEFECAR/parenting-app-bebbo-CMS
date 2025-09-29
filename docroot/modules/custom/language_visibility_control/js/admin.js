(function ($, Drupal) {
  'use strict';

  /**
   * Dynamic language visibility controls.
   */
  Drupal.behaviors.languageVisibilityControl = {
    attach: function (context) {
      var $context = $(context);
      
      // Watch for changes in the language field (both single and multiple select)
      $context.find('select[name*="field_language"]').once('language-visibility').on('change', function() {
        updateVisibilityOptions();
      });
      
      // Also watch for changes in multi-value language fields
      $context.find('input[name*="field_language"]').once('language-visibility').on('change', function() {
        updateVisibilityOptions();
      });
      
      function updateVisibilityOptions() {
        var selectedLanguages = [];
        
        // Get selected languages from select field(s)
        $('select[name*="field_language"] option:selected').each(function() {
          var val = $(this).val();
          if (val && val !== '_none' && val !== '') {
            selectedLanguages.push(val);
          }
        });
        
        // Also check for multi-value text inputs (if using a different widget)
        $('input[name*="field_language"]:checked').each(function() {
          var val = $(this).val();
          if (val && val !== '_none' && val !== '') {
            selectedLanguages.push(val);
          }
        });
        
        console.log('Selected languages:', selectedLanguages);
        
        // Update visibility checkboxes based on selected languages
        var $visibilityFieldset = $('[data-drupal-selector="edit-language-visibility"]');
        if ($visibilityFieldset.length > 0) {
          var $helpText = $visibilityFieldset.find('.language-visibility-help');
          
          if (selectedLanguages.length === 0) {
            // Show help text when no languages selected
            if ($helpText.length === 0) {
              $visibilityFieldset.append('<div class="language-visibility-help"><em>Select languages above to configure their visibility in the mobile app.</em></div>');
            }
            // Hide all checkboxes
            $visibilityFieldset.find('input[type="checkbox"]').closest('.form-item').hide();
          } else {
            // Remove help text
            $helpText.remove();
            
            // Show/hide checkboxes based on selected languages
            $visibilityFieldset.find('input[type="checkbox"]').each(function() {
              var $checkbox = $(this);
              var nameAttr = $checkbox.attr('name');
              
              if (nameAttr) {
                var matches = nameAttr.match(/\[([^\]]+)\]/);
                if (matches && matches[1]) {
                  var langcode = matches[1];
                  var $formItem = $checkbox.closest('.form-item');
                  
                  if (selectedLanguages.indexOf(langcode) !== -1) {
                    $formItem.show();
                  } else {
                    $formItem.hide();
                    $checkbox.prop('checked', false);
                  }
                }
              }
            });
          }
        }
      }
      
      // Initial update on page load
      setTimeout(updateVisibilityOptions, 100);
      
      // Also update when the form is rebuilt (AJAX)
      $(document).on('ajaxComplete', function() {
        setTimeout(updateVisibilityOptions, 100);
      });
    }
  };

})(jQuery, Drupal);