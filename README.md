# Bebbo CMS

#CONTENTS OF THIS FILE

Introduction
Requirements
Installation
Configuration
Maintainers

# Introduction

Parent Buddy CMS application is a headless implementation of Drupal 8 CMS where the contents will be added through the web interface and served as REST APIs for mobile App. This application is used to assist the editors to add different types of contents under different types of content types and taxonomies that will be configured in Drupal CMS.

[![Build Status](https://bebbo.app/)

# Requirements

Make sure you have installed all of the following prerequisites on your development machine:

1. Install [composer](https://getcomposer.org/doc/00-intro.md#installation-linux-unix-osx).
Optional - [Global composer installation](https://getcomposer.org/doc/00-intro.md#globally).
If skipping, you may need to replace `composer` with `php composer.phar` for your setup.

2. Install Drush
```    composer global require drush/drush
```

## Installation

1. Download Bebbo App from git repo 
   https://github.com/UNICEFECAR/parenting-app-bebbo-CMS
   Ex: git clone https://github.com/UNICEFECAR/parenting-app-bebbo-CMS
2. Download the Database from Acquia server and import the database into your local.
3. Update the database details in settings.php file ( docroot/sites/default/settings. php).
4. Then run the application in your browser.


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
- csv_serialization
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
- memcache
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

### Custom Libraries

- CKEDITOR

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

### Custom Roles

Globaladmin : This User handles all the country and country users, configures new languages and new country , Taxonomies data and offload a country.            
senior editor :   Senior editors have access to create , update , publish and translate the content to their country language.
Sme : SME have access to updates and approve the content.
Editor : Editor have access to create , update and translate the content to their country language
country admin : This user has access to create and cancel their country users and view their language content.

All the users have a separate Dashboard. Country admin and Senior editor have access the country reports.

## Menus

Global Content List - It shows all the published contents
Country Content List - In this page the user is able to see their allowed languages list.
Add Content - Editor , Globaladmin and Senioreditor have permission to create new content.

Manage Taxonomies - It shows all the available taxonomy terms
Manage Media - In this page User can add and update the image related details
Manage Country - Global admin can add any new country or update the already existing country and user details.
Manage Language - Create a new language or update an existing language. This have two options
Manage Users  -  Global admin can add another global admin using the language.
Manage Translation - Users can send a content translation request to memsource using this menu option.
Google Analytics -  Global admin can add the analytics id .
Import Taxonomy - Users can import the taxonomy term values using this option .Based on the documentation, users can change the feed configuration according to their language.
Manage reports - user can see their reports based on their allowed language

Configurations

Installation profile assists in setting up a base instance. 

Maintainers
Datamatics
