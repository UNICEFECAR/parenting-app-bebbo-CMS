# Pb_custom_form

ABOUT
------------
This module provides Configuration pages for Force Update API Check, Mobile Javascript management and to Administer Parent Buddy.
  -> Forcefull Update Check API is for the App to check Country based data validity for reissuing a force update.
  -> Mobile Javascript management configuration provides an interface to manage the Javascript that is loaded on ‘share’ url.
  -> The Administer Parent Buddy provides an interface to manage Master Languages. The Configuration  assists in triggering emails for Master Languages.

IMPLEMENTATION APPROACH
------------
Used hook schema to save this information. Please review: https://www.drupal.org/docs/7/api/schema-api/introduction-to-schema-api

The reason we have not used an entity approach, is as this data doesn’t need all the enhanced features of a Drupal entity, for additional functionalities around it like a node, user or comments.

PRE-REQUIREMENTS
------------------
Specific for Parent Buddy Project
