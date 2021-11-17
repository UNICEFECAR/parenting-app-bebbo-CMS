CONTENTS OF THIS FILE
---------------------

 * Introduction
 * Requirements
 * Installation
 * Configuration

INTRODUCTION
------------

The Views Custom Table module gives the functionality to integrate your custom
table to views. So you can use strong features of the views for your custom
table. This module use hook_view_data() to implement functionality.

 * For a full description of the module, visit the project page:
   https://www.drupal.org/project/view_custom_table

 * To submit bug reports and feature suggestions, or to track changes:
   https://www.drupal.org/project/issues/view_custom_table

REQUIREMENTS
------------

This module requires the following modules:

 * Views (Drupal Core)
 * Views UI (Drupal Core)

INSTALLATION
------------

 * Install as you would normally install a contributed Drupal module. Visit:
   https://www.drupal.org/docs/8/extending-drupal-8/installing-drupal-8-modules
   for further information.

CONFIGURATION
-------------

 * Configure user permissions in Administration » People » Permissions:

   - Add custom table in views

     Give user access to add his custom table to the system.

   - Administer all custom table in views

     Give user access to administer all the custom tables, added by all the
     users

   - Administer own custom table in views

     Give user access to administer own custom tables

 * Manage custom tables in Administration » Structure » Views » View Custom
   Table menu.
