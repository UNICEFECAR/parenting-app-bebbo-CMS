# Pb_custom_field:

ABOUT
------
This module also defines
 the Actions required in the Country Listing and Global Listing page.
 -> Assign Content Action
 -> Move From Publish to Draft Action
 -> Move From Publish to Senior Editor Action
 -> Move From Publish to SME Review Action

The alters in this module include:
 -> allowed languages as multi-select in create country page
 -> manage languages in media popup
 -> manage languages in individual media page
 -> making australian content and mandatory content as read only
 -> managing languages in user creation pages
 -> managing languages in country content listing page
 -> managing languages in user listing page
 -> manage language validation create, edit content pages
 -> field link alter for dashboard and to make listing page menus dynamic, basis user language
 -> Country user management
 -> Login form alter for forgot password
 -> Content moderation mail notifications


PRE-REQUIREMENTS
------------------
This module requires or refers
Module:
  -> Group
  -> Group Node
  -> Allowed Languages
   -> Content Moderation Notifications

Specific for Parent Buddy Project

CONFIGURATIONS
------------
View:
  -> content-listing
  -> country-content-listing
  -> global-content-list

Form ids:
User-register-form
User-form
Views-exposed-form-content-listing-page-1
Views-exposed-form-country-content-listing-page-5
Group-content-country-group-membership-add-form
Group-content-country-group-membership-edit-form
Views-exposed-form-user-admin-people-page-2
Media-image-add-form
Media-image-edit-form
Media-video-add-form
Media-remote-video-add-form
Media-video-edit-form
Media-remote-video-edit-form
Lang-dropdown-form
content-moderation-entity-moderation-form

