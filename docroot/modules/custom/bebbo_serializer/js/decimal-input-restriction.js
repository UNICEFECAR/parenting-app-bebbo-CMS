(function ($, Drupal, once) {
  'use strict';

  Drupal.behaviors.bebboDecimalInputRestriction = {
    attach: function (context) {
      var selectors = [
        'input[name="field_average_height[0][value]"]',
        'input[name="field_average_weight[0][value]"]',
      ].join(', ');

      once('decimal-restriction', selectors, context).forEach(function (el) {
        el.addEventListener('input', function () {
          var val = el.value;
          if (val === '' || val === '-') {
            return;
          }
          var dotIndex = val.indexOf('.');
          if (dotIndex !== -1 && val.length - dotIndex - 1 > 2) {
            el.value = parseFloat(val).toFixed(2);
          }
        });
      });
    }
  };

})(jQuery, Drupal, once);
