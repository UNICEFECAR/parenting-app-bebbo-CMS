(function ($, Drupal) {
    "use strict";
    Drupal.behaviors.bebboTaraTheme = {
        attach: function (context, settings) {
            // console.log("Bebbo Tara Theme JS Loaded!");

            function closeBanner() {
                document.getElementById("app-banner").style.display = "none";
            }
        }
    };
})(jQuery, Drupal);
