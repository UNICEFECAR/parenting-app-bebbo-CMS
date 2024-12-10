<h1>Bebbo CMS - Drupal content management system</h1>

### Table of Contents

* [Introduction](#introduction)
* [Requirements](#requirements)
* [Installation](#installation)
* [Contributed Modules](#contributed-modules)
* [Custom Modules](#custom-modules)
* [Theme](#theme)
* [Custom Roles](#custom-roles)
* [Menus](#menus)
* [Configurations](#configurations)
* [Maintainers](#maintainers)

## Introduction

Parent Buddy CMS application is a headless implementation of Drupal 8 CMS where the contents will be added through the web interface and served as REST APIs for mobile App. This application is used to assist the editors to add different types of contents under different types of content types and taxonomies that will be configured in Drupal CMS.

[Bebbo website](https://bebbo.app/)

## Requirements

- **PHP** (7.4 or higher, based on your project requirements)
- **Composer** (for managing dependencies)
- **MySQL** or **MariaDB**
- **Apache** or **Nginx** (this guide assumes Apache)
- Drupal's core installed in your project


Make sure you have installed all of the following prerequisites on your development machine:

1. Install [composer](https://getcomposer.org/doc/00-intro.md#installation-linux-unix-osx).
Optional - [global composer installation](https://getcomposer.org/doc/00-intro.md#globally).
If skipping, you may need to replace `composer` with `php composer.phar` for your setup.

2. Install Drush: `composer global require drush/drush`

## Installation

1. Download Bebbo App from [git repo](https://github.com/UNICEFECAR/parenting-app-bebbo-CMS)

   For example: `git clone https://github.com/UNICEFECAR/parenting-app-bebbo-CMS`

3. Download the database from Acquia server and import the database into your local. If you don’t have access to Acquia, you can download the dump database from this [link](https://drive.google.com/file/d/1mha-fwtKjb7931MFCEcAXVNOQt_IJ7Ce/view).
4. Update the database details in settings.php file (docroot/sites/default/settings.php).
5. Then run the application in your browser.

## Contributed Modules

The following contributed modules are installed as part of the profile:
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

## Custom Libraries

- CKEDITOR

## Custom Modules

The following custom modules are installed as part of the profile:
- custom_serialization
- group_country_field
- pb_custom_field
- pb_custom_form
- pb_custom_migrate
- pb_custom_rest_api
- pb_custom_standard_deviation

## Theme

The following themes are installed and enabled by the profile:
- bartik
- seven
- stable
- classy
- claro

## Custom Roles

Globaladmin: This User handles all the country and country users, configures new languages and new country, Taxonomies data and offload a country.
Senior editor: Senior editors have access to create, update, publish and translate the content to their country language.
SME: SME have access to updates and approve the content.
Editor: Editor have access to create, update and translate the content to their country language
Country admin: This user has access to create and cancel their country users and view their language content.

All the users have a separate dashboard. Country admin and Senior editor have access to the country reports.

## Menus

Global content list - It shows all the published contents.
Country content list - In this page the user will be able to see their allowed languages.
Add content - Editor, global admin and senior editor have permission to create a new content.

Manage Taxonomies - It shows all the available taxonomy terms
Manage Media - In this page User can add and update the image related details
Manage Country - Global admin can add any new country or update the already existing country and user details
Manage Language - Create a new language or update an existing language. This have two options
Manage Users  -  Global admin can add another global admin using the language
Manage Translation - Users can send a content translation request to memsource using this menu option
Google Analytics -  Global admin can add the analytics id
Import Taxonomy - Users can import the taxonomy term values using this option .Based on the documentation, users can change the feed configuration according to their language
Manage reports - user can see their reports based on their allowed language.

## Configurations

Installation profile assists in setting up a base instance.

## Multi-Site Installation Guide for Drupal

   This guide details the process of setting up a multi-site Drupal installation locally, with `bebbo.app` as the main site and `bangladesh` (accessible at `babuni.app`) as a sub-site located in the `sites/bangladesh` directory.

1. **Configure `sites.php`:**

   - Open the `sites.php` file located in the project root directory. `/docroot/sites/sites.php`.
   - Add a new entry for the Bangladesh sub-site in the file:
   ```php
   $sites['babuni.app'] = 'bangladesh';
   ```

2. **Create `sites/bangladesh` Directory:**

   - Inside the `sites` directory, create a new folder named `bangladesh`.

3. **Configure `settings.php` in `sites/bangladesh`:**

   - Create a new file named `settings.php` inside the `sites/bangladesh` directory.
   - Copy the contents of `sites/default/default.settings.php` into this new file.
   - Update the following settings in `sites/bangladesh/settings.php`:
   - Add database connection settings for the Bangladesh sub-site at the end of the file:
   ```php
   $databases['default']['default'] = [
      'database' => 'DATABASE', // Replace with your sub-site database name
      'username' => 'USERNAME', // Replace with your sub-site username
      'password' => 'PASSWORD', // Replace with your sub-site password
      'host' => '127.0.0.1', // Replace with your local databse host name or IP address
      'port' => '3306',
      'namespace' => 'Drupal\\Core\\Database\\Driver\\mysql',
      'driver' => 'mysql',
      'prefix' => '',
   ];
   // Replace with your sub-site database name
   $config_directories[CONFIG_SYNC_DIRECTORY] = '../config_bangladesh'; // Point to the `config_bangladesh` folder
   ```
4. **Create `config_bangladesh` Folder:**

   - In the project root directory, create a directory named `docroot/config_bangladesh`. This is where you'll store configuration specific to the Bangladesh sub-site.

5. **Download and Import Database (Optional):**

   - If you have a pre-existing database for the Bangladesh sub-site, import it to your newly created database.

6. **Configure Apache (Optional):**

   - If you're using Apache, you'll need to configure a virtual host for the Bangladesh sub-site. This involves creating a new `.conf` file in the `sites-available` directory and enabling it in the `sites-enabled` directory. Refer to your Apache documentation for specific instructions. Typically, the `.conf` file will contain details like the server name (babuni.app) and the document root (path to the `sites/bangladesh` directory).

7. **Access the Sub-site:**

   - Access the Bangladesh sub-site by visiting `http://babuni.app` in your browser.

## Maintainers

Datamatics
