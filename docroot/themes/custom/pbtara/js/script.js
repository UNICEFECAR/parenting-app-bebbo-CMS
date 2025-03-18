(function ($, Drupal) {
  "use strict";
  Drupal.behaviors.bebboTaraTheme = {
    attach: function (context, settings) {
      console.log("Bebbo Tara Theme JS Loaded!");
      var block1Empty = $('.views-element-container .view-related-views.view-display-id-block_1 .view-content').html().trim() === "";
      var block2Empty = $('.views-element-container .view-related-views.view-display-id-block_2 .view-content').html().trim() === "";

      if (block1Empty && block2Empty) {
        $('.view-display-id-category_block').show();
      } else {
        console.log('Not empty');
      }
    }
  };
})(jQuery, Drupal);
