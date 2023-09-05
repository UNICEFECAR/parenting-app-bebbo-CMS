# JSON Field

This module provides mechanisms for storing JSON data in fields, and various
tools to make editing JSON data easier than using a plain text field.

For a full description of the module, visit the
[project page](https://www.drupal.org/project/json_field).

Submit bug reports and feature suggestions, or track changes in the
[issue queue](https://www.drupal.org/project/issues/json_field).


## Requirements

This module requires no modules outside of Drupal core.

The minimum database versions are:

* MySQL v5.7.8
* MariaDB v10.2.7
* PostgreSQL v9.2
* sqlite v3.26


## Recommended modules

If the [JSON:API Extras](https://www.drupal.org/project/jsonapi_extras) module
is installed it is possible to output the raw JSON data via a JSON API endpoint.
See below for further details.


## Installation

To improve the JSON editing and viewing experience, this module uses the
JSON Editor and jsonview client libraries.


### JSON Editor

The [JSON Editor library](https://github.com/josdejong/jsoneditor) provides an
improved UI for editing JSON data, e.g. on a node edit form.


#### Installing JSON Editor with composer

The easiest solution to add jsoneditor to a site is to use Composer.

To add the jsoneditor library using composer, add the following code to the website project's root `composer.json` file:

```
"repositories": [
  {
    "type": "package",
    "package": {
      "name": "josdejong/jsoneditor",
      "version": "v5.29.1",
      "type": "drupal-library",
      "dist": {
        "url": "https://github.com/josdejong/jsoneditor/archive/v5.29.1.zip",
        "type": "zip"
      },
      "source": {
        "url": "https://github.com/josdejong/jsoneditor",
        "type": "git",
        "reference": "v5.29.1"
      }
    }
  }
],
```

Then add the library to the website project:

```
composer require yesmeck/jquery-jsonview
```


#### Installing jsoneditor without composer

Download the JSON Editor library from the following URL:

* https://github.com/josdejong/jsoneditor

Place the latest releases into the site's "libraries" directory.

The directory structure should be as follows:

```
- core
- libraries
 \- jsoneditor
   \- dist
     \- jsoneditor.min.js
     \- jsoneditor.min.css
     \- img
       \- jsoneditor-icons.svg
- modules
```

All three files are required for the editor to work correctly.


### jsonview library

The [jsonview library](https://github.com/yesmeck/jquery-jsonview) provides an
improved UI for viewing JSON on the front-end, e.g. on a node page.


#### Installing jsonview with composer

The easiest solution to add jsonview to a site is to use Composer

To add the jsonview library using composer, add the following code to the website project's root `composer.json` file:

```
"repositories": [
  {
    "type": "package",
    "package": {
      "name": "yesmeck/jquery-jsonview",
      "version": "v1.2.3",
      "type": "drupal-library",
      "dist": {
        "url": "https://github.com/yesmeck/jquery-jsonview/archive/v1.2.3.zip",
        "type": "zip"
      },
      "source": {
        "url": "https://github.com/yesmeck/jquery-jsonview",
        "type": "git",
        "reference": "v1.2.3"
      }
    }
  }
],
```

Then add the library to the website project:

```
composer require yesmeck/jquery-jsonview
```


#### Installing jsonview without composer

Download the jsonview library from the following URL:

* https://github.com/yesmeck/jquery-jsonview

Place the latest releases into the site's "libraries" directory.

The directory structure should be as follows:

```
- core
- libraries
 \- jsonview
   \- dist
     \- jquery.jsonview.css
     \- jquery.jsonview.js
- modules
```

Both files must be present in order for the library to work correctly.


## Configuration

The module's functionality is provided as a set of field types, which may be
selected when adding a new field to an entity bundle.

The module provides three field types, but how each database system supports
them will vary:

* JSON (text)
  * Uses a VARCHAR or TEXT column to store data in the database.
* JSON (raw)
  * MySQL: Uses a JSON column to store data in the database.
  * PostgreSQL: Uses a JSON column to store data in the database.
  * MariaDB: Uses a LONGTEXT column to store data in the database.
  * Sqlite: Uses a TEXT column to store data in the database.
* JSONB/JSON (raw)
  * MySQL: Uses a JSON column to store data in the database.
  * PostgreSQL: Uses a JSONB column to store data in the database.
  * MariaDB: Uses a LONGTEXT column to store data in the database.
  * Sqlite: Uses a TEXT column to store data in the database.

By default the edit UI will present a simple textarea field for editing the JSON
data.


### Optional field widget

To use the JSON Editor library for editing JSON data, the included JSON Field
Widget module must be installed. Once installed, each field must have the widget
changed via the appropriate form display settings page.


### Note about MariaDB

MariaDB uses a [LONGTEXT column to store JSON data](https://mariadb.com/kb/en/json-data-type/),
which will be confusing at first. However, it then supports JSON queries
executed against the column.


## JSON data in core

Using JSON columns will cause problems with core's database export script due
to it not directly supporting "json" field types. There are existing core
issues focused on the necessary API changes:

* META issue: https://www.drupal.org/project/drupal/issues/3343634
* MySQL/MariaDB: https://www.drupal.org/project/drupal/issues/3143512
* PostgreSQL: https://www.drupal.org/project/drupal/issues/2472709
* sqlite: https://www.drupal.org/project/drupal/issues/3325871


## Returning JSON with JSON:API

By default, when accessed via JSON:API endpoints the JSON fields created with
this module will return a string, not raw JSON.

To return raw JSON from JSON fields, use the JSON:API Extras module:

1. Install [JSON:API Extras](https://www.drupal.org/project/jsonapi_extras).
2. Go to `admin/config/services/jsonapi/resource_types` and override the
  desired resource type.
3. For the JSON field, click "Advanced".
4. Select "JSON Field" for the enhancer.

Now the JSON field will return the raw JSON instead of a string.

If more control is needed, such as returning some but not all JSON values, the
FieldItemNormalizer can be overridden to check for the JSON field and manually
decode the data there.


## Maintainers

- [Damien McKenna](https://www.drupal.org/u/damienmckenna)
- Daniel Wehner - [dawehner](https://www.drupal.org/u/dawehner)
- [Jesin](https://www.drupal.org/u/jaesin)
