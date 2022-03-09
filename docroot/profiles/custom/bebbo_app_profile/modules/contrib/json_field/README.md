# JSON Field

This module provides mechanisms for storing JSON data in fields, and various
tools to make editing it easier than using a plain text field.

## Supported field types

Three field types are provided by the module;

* "JSON (stored as text in database)"
  This option uses a VARCHAR or TEXT column in the database.
* "JSON (stored as raw JSON in database)"
  Stores the data in a JSON column in the database.
* "JSONB/JSON (stored as raw JSONB in PostgreSQL)"
  On PostgreSQL the data will be stored in a JSONB column, on MySQL (or MariaDB)
  the data will be stored in a regular JSON column.

## Installation

You need ideally the jsonview client library from
https://github.com/yesmeck/jquery-jsonview/releases. Put the latest release into
the site's "libraries" folder so the folder structure looks like the following:

```
- core
- libraries
 \- jsonview
   \- dist
     \- jquery.jsonview.css
     \- jquery.jsonview.js
- modules
```

## Using composer

If you are using composer you can add the following code to your root
 `composer.json`:

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
  },
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

And add the libraries to your project with:

```
composer require yesmeck/jquery-jsonview
composer require josdejong/jsoneditor
```
