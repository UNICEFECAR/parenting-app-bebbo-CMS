jQuery(document).ready(function() {
  jQuery('.pb-main-homepage .navbar-toggle').click(function() {
        jQuery(".region-header").toggleClass("headerbg");
    });
  if(jQuery('input[name="field_make_available_for_mobile[value]"]').is(':checked'))
    {
      jQuery( "<p>Please note this action can not be undone. Make sure you have requested your IT team for backup of the database and disabled all Country users.</p>" ).insertAfter(".field--name-field-make-available-for-mobile");
}
  // Help text for country listing page
  jQuery( "<p>We recommend to select 10-15 content for all bulk operations for best performance.</p>" ).insertAfter("#views-form-country-content-listing-page-5 .tableresponsive-toggle-columns");
});
 // for offload country process js
 jQuery('.field--name-field-make-available-for-mobile').click(function() {
    if(jQuery('input[name="field_make_available_for_mobile[value]"]').is(':checked'))
    {
      jQuery( "<p>Please note this action can not be undone. Make sure you have requested your IT team for backup of the database and disabled all Country users.</p>" ).insertAfter(".field--name-field-make-available-for-mobile");
}else
{
jQuery('.field--name-field-make-available-for-mobile').nextAll('p').remove();     

}
});


jQuery(document).ready(function() {
  var menuLinks = jQuery("#block-mainnavigation .menu-item a");
  
  var scrollOffset = -250;

  jQuery(window).on('scroll', function() {
    var currentScroll = jQuery(window).scrollTop();
    
    jQuery('.scroll-menu').each(function() {
      if (currentScroll >= jQuery(this).position().top + scrollOffset) {
        var id = jQuery(this).attr('id');
        menuLinks.removeClass('is-active');
        if (typeof id !== "undefined") {
          var correspondingLink = menuLinks.filter('[href="https://www.bebbo.app/#' + id + '"]');
          correspondingLink.addClass('is-active');
        }
      }
    });
  });

  menuLinks.on('click', function() {
    menuLinks.removeClass('is-active');
    jQuery(this).addClass('is-active');
  });
});


jQuery(document).ready(function() {
  var clickTimer = null;
  var clickDelay = 10; // Milliseconds delay for double-click emulation

  jQuery("#block-mainnavigation .menu-item").on('click', 'a', function () {
    var clickedElement = jQuery(this);

    if (clickTimer === null) {
      // First click
      clickTimer = setTimeout(function() {
        // Single click action
        var id = clickedElement.attr('href');
        jQuery('#block-mainnavigation li a.is-active').removeClass("is-active");
        jQuery('#block-mainnavigation .menu-item').find('a[href="'+id+'"]').addClass('is-active');

        clickTimer = null; // Reset the timer
      }, clickDelay);
    } else {
      // Second click (double click)
      clearTimeout(clickTimer);
      clickTimer = null;

      // Double click action
      // Put your double-click behavior here
    }
  });
});


  