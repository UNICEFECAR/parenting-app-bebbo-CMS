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
