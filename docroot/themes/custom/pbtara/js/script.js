(function ($, Drupal) {
  "use strict";
  Drupal.behaviors.bebboTaraTheme = {
    attach: function (context, settings) {
      console.log("Bebbo Tara Theme JS Loaded!");
      var block1Empty = $('.views-element-container .view-related-views.view-display-id-block_1 .view-content').html().trim() === "";
      var block2Empty = $('.views-element-container .view-related-views.view-display-id-block_2 .view-content').html().trim() === "";

      if (block1Empty && block2Empty) {
        $('#block-pbtara-views-block-taxonomy-term-display-category-block').show();
      } else {
        console.log('Not empty');
        $('#block-pbtara-views-block-taxonomy-term-display-category-block').hide();
      }
    }
  };
})(jQuery, Drupal);
