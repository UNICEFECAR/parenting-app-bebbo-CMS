[![Build Status](https://travis-ci.org/Jaesin/json_field.svg?branch=8.x-1.x)](https://travis-ci.org/Jaesin/json_field)

# Installation

You need ideally the jsonview client library from
https://github.com/yesmeck/jquery-jsonview/releases. Put the latest release into
our sites/all/libraries folder so the folder structure looks like the following:

```
- libraries
 \- jsonview
   \- dist
     \- jquery.jsonview.css
     \- jquery.jsonview.js
```

Using composer

```    
"repositories": [
    {
        "type": "composer",
        "url": "https://packages.drupal.org/8"
    },
    {
        "type": "package",
        "package": {
            "name": "yesmeck/jquery-jsonview",
            "version": "1.2.3",
            "type": "drupal-library",
            "dist": {
                "url": "https://github.com/yesmeck/jquery-jsonview/archive/master.zip",
                "type": "zip"
            },
            "extra": {
                "installer-name": "jsonview"
            }
        }
    }
]
