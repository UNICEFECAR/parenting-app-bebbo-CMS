This directory should contain all custom modules and features.

# Group_country_field:

This is a general module with customization all over the site which include:
Form alter, View Query alter, Views exposed form alter. 
The pages that it will impact include the translation management of all Job items pages, Recently logged in users for Dashboard.

# Pb_custom_field:

This module also defines
 the Actions required in the Country Listing and Global Listing page.
 -> Assign Content Action
 -> Move From Publish to Draft Action
 -> Move From Publish to Senior Editor Action
 -> Move From Publish to SME Review Action 
All Language related alters

# Pb_custom_form:

This module provides Configuration pages for Force Update API Check, Mobile Javascript management and to Administer Parent Buddy.
  -> Forcefull Update Check API is for the App to check Country based data validity for reissuing a force update.
  -> Mobile Javascript management configuration provides an interface to manage the        Javascript that is loaded on ‘share’ url.
  -> The Administer Parent Buddy provides an interface to manage Master Languages. The Configuration  assists in triggering emails for Master Languages.

# Custom_serialization:

This module is for using Serialization for API rendering. The module also supports API formatting, Removed tags and URL changes for embedded images and cover images. 

# Pb_custom_migrate:

This module helps to manage source and migration mapping yml files. Can be fully disabled after the site migration is complete.
 
# Pb_custom_rest_api:

Expose the force API table as REST API.
 
# Pb_custom_standard_deviation:

Expose the Standard Deviation API. As there are multiple validation, this is treated as separate module.

