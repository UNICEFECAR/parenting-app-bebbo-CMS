#CONTENTS OF THIS FILE

Introduction
Requirements
Installation
Configuration
Maintainers


# Introduction 
Bebbo App Profile

An installation profile provides with all necessary pre-configured features related to the Bebbo App . It only provides all the required configuration details for the new application. 

Note: Content/data has to be added manually through the Drupal interfaces.

[![Build Status](https://bebbo.app/)

# Requirements

Make sure you have installed all of the following prerequisites on your development machine:

1.Install [composer]: Follow the instructions mentioned in the below link        (https://getcomposer.org/doc/00-intro.md#installation-linux-unix-osx). 
 Optional - [Global composer installation] (https://getcomposer.org/doc/00-intro.md#globally). If you face any issues or global installation fails, you may need to replace `composer` with `php composer.phar` for your setup.
 
2.Install Drush, execute the following in terminal
  composer global require drush/drush
  
3.Install Drupal: To Download Drupal 8.9.14  through composer execute the following command in terminal
‘ composer create-project drupal/recommended-project:^8.9.14' {folder name } ‘

4.Install CK editor: Download the CKEDITOR library(https://github.com/ckeditor/ckeditor4.git) and place it in “libraries/” folder.Unzip ckeditor library into libraries folder as ckeditor.



## Installation

1. Download Bebbo App profile from git 
2. Place the bebbo_app_profile folder in your site “profile/” directory. 
3. Please select the  “Bebbo App Framework V1.0” from the installation screen
4. Enter the database configuration details and then click on the ‘Save and continue’ button
5. Then Provide the basic site details like site name , Time zone and admin credentials
6. After successful installation, users will be redirected to the home page of the new installation.


## Scope

An installation profile provides with all necessary pre-configured features, Contributed, custom modules and themes.

### Contributed Modules

The following contributed modules are installed as part of the profile
- acquia_purge
- actions_permissions
- admin_toolbar
- admin_toolbar_links_access_filter
- admin_toolbar_search
- admin_toolbar_tools
- allowed_languages
- automated_cron
- basic_auth
- big_pipe
- block
- block_content
- breakpoint
- ckeditor
- ckeditor_media_embed
- color
- config
- config_ignore
- config_translation
- content_moderation
- content_moderation_notifications
- contextual
# - csv_serialization
- date_popup
- datetime
- dynamic_page_cache
- editor
- entity
- feeds
- feeds_tamper
- field
- field_ui
- file
- filter
- gnode
- google_analytics
- group
- help
- image
- image_style_quality
- json_field
- lang_dropdown
- language
- languagefield
- link
- locale
- media
- media_library
# - memcache
- menu_link_content
- menu_per_role
- menu_ui
- migrate
- migrate_drupal
- migrate_plus
- migrate_source_csv
- migrate_tools
- migrate_upgrade
- node
- options
- page_cache
- path
- path_alias
- purge
- purge_ui
- quickedit
- rdf
- rest
- restui
- search
- seckit
- serialization
- shortcut
- smtp
- syslog
- system
- tamper
- taxonomy
- text
- title_length
- tmgmt
- tmgmt_config
- tmgmt_content
- tmgmt_demo
- tmgmt_file
- tmgmt_language_combination
- tmgmt_local
- tmgmt_locale
- tmgmt_memsource
- toolbar
- toolbar_menu
- toolbar_menu_clean
- tour
- user
- variationcache
- video_embed_field
- video_embed_media
- view_custom_table
- views_bulk_operations
- views_data_export
- workflows
- content_translation
- views

### Custom Modules

The following Custom modules are installed as part of the profile
- custom_serialization
- group_country_field
- pb_custom_field
- pb_custom_form
- pb_custom_migrate
- pb_custom_rest_api
- pb_custom_standard_deviation

### Theme

The theme is installed and enabled by the profile.
- bartik
- seven
- stable
- classy
- claro

### Manual configuration

1. Login as admin and create the menu links as required under the Editorial Menu.
2. Create a new  globaladmin user details. ( Note:- For Global Admin, please select all languages.)




