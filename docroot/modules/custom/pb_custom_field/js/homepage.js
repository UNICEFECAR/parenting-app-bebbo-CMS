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


jQuery(window).on('scroll', function() {
  jQuery('.scroll-menu').each(function() {
      if(jQuery(window).scrollTop() >= jQuery(this).position().top) {
          var id = jQuery(this).attr('id');
          jQuery('#block-mainnavigation .menu-item a').removeClass('is-active');
          if (typeof id !==  "undefined") {
            jQuery("#block-mainnavigation .menu-item").find('a[href="https://staging.bebbo.app/#'+ id +'"]').addClass('is-active');
          }
      }
  });
});

jQuery(document).ready(function() {
  jQuery("#block-mainnavigation .menu-item").on('click', 'a', function () {
      var id = jQuery(this).attr('href');
      jQuery('#block-mainnavigation li a.is-active').removeClass("is-active");
    jQuery('#block-mainnavigation .menu-item').find('a[href="'+id+'"]').addClass('is-active');
  });
});